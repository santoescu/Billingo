<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentoEmitido;
use App\Models\FiscalResponsibility;
use App\Models\PaymentMeansCode;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Pantalla de aterrizaje (selector/editor de empresas, sin métricas) y
 * Panel (resumen de actividad por módulo de la empresa activa) -- separados
 * en dos pantallas/rutas para no mezclar "elegir con qué empresa trabajar"
 * con "ver cómo le está yendo". El Panel usa ventanas fijas con botones
 * rápidos (hoy/semana/mes/año) en vez de un selector de rango libre --
 * siguiendo el patrón que ya usan sistemas comparables (POS/facturación):
 * rango libre queda para una futura sección de "Reportes", el panel
 * principal se mantiene a ventanas fijas para lectura rápida.
 */
class DashboardController extends Controller
{
    private const PERIODS = ['today', 'week', 'month', 'last_month', 'year'];

    public function index(Request $request)
    {
        $companies = $request->user()->companiesWithMembership();
        $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();
        $departments = Department::orderBy('descripcion')->get();

        return view('dashboard', compact('companies', 'fiscalResponsibilities', 'departments'));
    }

    public function panel(Request $request)
    {
        $panelData = $this->resolvePanelData($request);
        if ($panelData instanceof \Illuminate\Http\RedirectResponse) {
            return $panelData;
        }

        return view('panel', $panelData);
    }

    public function panelPdf(Request $request)
    {
        $panelData = $this->resolvePanelData($request);
        if ($panelData instanceof \Illuminate\Http\RedirectResponse) {
            return $panelData;
        }

        $pdf = Pdf::loadView('panel-pdf', $panelData)->setPaper('letter', 'portrait');

        return $pdf->stream('panel-' . $panelData['period'] . '.pdf');
    }

    /**
     * Toda la data del Panel (usada tanto por la vista normal como por el
     * PDF, para que ambas muestren exactamente lo mismo) -- devuelve un
     * redirect si no hay empresa activa en sesión.
     *
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function resolvePanelData(Request $request)
    {
        $companyId = session('selected_company.id');
        if (! $companyId) {
            return redirect()->route('dashboard');
        }

        $company = Company::find($companyId);
        if (! $company) {
            return redirect()->route('dashboard');
        }

        $myModuleRoles = session('selected_company.modules', []);
        $isModuleAdmin = fn (string $module) => in_array($myModuleRoles[$module] ?? null, ['owner', 'administrador'], true);

        $period = $this->resolvePeriod($request);
        $warehouses = $company->warehouses()->orderBy('name')->get();

        $data = [];
        if ($isModuleAdmin('invoicing')) {
            /**
             * Una venta POS que se convierte a factura electrónica genera
             * un DocumentoEmitido NUEVO y separado (numeral propio, ver
             * IssueDocumentService::issuePosSaleElectronic()) -- la venta
             * original sigue en "documentos_pos". Sin excluirla acá, el
             * panel contaba la misma venta real dos veces: una como POS y
             * otra como facturación (cantidades, ingresos, medios de pago,
             * todo duplicado). Se excluye por completo del lado de
             * facturación, ya que esa venta ya quedó contada del lado POS.
             */
            $posOriginatedIds = $company->documentosPos()
                ->whereNotNull('documento_emitido_id')
                ->get()
                ->pluck('documento_emitido_id')
                ->filter()
                ->values()
                ->all();

