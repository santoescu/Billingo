<?php

namespace App\Services\Panel;

use App\Models\CashShift;
use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\DocumentoRecibido;
use App\Models\PaymentMeansCode;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Toda la lógica de consultas del Panel, una función por tarjeta/gráfica --
 * cada método consulta por su cuenta (nada de traer todo a una colección
 * compartida y calcular encima en PHP, como hacía el Panel antes de este
 * refactor). Se usa desde dos lados que deben mostrar exactamente lo mismo:
 * los widgets de Livewire (cada uno llama un solo método, de forma
 * independiente y perezosa) y DashboardController::panelPdf() (que sigue
 * siendo síncrono -- llama a todos los métodos seguidos para armar el PDF de
 * una sola pasada, ya que DomPDF no puede esperar a componentes async).
 *
 * Las consultas de conteo/suma usan count()/sum() de Mongo directamente
 * (sin traer documentos a PHP). Las que necesitan procesar líneas de
 * producto o payload (top productos/clientes, bodegas) sí traen documentos,
 * pero solo proyectando los campos que van a usar (nunca "xml"/"pdf"/etc),
 * para no cargar en memoria más de lo necesario.
 */
class PanelMetricsService
{
    public const PERIODS = ['today', 'week', 'month', 'last_month', 'year'];

    public const MODULES = ['invoicing', 'pos', 'cotizaciones', 'receiving'];

