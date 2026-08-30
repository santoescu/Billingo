<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentoEmitido;
use App\Models\DocumentoPos;
use App\Models\FiscalResponsibility;
use App\Models\PaymentMeansCode;
use App\Models\Product;
use App\Models\User;
use App\Services\Dian\IssueDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PosController extends Controller
{
    /**
     * Pantalla de venta del POS: redirige a abrir turno si no hay uno
     * abierto. El listado inicial de productos usa el mismo filtro por
     * defecto que la búsqueda AJAX con "Todas las bodegas" (ver
     * DocumentoEmitidoController::productSearch()): más de 1 unidad en
     * total, para no listar productos sin existencias reales; y el mismo
     * shape que esa búsqueda (precios por tipo, stock por bodega), para que
     * el tooltip de inventario/precio del grid no salga vacío hasta la
     * primera búsqueda.
     *
     * Si viene "from_quotation" en la URL (al convertir una cotización
     * pendiente en venta, ver QuotationController::show()), precarga el
     * cliente y las líneas de esa cotización en la primera pre-cuenta (ver
     * "initialQuotation*" en pos/sell.blade.php); el "quotation_id" queda en
     * la vista para que el checkout enlace la cotización con la venta que
     * resulte (ver checkout() más abajo).
     *
     * Si viene "edit_sale" en la URL (botón "Editar" de pos/sales/show.blade.php),
     * precarga esta misma pantalla con el cliente y las líneas de esa venta
     * ya guardada, para corregirlas como si fuera una venta nueva -- solo un
     * administrador puede entrar en este modo, y solo si la venta todavía no
     * se facturó electrónicamente (ver DocumentoPos::is_electronic).
     *
     * @param  Request  $request  Petición actual (empresa activa en sesión).
     * @param  DocumentoEmitidoController  $documentController  Provee mapProductsForJs() para reusar el mismo mapeo que el AJAX de búsqueda.
     * @param  QuotationController  $quotationController  Resuelve y traduce la cotización a precargar, si aplica.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Vista pos.sell, o redirect a pos.shift si no hay turno abierto.
     */
    public function create(Request $request, DocumentoEmitidoController $documentController, QuotationController $quotationController)
    {
        $company = $this->currentCompany($request);

        $shift = $this->openShiftFor($company, $request);

        if (! $shift) {
            return redirect()->route('pos.shift');
        }

        $paymentMethods = $company->paymentMethods()->orderBy('name')->get();
        $sellers = $company->sellers()->orderBy('name')->get();

        $products = $company->products()->active()
            ->where(fn ($builder) => $builder->where('stock', '>', 1)->orWhere('tracks_inventory', '!=', true))
            ->orderBy('description')->limit(60)->get();

        $quotation = $quotationController->resolveFromQuotation($company, $request->query('from_quotation'));
        $quotationPrefill = $quotation ? $quotationController->mapQuotationLinesForJs($company, $quotation, $documentController) : null;

        $editSale = null;
        $editSalePrefill = null;

        if ($request->query('edit_sale')) {
            abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

            $editSale = $company->documentosPos()->where('_id', $request->query('edit_sale'))->first();
            abort_unless($editSale, 404);
            abort_if($editSale->is_electronic, 403, __('This sale was already issued as an electronic invoice; it cannot be edited.'));

            $editSalePrefill = $this->mapPosSaleLinesForJs($company, $editSale, $documentController);
        }

        $canEditPrice = User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []);

        return view('pos.sell', [
            'shift' => $shift,
            'company' => $company,
            'paymentMethods' => $paymentMethods,
            'sellers' => $sellers,
            'products' => $documentController->mapProductsForJs($products),
            'warehouses' => $company->warehouses()->orderBy('name')->get(),
            'priceTypes' => $company->priceTypes()->orderBy('name')->get(),
            'defaultClient' => $this->defaultClient($company),
            'departments' => Department::orderBy('descripcion')->get(),
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
            'quotationId' => $quotation ? (string) $quotation->_id : null,
            'quotationPrefill' => $quotationPrefill,
            'editSaleId' => $editSale ? (string) $editSale->_id : null,
            'editSalePrefill' => $editSalePrefill,
            'canEditPrice' => $canEditPrice,
            'isAdmin' => $canEditPrice,
        ]);
    }

    /**
     * Traduce una venta POS ya guardada al mismo shape que
     * QuotationController::mapQuotationLinesForJs() usa para precargar el
     * POS desde una cotización -- incluido aquí (y no ahí) porque lee de
     * DocumentoPos, no de Quotation, aunque el payload de líneas/cliente
     * tiene la misma forma en los dos modelos.
     *
     * @param  Company  $company  Empresa activa, para resolver los productos actuales por código.
     * @param  DocumentoPos  $documento  Venta cuyas líneas/cliente se van a precargar.
     * @param  DocumentoEmitidoController  $documentController  Provee mapProductsForJs() para reusar el mismo mapeo que el AJAX de búsqueda.
     * @return array{client: array, lines: array} Cliente y líneas listos para inyectar en la pre-cuenta del POS.
     */
    private function mapPosSaleLinesForJs(Company $company, DocumentoPos $documento, DocumentoEmitidoController $documentController): array
    {
        $customerParty = $documento->payload['accounting_customer_party'] ?? [];

        $client = [
            'id' => $documento->cliente_id,
            'identification_type' => $customerParty['tipo_identificacion'] ?? '13',
            'identificacion' => $customerParty['identificacion'] ?? '',
            'name' => $customerParty['razon_social'] ?? '',
            'person_type' => $customerParty['tipo_persona'] ?? '2',
            'fiscal_responsibilities' => $customerParty['responsabilidades_fiscales'] ?? null,
            'address' => $customerParty['direccion'] ?? null,
            'department_code' => $customerParty['departamento_codigo'] ?? null,
            'city_code' => $customerParty['ciudad_codigo'] ?? null,
            'phone' => $customerParty['telefono'] ?? null,
            'email' => $customerParty['email'] ?? null,
        ];

        $lineas = $documento->payload['lineas'] ?? [];
        $codes = collect($lineas)->pluck('codigo')->filter()->values()->all();
        $productsByCode = Product::where('company_id', (string) $company->_id)->whereIn('code', $codes)->get()->keyBy('code');

        $lines = collect($lineas)->map(function (array $linea) use ($productsByCode, $documentController) {
            $product = $productsByCode->get($linea['codigo'] ?? null);

            $productJs = $product
                ? $documentController->mapProductsForJs(collect([$product]))->first()
                : [
                    'id' => null,
                    'code' => $linea['codigo'] ?? '',
                    'barcode' => null,
                    'description' => $linea['descripcion'] ?? '',
                    'unit_code' => 'EA',
                    'unit_price' => (float) ($linea['precio_unitario'] ?? 0),
                    'tracks_inventory' => false,
                    'stock' => 0,
                    'prices' => [],
                    'warehouses' => [],
                    'image_url' => null,
                ];

            /**
             * La cantidad de esta línea todavía está "reservada" por la
             * propia venta (su inventario recién se devuelve/vuelve a
             * descontar cuando se guarda la edición, ver
             * IssueDocumentService::updatePosSaleLines()) -- si no se le
             * suma de vuelta acá, el stock actual de esa bodega puede
             * aparecer en 0 (ya descontado por esta misma venta) y el
             * stepper de cantidad del POS se rompe con un tope inválido
             * (máximo 0, mínimo 1).
             */
            if ($product && $product->tracks_inventory && ! empty($linea['bodega_id'])) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $productJs['stock'] += $cantidad;
                $productJs['warehouses'] = collect($productJs['warehouses'])->map(function (array $warehouse) use ($linea, $cantidad) {
                    if ($warehouse['warehouse_id'] === $linea['bodega_id']) {
                        $warehouse['stock'] += $cantidad;
                    }

                    return $warehouse;
                })->values()->all();
            }

            return [
                'product' => $productJs,
                'qty' => (float) ($linea['cantidad'] ?? 1),
                'warehouse_id' => $linea['bodega_id'] ?? null,
                'unit_price' => (float) ($linea['precio_unitario'] ?? 0),
            ];
        })->values()->all();

        return ['client' => $client, 'lines' => $lines, 'seller_id' => $documento->seller_id];
    }

    /**
     * Pestaña "Caja": el turno propio (para abrirlo si no hay uno, o verlo y
     * cerrarlo si ya está abierto) y, solo para el owner o un administrador
     * del módulo POS, la lista de TODAS las cajas abiertas de la empresa en
     * este momento -- un cajero cualquiera no ve las cajas de los demás.
     */
    public function shift(Request $request, DocumentoEmitidoController $documentController)
    {
        $company = $this->currentCompany($request);

        $shift = $this->openShiftFor($company, $request);
        $isAdmin = User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []);

        $fvResolutions = $documentController->resolutionsFor($company, 'FV');
        $invoicingResolutions = $company->hasModule('invoicing')
            ? $documentController->resolutionsFor($company, '01')
            : collect();

        if ($isAdmin) {
            $openShifts = CashShift::where('company_id', (string) $company->_id)
                ->open()
                ->orderBy('opened_at')
                ->get()
                ->map(fn (CashShift $s) => $this->shiftSummary($s));
        } else {
            $openShifts = $shift ? collect([$this->shiftSummary($shift)]) : collect();
        }

        return view('pos.shift', compact('shift', 'isAdmin', 'fvResolutions', 'invoicingResolutions', 'openShifts'));
    }

    /**
     * Saldo esperado en vivo y total vendido durante el turno -- calculado a
     * partir de sus CashMovement (mismo cálculo que ya usa
     * CashShiftController::close()/show()), para no duplicar la lógica de
     * "cuánto debería haber en caja ahora mismo" en dos lados.
     */
    private function shiftSummary(CashShift $shift): array
    {
        $movements = CashMovement::where('shift_id', (string) $shift->_id)->get();

        return [
            'shift' => $shift,
            'expected_balance' => $shift->opening_balance + $movements->sum(fn (CashMovement $m) => $m->signedAmount()),
            'sales_total' => (float) $movements->where('type', CashMovement::TYPE_VENTA)->sum('amount'),
            'sales_count' => $movements->where('type', CashMovement::TYPE_VENTA)->count(),
        ];
    }

    /**
     * Emite la venta (SIEMPRE como factura de venta, nunca electrónica de
     * una vez -- ver DocumentoEmitidoController::issuePosSale()) y devuelve
     * JSON: el formulario del POS lo manda por fetch (no un submit normal),
     * para poder abrir el modal de resultado con la vista previa del recibo
     * sin salir de la pantalla de venta. "Emitir factura electrónica" es una
     * acción aparte desde ese modal (ver issueElectronic() más abajo).
     */
    public function checkout(Request $request, DocumentoEmitidoController $documentController, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        $shift = $this->openShiftFor($company, $request);

        if (! $shift) {
            return response()->json(['message' => __('You need to open a cash shift before selling.')], 422);
        }

        $cashReceived = $request->validate([
            'efectivo_recibido' => ['nullable', 'numeric', 'min:0'],
        ])['efectivo_recibido'] ?? null;

        try {
            $documentoPos = $documentController->issuePosSale($request, $company, $shift, $service);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not issue the document.')], 500);
        }

        CashMovement::create([
            'company_id' => (string) $company->_id,
            'shift_id' => (string) $shift->_id,
            'type' => CashMovement::TYPE_VENTA,
            'amount' => $documentoPos->total,
            'reason' => 'document:' . $documentoPos->numeral,
            'document_id' => (string) $documentoPos->_id,
            'payment_means_code' => $documentoPos->payment_means_code,
            'user_id' => (string) $request->user()->_id,
        ]);

        if ($cashReceived !== null) {

            session()->put('pos_cash_received.' . $documentoPos->_id, $cashReceived);
        }

        $quotationId = $request->input('quotation_id');
        if ($quotationId) {
            $company->quotations()->where('_id', $quotationId)->update(['documento_pos_id' => (string) $documentoPos->_id]);
        }

        return response()->json([
            'sale_id' => (string) $documentoPos->_id,
            'numeral' => $documentoPos->numeral,
            'total_formatted' => $documentoPos->total_formatted,
            'can_issue_electronic' => (bool) $shift->invoicing_resolution_id && $company->hasModule('invoicing'),
            'receipt_url' => route('pos.sales.receipt-pdf', $documentoPos->_id),
            'receipt_preview_url' => route('pos.sales.receipt-preview', $documentoPos->_id),
            'show_url' => route('pos.sales.show', $documentoPos->_id),
            'issue_electronic_url' => route('pos.sales.issue-electronic', $documentoPos->_id),
        ]);
    }

    /**
     * Emite la factura electrónica de una venta del POS ya creada (acción
     * del modal de resultado del checkout, ver "Emitir factura electrónica")
     * -- reusa la resolución de facturación electrónica del turno bajo el
     * que se creó esa venta, no la del turno actual del usuario que hace
     * clic (puede ser un administrador viéndola después).
     */
    public function issueElectronic(Request $request, string $sale, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        $documentoPos = $company->documentosPos()->where('_id', $sale)->first();
        abort_unless($documentoPos, 404);

        if ($documentoPos->documento_emitido_id) {
            return response()->json(['message' => __('This sale was already issued as an electronic invoice.')], 422);
        }

        if (! $company->hasModule('invoicing')) {
            return response()->json(['message' => __('This company does not have the electronic invoicing module enabled.')], 422);
        }

        $shift = $documentoPos->shift;
        if (! $shift || ! $shift->invoicingResolution) {
            return response()->json(['message' => __('There is no electronic invoice resolution available for this sale.')], 422);
        }

        if (empty($documentoPos->payment_means_code)) {
            return response()->json([
                'message' => __('The payment method ":name" has no DIAN equivalent mapped, so it cannot be used for an electronic invoice.', [
                    'name' => $documentoPos->payment_method_name ?? '—',
                ]),
            ], 422);
        }

        $cliente = $documentoPos->payload['accounting_customer_party'] ?? [];
        if (empty($cliente['direccion']) || empty($cliente['ciudad_codigo']) || empty($cliente['departamento_codigo'])) {
            return response()->json([
                'message' => __('The client ":name" is missing address/city/department data, required for an electronic invoice. Complete it from the Clients screen and try again.', [
                    'name' => $documentoPos->payload['accounting_customer_party']['razon_social'] ?? '—',
                ]),
            ], 422);
        }

        try {
            $documentoElectronico = $service->issuePosSaleElectronic($company, $documentoPos, $shift->invoicingResolution);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not issue the electronic invoice.')], 500);
        }

        $documentoPos->update(['documento_emitido_id' => (string) $documentoElectronico->_id]);

        return response()->json([
            'accepted' => $documentoElectronico->status === DocumentoEmitido::STATUS_ACCEPTED,
            'document_url' => route('documents.show', $documentoElectronico->_id),
        ]);
    }

    /**
     * Pantalla para editar los productos/cantidades de una venta ya guardada -- solo mientras
     * siga siendo talonario interno (! documento_emitido_id). Una vez facturada
     * electrónicamente, la DIAN ya la aceptó y corregirla requiere una Nota Crédito, no una
     * edición; por eso ni se muestra el botón ni existe esta pantalla para esos casos.
     */
    /**
     * Guarda la edición de líneas de una venta -- mismo patrón de manejo de errores que
     * checkout(). Si el total cambió, el CashMovement de esa venta se actualiza también, para
     * que el arqueo de caja siga cuadrando con lo que de verdad quedó vendido. Solo un
     * administrador puede editar (mismo control que create() al entrar en modo edición).
     */
    public function updateSale(Request $request, string $sale, DocumentoEmitidoController $documentController, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

        $documento = $company->documentosPos()->where('_id', $sale)->first();

        if (! $documento) {
            return response()->json(['message' => __('Sale not found.')], 404);
        }

        try {
            $documento = $documentController->updatePosSaleItems($request, $company, $documento, $service);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not update the sale.')], 500);
        }

        CashMovement::where('document_id', (string) $documento->_id)
            ->where('type', CashMovement::TYPE_VENTA)
            ->update(['amount' => $documento->total]);

        return response()->json([
            'sale_id' => (string) $documento->_id,
            'numeral' => $documento->numeral,
            'total_formatted' => $documento->total_formatted,
            'show_url' => route('pos.sales.show', $documento->_id),
        ]);
    }

    /**
     * Lista las ventas del POS (talonario o electrónicas) de la empresa
     * activa -- colección separada de "documentos_emitidos" (esa es solo
     * documentos electrónicos reales), para poder ver de un vistazo cuánto
     * se ha vendido por el POS sin importar si terminó facturado
     * electrónicamente o no.
     */
    public function sales(Request $request)
    {
        $company = $this->currentCompany($request);

        $documentos = $company->documentosPos()
            ->orderByDesc('created_at')
            ->get();

        return view('pos.sales.index', compact('company', 'documentos'));
    }

    public function showSale(Request $request, string $sale)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosPos()->where('_id', $sale)->first();

        abort_unless($documento, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        /**
         * Mismas condiciones que issueElectronic() (menos las de datos del
         * cliente, esas las valida el propio endpoint al emitir) -- para no
         * mostrar el botón si de entrada no va a poder emitirse, en vez de
         * dejar que el usuario le dé clic y se entere ahí del error.
         */
        $canIssueElectronic = ! $documento->documento_emitido_id
            && $company->hasModule('invoicing')
            && $documento->shift?->invoicingResolution
            && ! empty($documento->payment_means_code);

        $isAdmin = User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []);

        $warehouseNames = $company->warehouses()->get()->mapWithKeys(fn ($warehouse) => [(string) $warehouse->_id => $warehouse->name]);

        return view('pos.sales.show', compact('company', 'documento', 'paymentMeansCode', 'canIssueElectronic', 'isAdmin', 'warehouseNames'));
    }

    /**
     * Descarga el recibo de la venta (dispara la descarga del archivo). Ver
     * receiptPreview() para la variante embebida en el navegador, con su
     * propia URL en vez de "?inline=1" (mismo query string para ambos casos
     * se veía feo en la barra de direcciones al abrir el link en pestaña
     * nueva).
     */
    public function receiptPdf(Request $request, string $sale)
    {
        [$pdf, $filename] = $this->buildReceiptPdf($request, $sale);

        return $pdf->download($filename);
    }

    /**
     * Igual que receiptPdf(), pero para mostrarlo embebido (iframe de vista
     * previa del modal de resultado, o "Ver PDF" en pestaña nueva) en vez de
     * descargarlo.
     */
    public function receiptPreview(Request $request, string $sale)
    {
        [$pdf, $filename] = $this->buildReceiptPdf($request, $sale);

        return $pdf->stream($filename);
    }

    /**
     * Arma el recibo de la venta, compartido entre receiptPdf() (descarga) y
     * receiptPreview() (embebido).
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string} PDF armado y nombre de archivo sugerido.
     */
    private function buildReceiptPdf(Request $request, string $sale): array
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosPos()->where('_id', $sale)->first();

        abort_unless($documento, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        $cashReceived = session('pos_cash_received.' . $documento->_id);
        $isElectronic = (bool) $documento->documento_emitido_id;
        $uuid = $documento->documentoEmitido?->uuid;

        $pdf = Pdf::loadView('documents.receipt-pdf', compact(
            'company',
            'documento',
            'paymentMeansCode',
            'cashReceived',
            'isElectronic',
            'uuid',
        ))->setPaper([0, 0, 226.77, 800], 'portrait');

        return [$pdf, 'recibo-' . $documento->numeral . '.pdf'];
    }

    private function openShiftFor($company, Request $request): ?CashShift
    {
        return CashShift::where('company_id', (string) $company->_id)
            ->where('user_id', (string) $request->user()->_id)
            ->open()
            ->first();
    }
}
