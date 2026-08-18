<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Services\Dian\IssueDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class QuotationController extends Controller
{
    /**
     * Pantalla de creación de cotizaciones: mismo grid de productos y panel
     * de cliente que el POS, pero sin turno de caja ni medios de pago (ver
     * resources/views/quotations/create.blade.php). Si la empresa no tiene
     * ninguna Resolution activa tipo 'COT', igual se muestra la pantalla
     * (a diferencia del POS, que redirige a abrir turno) para que el
     * usuario vea el aviso y sepa que debe crear la resolución antes de
     * poder emitir.
     *
     * @param  Request  $request  Petición actual (empresa activa en sesión).
     * @param  DocumentoEmitidoController  $documentController  Provee mapProductsForJs()/resolutionsFor() para reusar el mismo mapeo que documentos/POS.
     * @return \Illuminate\View\View
     */
    public function create(Request $request, DocumentoEmitidoController $documentController)
    {
        $company = $this->currentCompany($request);

        $resolutions = $documentController->resolutionsFor($company, 'COT');

        $products = $company->products()->active()->where('stock', '>', 1)->orderBy('description')->limit(60)->get();

        return view('quotations.create', [
            'company' => $company,
            'hasResolution' => $resolutions->isNotEmpty(),
            'products' => $documentController->mapProductsForJs($products),
            'warehouses' => $company->warehouses()->orderBy('name')->get(),
            'priceTypes' => $company->priceTypes()->orderBy('name')->get(),
            'defaultClient' => $this->defaultClient($company),
            'departments' => Department::orderBy('descripcion')->get(),
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
        ]);
    }

    /**
     * Valida y emite la cotización (sin pago ni descuento de inventario, ver
     * DocumentoEmitidoController::issueQuotation()) y responde JSON: el
     * formulario la manda por fetch, para poder abrir el modal de resultado
     * sin salir de la pantalla, igual que el checkout del POS.
     *
     * @param  Request  $request  Debe traer cliente_* e items[].
     * @param  DocumentoEmitidoController  $documentController  Arma el JSON del documento (buildDocumentJson()).
     * @param  IssueDocumentService  $service  Servicio que arma y guarda la Quotation.
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, DocumentoEmitidoController $documentController, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        $resolution = $documentController->resolutionsFor($company, 'COT')->first();

        if (! $resolution) {
            return response()->json(['message' => __('There is no valid quotation resolution available.')], 422);
        }

        try {
            $quotation = $documentController->issueQuotation($request, $company, $resolution, $service);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not issue the quotation.')], 500);
        }

        return response()->json([
            'quotation_id' => (string) $quotation->_id,
            'numeral' => $quotation->numeral,
            'total_formatted' => $quotation->total_formatted,
            'pdf_url' => route('quotations.pdf', $quotation->_id),
            'preview_url' => route('quotations.preview', $quotation->_id),
            'show_url' => route('quotations.show', $quotation->_id),
        ]);
    }

    /**
     * Lista las cotizaciones de la empresa activa, más recientes primero.
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $quotations = $company->quotations()->orderByDesc('created_at')->get();
        $catalogLinks = $company->catalogLinks()->orderByDesc('created_at')->get();
        $warehouses = $company->warehouses()->orderBy('name')->get();
        $priceTypes = $company->priceTypes()->orderBy('name')->get();

        return view('quotations.index', compact('company', 'quotations', 'catalogLinks', 'warehouses', 'priceTypes'));
    }

    /**
     * Detalle de una cotización, con los botones de conversión ("Convertir
     * a venta POS" / "Emitir factura electrónica") disponibles solo si la
     * empresa tiene el módulo correspondiente habilitado -- si no tiene
     * ninguno de los dos, no hay ninguna forma de convertirla, igual que si
     * no tuviera cotizaciones ni pos habilitado no vería el módulo.
     */
    public function show(Request $request, string $quotation)
    {
        $company = $this->currentCompany($request);

        $documento = $company->quotations()->where('_id', $quotation)->first();

        abort_unless($documento, 404);

        return view('quotations.show', [
            'company' => $company,
            'documento' => $documento,
            'canConvertToPos' => $company->hasModule('pos'),
            'canConvertToInvoicing' => $company->hasModule('invoicing'),
        ]);
    }

    /**
     * Descarga el PDF de la cotización (dispara la descarga del archivo).
     * Ver preview() para la variante que lo abre embebido en el navegador,
     * en una URL propia en vez de "?inline=1" (mismo query string para
     * ambos casos se veía feo en la barra de direcciones al abrir el link
     * en pestaña nueva).
     */
    public function pdf(Request $request, string $quotation)
    {
        [$pdf, $filename] = $this->buildPdf($request, $quotation);

        return $pdf->download($filename);
    }

    /**
     * Igual que pdf(), pero para mostrarlo embebido (iframe de vista previa
     * del modal de resultado, o "Ver PDF" en pestaña nueva) en vez de
     * descargarlo.
     */
    public function preview(Request $request, string $quotation)
    {
        [$pdf, $filename] = $this->buildPdf($request, $quotation);

        return $pdf->stream($filename);
    }

    /**
     * Arma el PDF de la cotización: plantilla propia a tamaño carta (no el
     * recibo angosto del POS pensado para impresora térmica, ver
     * quotations/pdf.blade.php) -- una cotización se le entrega al cliente
     * como documento formal, no como ticket de venta. Compartido entre
     * pdf() (descarga) y preview() (embebido).
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string} PDF armado y nombre de archivo sugerido.
     */
    private function buildPdf(Request $request, string $quotation): array
    {
        $company = $this->currentCompany($request);

        $documento = $company->quotations()->where('_id', $quotation)->first();

        abort_unless($documento, 404);

        $warehouseIds = collect($documento->payload['lineas'] ?? [])->pluck('bodega_id')->filter()->unique()->values()->all();
        $warehousesById = Warehouse::whereIn('_id', $warehouseIds)->get()->keyBy(fn (Warehouse $w) => (string) $w->_id);

        $pdf = Pdf::loadView('quotations.pdf', compact('company', 'documento', 'warehousesById'))
            ->setPaper('letter', 'portrait');

        return [$pdf, 'cotizacion-' . $documento->numeral . '.pdf'];
    }

    /**
     * Busca una cotización pendiente (aún no convertida) de la empresa por
     * id, para precargar POS/Facturación con su cliente y líneas -- ver
     * PosController::create()/DocumentoEmitidoController::create(), que
     * llaman a este método con el "from_quotation" de la URL. Devuelve null
     * si no existe, es de otra empresa, o ya se convirtió (una cotización ya
     * convertida no se vuelve a precargar).
     *
     * @param  Company  $company
     * @param  string|null  $quotationId
     * @return Quotation|null
     */
    public function resolveFromQuotation(Company $company, ?string $quotationId): ?Quotation
    {
        if (! $quotationId) {
            return null;
        }

        $quotation = $company->quotations()->where('_id', $quotationId)->first();

        return ($quotation && ! $quotation->is_converted) ? $quotation : null;
    }

    /**
     * Traduce el cliente y las líneas guardadas de una cotización al shape
     * que consumen los formularios de POS/Facturación: el cliente igual que
     * mapClientForJs()/defaultClientJs, y cada línea como un producto en el
     * shape de mapProductsForJs() (buscando el producto actual por código,
     * para traer precios/bodegas vigentes) más la cantidad guardada -- si el
     * producto ya no existe en el catálogo, se arma un producto sintético
     * solo con descripción/precio, sin precios por tipo ni bodegas.
     *
     * El precio que se precarga es SIEMPRE el que quedó guardado en la
     * cotización (linea.precio_unitario), no el precio actual del catálogo
     * ni el primero de su lista de precios -- si el producto tiene varias
     * listas, no hay forma de adivinar cuál se usó al cotizar, así que se
     * respeta el valor exacto que se le mostró al cliente.
     *
     * @param  Company  $company
     * @param  Quotation  $quotation
     * @param  DocumentoEmitidoController  $documentController
     * @return array{client: array, lines: array<int, array{product: array, qty: float, warehouse_id: ?string, unit_price: float}>}
     */
    public function mapQuotationLinesForJs(Company $company, Quotation $quotation, DocumentoEmitidoController $documentController): array
    {
        $customerParty = $quotation->payload['accounting_customer_party'] ?? [];

        $client = [
            'id' => $quotation->cliente_id,
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

        $lineas = $quotation->payload['lineas'] ?? [];
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

            return [
                'product' => $productJs,
                'qty' => (float) ($linea['cantidad'] ?? 1),
                'warehouse_id' => $linea['bodega_id'] ?? null,
                'unit_price' => (float) ($linea['precio_unitario'] ?? 0),
            ];
        })->values()->all();

        return ['client' => $client, 'lines' => $lines];
    }
}