            $data['invoicing'] = $this->fetchWindowData(
                $company->documentosEmitidos()->whereNotIn('_id', $posOriginatedIds),
                $period
            );
        }
        if ($isModuleAdmin('pos')) {
            $data['pos'] = $this->fetchWindowData($company->documentosPos(), $period);
        }
        if ($isModuleAdmin('cotizaciones')) {
            $data['cotizaciones'] = $this->fetchWindowData($company->quotations(), $period);
        }

        return [
            'company' => $company,
            'period' => $period['key'],
            'metrics' => $this->buildMetrics($company, $data),
            'utility' => $this->buildUtility($company, $data),
            'trend' => $this->buildTrend($data, $period),
            'moduleDistribution' => $this->buildModuleDistribution($data),
            'invoiceStatusBreakdown' => $this->buildInvoiceStatusBreakdown($data),
            'warehouseComparison' => $this->buildWarehouseComparison($warehouses, $data),
            'cashierComparison' => $this->buildCashierComparison($company, $data),
            'paymentMethodBreakdown' => $this->buildPaymentMethodBreakdown($data),
            'topProductsInvoicing' => $this->buildTopProducts($data, 'invoicing'),
            'topProductsPos' => $this->buildTopProducts($data, 'pos'),
            'topClientsInvoicing' => $this->buildTopClients($data, 'invoicing'),
            'topClientsPos' => $this->buildTopClients($data, 'pos'),
            'recentActivity' => $this->buildRecentActivity($data),
            'lowStockProducts' => $this->lowStockProducts($company, $data),
            'receivables' => $isModuleAdmin('invoicing') ? $this->buildReceivables($company) : null,
        ];
    }

    /**
     * "period" de la query string (today/week/month/last_month/year, la
     * manda cada botón de la barra de filtros); si falta o no es válido,
     * cae a "month". El periodo anterior de igual largo se calcula para el
     * % de cambio en cada métrica.
     *
     * @return array{key: string, from: \Carbon\Carbon, to: \Carbon\Carbon, prev_from: \Carbon\Carbon, prev_to: \Carbon\Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $key = $request->query('period');
        if (! in_array($key, self::PERIODS, true)) {
            $key = 'month';
        }

        /**
         * La app guarda todo en UTC (config('app.timezone') = 'UTC', mismo
         * patrón que el resto del código -- ver, p. ej.,
         * IssueDocumentService o documents/show.blade.php, que siempre
         * convierten a America/Bogota antes de mostrar una fecha). Sin
         * esto, "hoy" empezaba a la medianoche UTC (7pm de ayer en
         * Colombia) y las horas de la gráfica de tendencia salían
         * corridas ~5 horas.
         */
        $from = match ($key) {
            'today' => now('America/Bogota')->startOfDay(),
            'week' => now('America/Bogota')->startOfWeek(),
            'last_month' => now('America/Bogota')->subMonthNoOverflow()->startOfMonth(),
            'year' => now('America/Bogota')->startOfYear(),
            default => now('America/Bogota')->startOfMonth(),
        };
        $to = $key === 'last_month' ? now('America/Bogota')->startOfMonth()->subSecond() : now('America/Bogota');

        $lengthDays = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($lengthDays - 1)->startOfDay();

        return ['key' => $key, 'from' => $from, 'to' => $to, 'prev_from' => $prevFrom, 'prev_to' => $prevTo];
    }

    /**
     * @return array{today: Collection, period: Collection, previous: Collection}
     */
    private function fetchWindowData($relation, array $period): array
    {
        return [
            'today' => (clone $relation)->where('created_at', '>=', now('America/Bogota')->startOfDay())->get(),
            'period' => (clone $relation)->whereBetween('created_at', [$period['from'], $period['to']])->get(),
            'previous' => (clone $relation)->whereBetween('created_at', [$period['prev_from'], $period['prev_to']])->get(),
        ];
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array<string, array<string, mixed>>
     */
    private function buildMetrics(Company $company, array $data): array
    {
        $metrics = [];

        if (isset($data['invoicing'])) {
            $period = $data['invoicing']['period'];
            $metrics['invoicing'] = [
                'today_count' => $data['invoicing']['today']->count(),
                'today_total' => (float) $data['invoicing']['today']->sum('total'),
                'period_count' => $period->count(),
                'period_total' => (float) $period->sum('total'),
                'period_change_pct' => $this->percentChange((float) $data['invoicing']['previous']->sum('total'), (float) $period->sum('total')),
                'period_issues' => $period->whereIn('status', [DocumentoEmitido::STATUS_REJECTED, DocumentoEmitido::STATUS_ERROR])->count(),
            ];
        }

        if (isset($data['pos'])) {
            $period = $data['pos']['period'];
            $metrics['pos'] = [
                'open_shifts' => CashShift::where('company_id', (string) $company->_id)->open()->count(),
                'today_count' => $data['pos']['today']->count(),
                'today_total' => (float) $data['pos']['today']->sum('total'),
                'period_count' => $period->count(),
                'period_total' => (float) $period->sum('total'),
                'period_change_pct' => $this->percentChange((float) $data['pos']['previous']->sum('total'), (float) $period->sum('total')),
            ];
        }

        if (isset($data['cotizaciones'])) {
            $period = $data['cotizaciones']['period'];
            $pendingCount = $company->quotations()
                ->whereNull('documento_pos_id')
                ->whereNull('documento_emitido_id')
                ->count();

            $metrics['cotizaciones'] = [
                'pending_count' => $pendingCount,
                'converted_period_count' => $period->filter(fn ($q) => $q->documento_pos_id || $q->documento_emitido_id)->count(),
                'period_total' => (float) $period->sum('total'),
                'period_change_pct' => $this->percentChange((float) $data['cotizaciones']['previous']->sum('total'), (float) $period->sum('total')),
            ];
        }

        return $metrics;
    }

    /**
     * Utilidad bruta del periodo elegido (facturación + POS -- las
     * cotizaciones no cuentan, todavía no son una venta). El costo sale de
     * los StockMovement de salida que ya quedaron guardados al vender (ver
     * IssueDocumentService::discountInventory(), que guarda
     * "reason" => "document:{numeral}" con el costo promedio del producto
     * en ese momento) -- así la utilidad usa el costo real de cuando se
     * vendió, no el costo actual del producto.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{revenue: float, cogs: float, profit: float, margin_pct: float, change_pct: ?float}|null
     */
    private function buildUtility(Company $company, array $data): ?array
    {
        if (! isset($data['invoicing']) && ! isset($data['pos'])) {
            return null;
        }

        $periodDocs = collect()->merge($data['invoicing']['period'] ?? [])->merge($data['pos']['period'] ?? []);
        $previousDocs = collect()->merge($data['invoicing']['previous'] ?? [])->merge($data['pos']['previous'] ?? []);

        $periodProfit = $this->profitFor($company, $periodDocs);
        $previousProfit = $this->profitFor($company, $previousDocs);

        return [
            'revenue' => $periodProfit['revenue'],
            'cogs' => $periodProfit['cogs'],
            'profit' => $periodProfit['profit'],
            'margin_pct' => $periodProfit['revenue'] > 0 ? round($periodProfit['profit'] / $periodProfit['revenue'] * 100, 1) : 0.0,
            'change_pct' => $this->percentChange($previousProfit['profit'], $periodProfit['profit']),
        ];
    }

    /**
     * @return array{revenue: float, cogs: float, profit: float}
     */
    private function profitFor(Company $company, Collection $documents): array
    {
        $revenue = (float) $documents->sum('subtotal');
        $reasons = $documents->map(fn ($d) => 'document:' . $d->numeral)->all();

        $cogs = empty($reasons) ? 0.0 : (float) StockMovement::where('company_id', (string) $company->_id)
            ->whereIn('reason', $reasons)
            ->sum('total_cost');

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => $revenue - $cogs,
        ];
    }

    /**
     * Sigue el periodo elegido arriba (no una ventana fija): por hora si es
     * "hoy", por día si es "esta semana"/"este mes", por mes si es "este
     * año" -- para que la tendencia siempre tenga una granularidad que
     * tenga sentido para lo que se está mirando. Reusa las colecciones ya
     * traídas en $data[...]['period'] (mismo rango), sin volver a consultar
     * la base de datos.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @param  array{key: string, from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    private function buildTrend(array $data, array $period): array
    {
        $from = $period['from'];
        $to = $period['to'];
        $buckets = [];

        if ($period['key'] === 'today') {
            for ($hour = 0; $hour < 24; $hour++) {
                $start = $from->copy()->addHours($hour);
                if ($start->gt($to)) {
                    break;
                }

                $buckets[] = [
                    'start' => $start,
                    'end' => $start->copy()->addHour()->subSecond(),
                    'label' => $start->format('H:00'),
                ];
            }
        } elseif ($period['key'] === 'year') {
            $cursor = $from->copy()->startOfMonth();
            while ($cursor->lte($to)) {
                $buckets[] = [
                    'start' => $cursor->copy(),
                    'end' => $cursor->copy()->endOfMonth(),
                    'label' => $cursor->translatedFormat('M'),
                ];
                $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
            }
        } else {
            $cursor = $from->copy()->startOfDay();
            while ($cursor->lte($to)) {
                $buckets[] = [
                    'start' => $cursor->copy(),
                    'end' => $cursor->copy()->endOfDay(),
                    'label' => $cursor->format('d/m'),
                ];
                $cursor = $cursor->addDay();
            }
        }

        $series = [];
        foreach (['invoicing', 'pos', 'cotizaciones'] as $module) {
            if (! isset($data[$module])) {
                continue;
            }

            $docs = $data[$module]['period'];
            $series[$module] = collect($buckets)
                ->map(fn (array $bucket) => (float) $docs
                    ->filter(fn ($doc) => $doc->created_at >= $bucket['start'] && $doc->created_at <= $bucket['end'])
                    ->sum('total'))
                ->all();
        }

        return [
            'labels' => collect($buckets)->pluck('label')->all(),
            'series' => $series,
        ];
    }

    /**
     * Distribución de ingresos entre módulos en el periodo elegido -- solo
     * tiene sentido si administra 2 o más módulos con datos, si no es
     * redundante con la tarjeta de esa única métrica.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{modules: array<int, string>, labels: array<int, string>, values: array<int, float>}|null
     */
    private function buildModuleDistribution(array $data): ?array
    {
        $modules = [];
        $labels = [];
        $values = [];

        foreach (['invoicing', 'pos', 'cotizaciones'] as $module) {
            if (! isset($data[$module])) {
                continue;
            }

            $total = (float) $data[$module]['period']->sum('total');
            if ($total <= 0) {
                continue;
            }

            $modules[] = $module;
            $labels[] = config("modules.$module.name");
            $values[] = $total;
        }

        return count($values) >= 2 ? ['modules' => $modules, 'labels' => $labels, 'values' => $values] : null;
    }

    /**
     * Cuántos documentos de facturación quedaron en cada estado DIAN en el
     * periodo elegido -- ayuda a notar de un vistazo si hay muchos
     * rechazados/con error. Reusa DocumentoEmitido::status_label (mismo
     * texto que ya se usa en el listado de documentos).
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{labels: array<int, string>, values: array<int, int>}|null
     */
    private function buildInvoiceStatusBreakdown(array $data): ?array
    {
        if (! isset($data['invoicing']) || $data['invoicing']['period']->isEmpty()) {
            return null;
        }

        $counts = $data['invoicing']['period']->countBy(fn ($doc) => $doc->status_label);

        return ['labels' => $counts->keys()->all(), 'values' => $counts->values()->all()];
    }

    /**
     * Ventas por bodega en el periodo elegido, cruzando todos los módulos
     * visibles -- solo se calcula si la empresa tiene 2 o más bodegas. Suma
     * cantidad*precio_unitario por línea agrupado por bodega_id, igual que
     * buildTopProducts() pero agrupando por bodega en vez de producto.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    private function buildWarehouseComparison(Collection $warehouses, array $data): ?array
    {
        if ($warehouses->count() < 2) {
            return null;
        }

        $lines = collect();
        foreach (['invoicing', 'pos', 'cotizaciones'] as $module) {
            if (! isset($data[$module])) {
                continue;
            }

            $lines = $lines->merge($data[$module]['period']->flatMap(fn ($doc) => $doc->payload['lineas'] ?? []));
        }

        $lines = $lines->filter(fn (array $line) => ! empty($line['bodega_id']));
        if ($lines->isEmpty()) {
            return null;
        }

        $namesById = $warehouses->mapWithKeys(fn (Warehouse $w) => [(string) $w->_id => $w->name]);

        $totals = $lines
            ->groupBy('bodega_id')
            ->map(fn (Collection $group) => (float) $group->sum(fn (array $line) => $line['cantidad'] * $line['precio_unitario']))
            ->sortByDesc(fn ($total) => $total);

        return [
            'labels' => $totals->keys()->map(fn ($id) => $namesById->get($id, __('Unknown warehouse')))->all(),
            'values' => $totals->values()->all(),
        ];
    }

    /**
     * Cuánto se pagó por cada medio de pago en el periodo elegido -- por
     * separado para facturación y para POS (son medios de pago de
     * naturaleza distinta, no tiene sentido sumarlos en un solo total). En
     * facturación cada documento tiene un solo medio de pago
     * (payment_means_code, código DIAN -- se resuelve el nombre legible con
     * PaymentMeansCode). En POS una venta puede haberse pagado con varios
     * medios a la vez (repartiendo el total entre ellos): se usa el arreglo
     * "payments" (payment_method_name + amount por cada medio), ya guardado
     * así desde que existe pago dividido -- ver
     * DocumentoEmitidoController::issuePosSale(). Si una venta vieja no
     * tiene "payments" (dato guardado antes de esa función), cae al medio
     * único de siempre con el total completo.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{invoicing: array{labels: array<int, string>, values: array<int, float>}|null, pos: array{labels: array<int, string>, values: array<int, float>}|null}
     */
    private function buildPaymentMethodBreakdown(array $data): array
    {
        $invoicingTotals = collect();

        if (isset($data['invoicing'])) {
            $codes = PaymentMeansCode::all()->keyBy('codigo');

            foreach ($data['invoicing']['period'] as $doc) {
                if (! $doc->payment_means_code) {
                    continue;
                }

                $label = $codes->get($doc->payment_means_code)?->medio ?? $doc->payment_means_code;
                $invoicingTotals[$label] = ($invoicingTotals[$label] ?? 0) + (float) $doc->total;
            }
        }

        $posTotals = collect();

        if (isset($data['pos'])) {
            foreach ($data['pos']['period'] as $doc) {
                $payments = $doc->payments ?: ($doc->payment_method_name ? [[
                    'payment_method_name' => $doc->payment_method_name,
                    'amount' => $doc->total,
                ]] : []);

                foreach ($payments as $payment) {
                    $label = $payment['payment_method_name'] ?? __('Unknown');
                    $posTotals[$label] = ($posTotals[$label] ?? 0) + (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return [
            'invoicing' => $this->sortedLabelsAndValues($invoicingTotals),
            'pos' => $this->sortedLabelsAndValues($posTotals),
        ];
    }

    /**
     * @param  Collection<string, float>  $totals
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    private function sortedLabelsAndValues(Collection $totals): ?array
    {
        if ($totals->isEmpty()) {
            return null;
        }

        $totals = $totals->sortDesc();

        return ['labels' => $totals->keys()->all(), 'values' => $totals->values()->all()];
    }

    /**
     * Ventas por cajero en el periodo elegido (solo POS) -- cada venta
     * queda ligada a un turno (shift_id) y cada turno a un usuario
     * (CashShift.user_id), así que se resuelve el cajero por ese camino.
     * Solo se muestra si hubo ventas de 2 o más cajeros distintos, si no
     * es redundante con la tarjeta de POS.
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    private function buildCashierComparison(Company $company, array $data): ?array
    {
        if (! isset($data['pos']) || $data['pos']['period']->isEmpty()) {
            return null;
        }

        $sales = $data['pos']['period'];
        $shiftIds = $sales->pluck('shift_id')->filter()->unique()->values()->all();
        if (empty($shiftIds)) {
            return null;
        }

        $userIdByShift = CashShift::whereIn('_id', $shiftIds)->get()
            ->mapWithKeys(fn (CashShift $shift) => [(string) $shift->_id => (string) $shift->user_id]);

        $totals = $sales
            ->groupBy(fn ($sale) => $userIdByShift->get((string) $sale->shift_id))
            ->map(fn (Collection $group) => (float) $group->sum('total'));

        if ($totals->count() < 2) {
            return null;
        }

        $namesById = User::whereIn('_id', $totals->keys()->filter()->all())->get()
            ->mapWithKeys(fn (User $u) => [(string) $u->_id => $u->name]);

        $totals = $totals->sortByDesc(fn ($total) => $total);

        return [
            'labels' => $totals->keys()->map(fn ($id) => $namesById->get($id, __('Unknown')))->all(),
            'values' => $totals->values()->all(),
        ];
    }

    /**
     * Productos más vendidos/facturados en el periodo elegido, de UN solo
     * módulo a la vez (antes se cruzaban invoicing+pos+cotizaciones en una
     * sola lista, pero eso mezclaba dos negocios distintos con volúmenes
     * distintos -- separarlos deja ver qué se mueve en el mostrador vs. qué
     * se factura electrónicamente). Las líneas de documentos/ventas/
     * cotizaciones ya se guardan con el mismo shape (codigo, descripcion,
     * cantidad, precio_unitario), ver DocumentJsonMapper::mapLines().
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array<int, array{codigo: ?string, descripcion: string, cantidad: float, total: float}>
     */
    private function buildTopProducts(array $data, string $module): array
    {
        if (! isset($data[$module])) {
            return [];
        }

        $lines = $data[$module]['period']->flatMap(fn ($doc) => $doc->payload['lineas'] ?? []);

        return $lines
            ->groupBy(fn (array $line) => $line['codigo'] ?: $line['descripcion'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'codigo' => $first['codigo'] ?? null,
                    'descripcion' => $first['descripcion'] ?? '',
                    'cantidad' => (float) $group->sum('cantidad'),
                    'total' => (float) $group->sum(fn (array $line) => $line['cantidad'] * $line['precio_unitario']),
                ];
            })
            ->sortByDesc('total')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Clientes que más compraron en el periodo elegido, de un solo módulo a
     * la vez -- agrupa por cliente_id (guardado en cada documento/venta) y
     * usa el nombre ya guardado en el payload (accounting_customer_party),
     * sin necesitar otra consulta a ThirdParty. Null si no hay al menos 2
     * clientes distintos con compras (con 1 solo no aporta nada nuevo que
     * ya no se vea en la tarjeta del módulo).
     *
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array<int, array{name: string, total: float}>|null
     */
    private function buildTopClients(array $data, string $module): ?array
    {
        if (! isset($data[$module]) || $data[$module]['period']->isEmpty()) {
            return null;
        }

        $totals = $data[$module]['period']
            ->groupBy(fn ($doc) => $doc->cliente_id ?: 'sin-cliente')
            ->map(function (Collection $group) {
                /**
                 * El nombre del cliente queda "congelado" en el payload de
                 * cada documento tal como estaba en ese momento -- si
                 * cambió de razón social entre una compra y otra, cada
                 * documento tiene un nombre distinto para el mismo
                 * cliente_id. Se usa el del documento más reciente del
                 * grupo (el más representativo hoy), no el primero que
                 * salga en la colección (orden de Mongo, no
                 * necesariamente cronológico).
                 */
                $mostRecent = $group->sortByDesc('created_at')->first();
                $name = $mostRecent->payload['accounting_customer_party']['razon_social'] ?? null;

                return [
                    'name' => $name ?: __('Unknown'),
                    'total' => (float) $group->sum('total'),
                ];
            });

        if ($totals->count() < 2) {
            return null;
        }

        return $totals->sortByDesc('total')->take(8)->values()->all();
    }

    /**
     * @param  array<string, array{today: Collection, period: Collection, previous: Collection}>  $data
     * @return array<int, array{type: string, label: string, title: string, total: float, created_at: \Carbon\Carbon, url: string}>
     */
    private function buildRecentActivity(array $data): array
    {
        $items = collect();

        if (isset($data['invoicing'])) {
            $items = $items->merge($data['invoicing']['period']->map(fn ($d) => [
                'type' => 'invoicing',
                'label' => __('Invoice'),
                'title' => $d->numeral,
                'total' => (float) $d->total,
                'created_at' => $d->created_at,
                'url' => route('documents.show', $d->_id),
            ]));
        }

        if (isset($data['pos'])) {
            $items = $items->merge($data['pos']['period']->map(fn ($d) => [
                'type' => 'pos',
                'label' => __('POS sale'),
                'title' => $d->numeral,
                'total' => (float) $d->total,
                'created_at' => $d->created_at,
                'url' => route('pos.sales.show', $d->_id),
            ]));
        }

        if (isset($data['cotizaciones'])) {
            $items = $items->merge($data['cotizaciones']['period']->map(fn ($q) => [
                'type' => 'cotizaciones',
                'label' => __('Quotation'),
                'title' => $q->numeral,
                'total' => (float) $q->total,
                'created_at' => $q->created_at,
                'url' => route('quotations.show', $q->_id),
            ]));
        }

        return $items->sortByDesc('created_at')->take(8)->values()->all();
    }

    /**
     * Productos con inventario controlado y stock por debajo del umbral
     * fijo -- visible para cualquier módulo administrado, ya que el
     * inventario es compartido entre POS/facturación/cotizaciones.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, Product>
     */
    private function lowStockProducts(Company $company, array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return $company->products()->active()
            ->where('tracks_inventory', true)
            ->where('stock', '<=', Product::LOW_STOCK_THRESHOLD)
            ->orderBy('stock')
            ->limit(8)
            ->get()
            ->all();
    }

    /**
     * Cartera (cuentas por cobrar) -- solo las facturas electrónicas a
     * crédito (payment_means_id = "2") que aún no se han marcado como
     * pagadas cuentan acá. Las ventas POS se consideran pagadas al momento
     * de la venta y las facturas a contado se pagan de inmediato, así que
     * ninguna de las dos aporta cartera real. Es una foto del saldo actual,
     * no se filtra por el periodo elegido en el panel.
     *
     * @return array{total_pending: float, total_overdue: float, pending_count: int, overdue_count: int, top_overdue: array<int, array<string, mixed>>}|null
     */
    private function buildReceivables(Company $company): ?array
    {
        $pending = $company->documentosEmitidos()
            ->where('payment_means_id', DocumentoEmitido::PAYMENT_MEANS_CREDIT)
            ->whereNull('paid_at')
            ->get();

        if ($pending->isEmpty()) {
            return null;
        }

        $now = now('America/Bogota');
        $overdue = $pending->filter(fn (DocumentoEmitido $d) => $d->due_date && $d->due_date->lt($now));

        $topOverdue = $overdue->sortBy('due_date')
            ->take(5)
            ->map(fn (DocumentoEmitido $d) => [
                'numeral' => $d->numeral,
                'client' => data_get($d->payload, 'accounting_customer_party.razon_social') ?: __('Unknown'),
                'total' => (float) $d->total,
                'due_date' => $d->due_date,
                'url' => route('documents.show', $d->_id),
            ])
            ->values()
            ->all();

        return [
            'total_pending' => (float) $pending->sum('total'),
            'total_overdue' => (float) $overdue->sum('total'),
            'pending_count' => $pending->count(),
            'overdue_count' => $overdue->count(),
            'top_overdue' => $topOverdue,
        ];
    }
}