    /**
     * "period" de la query string (today/week/month/last_month/year); si
     * falta o no es válido, cae a "month". El periodo anterior de igual
     * largo se calcula para el % de cambio en cada métrica.
     *
     * @return array{key: string, from: \Carbon\Carbon, to: \Carbon\Carbon, prev_from: \Carbon\Carbon, prev_to: \Carbon\Carbon}
     */
    public function resolvePeriod(?string $key): array
    {
        if (! in_array($key, self::PERIODS, true)) {
            $key = 'month';
        }

        /**
         * Los datetime que vienen del driver de Mongo se hidratan en UTC sin
         * importar config('app.timezone') -- mismo patrón que el resto del
         * código, ver p. ej. IssueDocumentService o documents/show.blade.php,
         * que siempre convierten a America/Bogota antes de mostrar una fecha.
         * Sin esto, "hoy" empezaba a la medianoche UTC (7pm de ayer en
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

    public function dateColumn(string $module): string
    {
        return $module === 'receiving' ? 'issue_date' : 'created_at';
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Base de la relación de facturación, excluyendo las facturas que en
     * realidad nacieron de una venta POS convertida a electrónica (numeral
     * propio, ver IssueDocumentService::issuePosSaleElectronic()) -- sin
     * esto se contaría la misma venta real dos veces: una en POS y otra en
     * facturación.
     */
    private function invoicingQuery(Company $company)
    {
        $posOriginatedIds = $company->documentosPos()
            ->whereNotNull('documento_emitido_id')
            ->pluck('documento_emitido_id')
            ->filter()
            ->values()
            ->all();

        return $company->documentosEmitidos()->whereNotIn('_id', $posOriginatedIds);
    }

    private function baseQuery(Company $company, string $module)
    {
        return match ($module) {
            'invoicing' => $this->invoicingQuery($company),
            'pos' => $company->documentosPos(),
            'cotizaciones' => $company->quotations(),
            'receiving' => $company->documentosRecibidos(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(Company $company, string $module, array $period): array
    {
        return match ($module) {
            'invoicing' => $this->invoicingMetrics($company, $period),
            'pos' => $this->posMetrics($company, $period),
            'cotizaciones' => $this->cotizacionesMetrics($company, $period),
            'receiving' => $this->receivingMetrics($company, $period),
        };
    }

    private function invoicingMetrics(Company $company, array $period): array
    {
        $today = (clone $this->invoicingQuery($company))->where('created_at', '>=', now('America/Bogota')->startOfDay());
        $periodQuery = (clone $this->invoicingQuery($company))->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousTotal = (float) (clone $this->invoicingQuery($company))->whereBetween('created_at', [$period['prev_from'], $period['prev_to']])->sum('total');
        $periodTotal = (float) (clone $periodQuery)->sum('total');

        return [
            'today_count' => $today->count(),
            'today_total' => (float) (clone $today)->sum('total'),
            'period_count' => (clone $periodQuery)->count(),
            'period_total' => $periodTotal,
            'period_change_pct' => $this->percentChange($previousTotal, $periodTotal),
            'period_issues' => (clone $periodQuery)->whereIn('status', [DocumentoEmitido::STATUS_REJECTED, DocumentoEmitido::STATUS_ERROR])->count(),
        ];
    }

    private function posMetrics(Company $company, array $period): array
    {
        $today = $company->documentosPos()->where('created_at', '>=', now('America/Bogota')->startOfDay());
        $periodQuery = $company->documentosPos()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousTotal = (float) $company->documentosPos()->whereBetween('created_at', [$period['prev_from'], $period['prev_to']])->sum('total');
        $periodTotal = (float) (clone $periodQuery)->sum('total');

        return [
            'open_shifts' => CashShift::where('company_id', (string) $company->_id)->open()->count(),
            'today_count' => $today->count(),
            'today_total' => (float) (clone $today)->sum('total'),
            'period_count' => (clone $periodQuery)->count(),
            'period_total' => $periodTotal,
            'period_change_pct' => $this->percentChange($previousTotal, $periodTotal),
        ];
    }

    private function cotizacionesMetrics(Company $company, array $period): array
    {
        $periodQuery = $company->quotations()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousTotal = (float) $company->quotations()->whereBetween('created_at', [$period['prev_from'], $period['prev_to']])->sum('total');
        $periodTotal = (float) (clone $periodQuery)->sum('total');

        return [
            'pending_count' => $company->quotations()->whereNull('documento_pos_id')->whereNull('documento_emitido_id')->count(),
            'converted_period_count' => (clone $periodQuery)->where(function ($q) {
                $q->whereNotNull('documento_pos_id')->orWhereNotNull('documento_emitido_id');
            })->count(),
            'period_total' => $periodTotal,
            'period_change_pct' => $this->percentChange($previousTotal, $periodTotal),
        ];
    }

    private function receivingMetrics(Company $company, array $period): array
    {
        $today = $company->documentosRecibidos()->where('issue_date', '>=', now('America/Bogota')->startOfDay());
        $periodQuery = $company->documentosRecibidos()->whereBetween('issue_date', [$period['from'], $period['to']]);
        $previousTotal = (float) $company->documentosRecibidos()->whereBetween('issue_date', [$period['prev_from'], $period['prev_to']])->sum('total');
        $periodTotal = (float) (clone $periodQuery)->sum('total');

        return [
            'today_count' => $today->count(),
            'today_total' => (float) (clone $today)->sum('total'),
            'period_count' => (clone $periodQuery)->count(),
            'period_total' => $periodTotal,
            'period_change_pct' => $this->percentChange($previousTotal, $periodTotal),
            'pending_review_count' => $company->documentosRecibidos()->where('status', DocumentoRecibido::STATUS_PENDING)->count(),
        ];
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
     * @return array{revenue: float, cogs: float, profit: float, margin_pct: float, change_pct: ?float}|null
     */
    public function utility(Company $company, array $period, bool $hasInvoicing, bool $hasPos): ?array
    {
        if (! $hasInvoicing && ! $hasPos) {
            return null;
        }

        $periodProfit = $this->profitFor($company, $period['from'], $period['to'], $hasInvoicing, $hasPos);
        $previousProfit = $this->profitFor($company, $period['prev_from'], $period['prev_to'], $hasInvoicing, $hasPos);

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
    private function profitFor(Company $company, \Carbon\Carbon $from, \Carbon\Carbon $to, bool $hasInvoicing, bool $hasPos): array
    {
        $revenue = 0.0;
        $numerals = [];

        if ($hasInvoicing) {
            $docs = (clone $this->invoicingQuery($company))->whereBetween('created_at', [$from, $to])->select(['numeral', 'subtotal'])->get();
            $revenue += (float) $docs->sum('subtotal');
            $numerals = array_merge($numerals, $docs->pluck('numeral')->all());
        }

        if ($hasPos) {
            $docs = $company->documentosPos()->whereBetween('created_at', [$from, $to])->select(['numeral', 'subtotal'])->get();
            $revenue += (float) $docs->sum('subtotal');
            $numerals = array_merge($numerals, $docs->pluck('numeral')->all());
        }

        $reasons = array_map(fn ($numeral) => 'document:' . $numeral, $numerals);
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
     * Sigue el periodo elegido (no una ventana fija): por hora si es "hoy",
     * por día si es "esta semana"/"este mes", por mes si es "este año".
     * $modules son los módulos que el usuario ve en este momento (ya
     * filtrados por rol + filtro de módulo elegido en la barra).
     *
     * @param  array<int, string>  $modules
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    public function trend(Company $company, array $period, array $modules): array
    {
        $buckets = $this->trendBuckets($period);

        $series = [];
        foreach ($modules as $module) {
            $dateColumn = $this->dateColumn($module);
            $docs = $this->baseQuery($company, $module)
                ->whereBetween($dateColumn, [$period['from'], $period['to']])
                ->select([$dateColumn, 'total'])
                ->get();

            $series[$module] = collect($buckets)
                ->map(fn (array $bucket) => (float) $docs
                    ->filter(fn ($doc) => $doc->{$dateColumn} >= $bucket['start'] && $doc->{$dateColumn} <= $bucket['end'])
                    ->sum('total'))
                ->all();
        }

        return [
            'labels' => collect($buckets)->pluck('label')->all(),
            'series' => $series,
        ];
    }

    /**
     * @return array<int, array{start: \Carbon\Carbon, end: \Carbon\Carbon, label: string}>
     */
    private function trendBuckets(array $period): array
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

                $buckets[] = ['start' => $start, 'end' => $start->copy()->addHour()->subSecond(), 'label' => $start->format('H:00')];
            }
        } elseif ($period['key'] === 'year') {
            $cursor = $from->copy()->startOfMonth();
            while ($cursor->lte($to)) {
                $buckets[] = ['start' => $cursor->copy(), 'end' => $cursor->copy()->endOfMonth(), 'label' => $cursor->translatedFormat('M')];
                $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
            }
        } else {
            $cursor = $from->copy()->startOfDay();
            while ($cursor->lte($to)) {
                $buckets[] = ['start' => $cursor->copy(), 'end' => $cursor->copy()->endOfDay(), 'label' => $cursor->format('d/m')];
                $cursor = $cursor->addDay();
            }
        }

        return $buckets;
    }

    /**
     * Distribución de ingresos entre módulos en el periodo elegido -- solo
     * tiene sentido si 2 o más módulos tienen ingresos, si no es redundante
     * con la tarjeta de esa única métrica.
     *
     * @param  array<int, string>  $modules
     * @return array{modules: array<int, string>, labels: array<int, string>, values: array<int, float>}|null
     */
    public function moduleDistribution(Company $company, array $period, array $modules): ?array
    {
        $result = ['modules' => [], 'labels' => [], 'values' => []];

        foreach (array_intersect($modules, ['invoicing', 'pos', 'cotizaciones']) as $module) {
            $total = (float) $this->baseQuery($company, $module)->whereBetween('created_at', [$period['from'], $period['to']])->sum('total');
            if ($total <= 0) {
                continue;
            }

            $result['modules'][] = $module;
            $result['labels'][] = config("modules.$module.name");
            $result['values'][] = $total;
        }

        return count($result['values']) >= 2 ? $result : null;
    }

    /**
     * Cuántos documentos de facturación quedaron en cada estado DIAN en el
     * periodo elegido.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}|null
     */
    public function invoiceStatusBreakdown(Company $company, array $period): ?array
    {
        $docs = (clone $this->invoicingQuery($company))
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->select(['status'])
            ->get();

        if ($docs->isEmpty()) {
            return null;
        }

        $counts = $docs->countBy(fn ($doc) => $doc->status_label);

        return ['labels' => $counts->keys()->all(), 'values' => $counts->values()->all()];
    }

    /**
     * Ventas por bodega en el periodo elegido, cruzando todos los módulos
     * visibles -- solo se calcula si la empresa tiene 2 o más bodegas.
     *
     * @param  array<int, string>  $modules
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    public function warehouseComparison(Company $company, array $period, array $modules): ?array
    {
        $warehouses = $company->warehouses()->orderBy('name')->get();
        if ($warehouses->count() < 2) {
            return null;
        }

        $lines = collect();
        foreach (array_intersect($modules, ['invoicing', 'pos', 'cotizaciones']) as $module) {
            $docs = $this->baseQuery($company, $module)->whereBetween('created_at', [$period['from'], $period['to']])->select(['payload'])->get();
            $lines = $lines->merge($docs->flatMap(fn ($doc) => $doc->payload['lineas'] ?? []));
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
     * Ventas por cajero en el periodo elegido (solo POS). Solo se muestra si
     * hubo ventas de 2 o más cajeros distintos.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    public function cashierComparison(Company $company, array $period): ?array
    {
        $sales = $company->documentosPos()->whereBetween('created_at', [$period['from'], $period['to']])->select(['shift_id', 'total'])->get();
        if ($sales->isEmpty()) {
            return null;
        }

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
     * Ventas por vendedor -- a diferencia de los demás comparativos, siempre
     * muestra a todos los vendedores con ventas en el periodo, aunque haya
     * uno solo.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    public function sellerComparison(Company $company, array $period): ?array
    {
        $sales = $company->documentosPos()->whereBetween('created_at', [$period['from'], $period['to']])->select(['seller_id', 'seller_name', 'total'])->get();

        $totals = $sales
            ->filter(fn ($sale) => $sale->seller_id)
            ->groupBy('seller_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->seller_name ?: __('Unknown'),
                'total' => (float) $group->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();

        if ($totals->isEmpty()) {
            return null;
        }

        return [
            'labels' => $totals->pluck('name')->all(),
            'values' => $totals->pluck('total')->all(),
        ];
    }

    /**
     * Cuánto se pagó por cada medio de pago en facturación en el periodo
     * elegido.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    public function paymentMethodsInvoicing(Company $company, array $period): ?array
    {
        $docs = (clone $this->invoicingQuery($company))
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->whereNotNull('payment_means_code')
            ->select(['payment_means_code', 'total'])
            ->get();

        if ($docs->isEmpty()) {
            return null;
        }

        $codes = PaymentMeansCode::all()->keyBy('codigo');
        $totals = collect();

        foreach ($docs as $doc) {
            $label = $codes->get($doc->payment_means_code)?->medio ?? $doc->payment_means_code;
            $totals[$label] = ($totals[$label] ?? 0) + (float) $doc->total;
        }

        return $this->sortedLabelsAndValues($totals);
    }

    /**
     * Cuánto se pagó por cada medio de pago en POS en el periodo elegido --
     * una venta puede haberse pagado con varios medios a la vez (repartiendo
     * el total entre ellos), ver "payments" en DocumentoPos.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}|null
     */
    public function paymentMethodsPos(Company $company, array $period): ?array
    {
        $docs = $company->documentosPos()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->select(['payments', 'payment_method_name', 'total'])
            ->get();

        $totals = collect();

        foreach ($docs as $doc) {
            $payments = $doc->payments ?: ($doc->payment_method_name ? [[
                'payment_method_name' => $doc->payment_method_name,
                'amount' => $doc->total,
            ]] : []);

            foreach ($payments as $payment) {
                $label = $payment['payment_method_name'] ?? __('Unknown');
                $totals[$label] = ($totals[$label] ?? 0) + (float) ($payment['amount'] ?? 0);
            }
        }

        return $this->sortedLabelsAndValues($totals);
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
     * Productos más vendidos/facturados en el periodo elegido, de UN solo
     * módulo a la vez.
     *
     * @return array<int, array{codigo: ?string, descripcion: string, cantidad: float, total: float}>
     */
    public function topProducts(Company $company, string $module, array $period): array
    {
        $dateColumn = $this->dateColumn($module);
        $docs = $this->baseQuery($company, $module)->whereBetween($dateColumn, [$period['from'], $period['to']])->select(['payload'])->get();
        $lines = $docs->flatMap(fn ($doc) => $doc->payload['lineas'] ?? []);

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
     * la vez. Null si no hay al menos 2 clientes distintos con compras.
     *
     * @return array<int, array{name: string, total: float}>|null
     */
    public function topClients(Company $company, string $module, array $period): ?array
    {
        $dateColumn = $this->dateColumn($module);
        $docs = $this->baseQuery($company, $module)
            ->whereBetween($dateColumn, [$period['from'], $period['to']])
            ->select(['cliente_id', 'payload', 'total', 'created_at'])
            ->get();

        if ($docs->isEmpty()) {
            return null;
        }

        $totals = $docs
            ->groupBy(fn ($doc) => $doc->cliente_id ?: 'sin-cliente')
            ->map(function (Collection $group) {
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
     * Proveedores a los que más se les compró en el periodo elegido -- a
     * diferencia de topClients(), DocumentoRecibido no guarda el nombre del
     * proveedor en su payload (solo "proveedor_id"), así que el nombre se
     * resuelve con una consulta aparte a ThirdParty, igual que en
     * payables(). Null si no hay al menos 2 proveedores distintos.
     *
     * @return array<int, array{name: string, total: float}>|null
     */
    public function topSuppliers(Company $company, array $period): ?array
    {
        $docs = $company->documentosRecibidos()
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->select(['proveedor_id', 'total'])
            ->get();

        if ($docs->isEmpty()) {
            return null;
        }

        $totals = $docs
            ->groupBy(fn ($doc) => $doc->proveedor_id ?: 'sin-proveedor')
            ->map(fn (Collection $group) => (float) $group->sum('total'));

        if ($totals->count() < 2) {
            return null;
        }

        $namesById = ThirdParty::whereIn('_id', $totals->keys()->filter()->all())->get()
            ->mapWithKeys(fn (ThirdParty $t) => [(string) $t->_id => $t->name]);

        $totals = $totals->sortByDesc(fn ($total) => $total)->take(8);

        return $totals->map(fn ($total, $id) => [
            'name' => $namesById->get($id, __('Unknown')),
            'total' => $total,
        ])->values()->all();
    }

    /**
     * Documentos más recientes de UN módulo, ya recortados a 8 en la propia
     * consulta -- el widget de actividad reciente llama esto por cada módulo
     * visible y mezcla los resultados (máximo 8 * módulos visibles antes de
     * recortar a 8 de nuevo).
     *
     * @return array<int, array{type: string, label: string, title: string, total: float, created_at: \Carbon\Carbon, url: string}>
     */
    public function recentActivityForModule(Company $company, string $module, array $period): array
    {
        $dateColumn = $this->dateColumn($module);
        $docs = $this->baseQuery($company, $module)
            ->whereBetween($dateColumn, [$period['from'], $period['to']])
            ->orderBy($dateColumn, 'desc')
            ->limit(8)
            ->select(['numeral', 'total', $dateColumn])
            ->get();

        [$label, $routeName] = match ($module) {
            'invoicing' => [__('Invoice'), 'documents.show'],
            'pos' => [__('POS sale'), 'pos.sales.show'],
            'cotizaciones' => [__('Quotation'), 'quotations.show'],
            'receiving' => [__('Received document'), 'received-documents.show'],
        };

        return $docs->map(fn ($d) => [
            'type' => $module,
            'label' => $label,
            'title' => $d->numeral,
            'total' => (float) $d->total,
            'created_at' => $d->{$dateColumn},
            'url' => route($routeName, $d->_id),
        ])->all();
    }

    /**
     * Productos con inventario controlado y stock por debajo del umbral
     * fijo -- visible para cualquier módulo administrado.
     *
     * @return array<int, Product>
     */
    public function lowStockProducts(Company $company): array
    {
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
     * crédito que aún no se han marcado como pagadas. Foto del saldo
     * actual, no se filtra por el periodo elegido en el panel.
     *
     * @return array{total_pending: float, total_overdue: float, pending_count: int, overdue_count: int, top_overdue: array<int, array<string, mixed>>}|null
     */
    public function receivables(Company $company): ?array
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

    /**
     * Cuentas por pagar -- espejo de receivables(), del lado de recepción.
     *
     * @return array{total_pending: float, total_overdue: float, pending_count: int, overdue_count: int, top_overdue: array<int, array<string, mixed>>}|null
     */
    public function payables(Company $company): ?array
    {
        $pending = $company->documentosRecibidos()
            ->where('payment_means_id', DocumentoRecibido::PAYMENT_MEANS_CREDIT)
            ->whereNull('paid_at')
            ->get();

        if ($pending->isEmpty()) {
            return null;
        }

        $now = now('America/Bogota');
        $overdue = $pending->filter(fn (DocumentoRecibido $d) => $d->due_date && $d->due_date->lt($now));

        $proveedorIds = $overdue->pluck('proveedor_id')->filter()->unique()->all();
        $proveedorNames = ThirdParty::whereIn('_id', $proveedorIds)->get()->keyBy(fn (ThirdParty $t) => (string) $t->_id);

        $topOverdue = $overdue->sortBy('due_date')
            ->take(5)
            ->map(fn (DocumentoRecibido $d) => [
                'numeral' => $d->numeral,
                'supplier' => $proveedorNames->get((string) $d->proveedor_id)?->name ?: __('Unknown'),
                'total' => (float) $d->total,
                'due_date' => $d->due_date,
                'url' => route('received-documents.show', $d->_id),
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
