<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentoEmitido;
use App\Models\DocumentoPos;
use App\Models\FiscalResponsibility;
use App\Models\MeasurementUnit;
use App\Models\PaymentMeansCode;
use App\Models\PaymentMethod;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Resolution;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Dian\DianSoapClient;
use App\Services\Dian\IssueDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DocumentoEmitidoController extends Controller
{
    private const FACTURA_CODES = ['01', '02', '03', '04'];

    private const REFERENCEABLE_FACTURA_CODES = ['01', '02'];

    private const CREATABLE_DOCUMENT_TYPES = ['01', '91', '92'];
    private const NOTA_DOCUMENT_TYPES = ['91', '92'];

    /**
     * Lista los documentos emitidos por la empresa activa en su ambiente
     * DIAN actual (habilitación o producción), más recientes primero.
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);
        $environment = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        $documentos = $company->documentosEmitidos()
            ->where('ambiente', $environment)
            ->orderByDesc('created_at')
            ->get();

        $rowsHtml = view('documents.partials.rows', compact('documentos'))->render();

        return response()->json(['rows_html' => $rowsHtml]);
    }

    /**
     * Muestra el formulario para emitir un documento desde el panel web, con
     * los catálogos necesarios (productos, medios de pago,
     * departamentos/ciudades, responsabilidades fiscales). Las resoluciones,
     * facturas referenciables y clientes se cargan por AJAX (ver
     * createOptions()/clientSearch()) para no recargar la página ni cargar
     * de una vez catálogos que pueden tener miles de registros.
     *
     * Si viene "from_quotation" en la URL (al convertir una cotización
     * pendiente en factura electrónica, ver QuotationController::show()),
     * precarga el cliente y las líneas de esa cotización (ver
     * "quotationPrefill" en documents/create.blade.php).
     *
     * @param  Request  $request
     * @param  QuotationController  $quotationController  Resuelve y traduce la cotización a precargar, si aplica.
     * @return \Illuminate\View\View
     */
    public function create(Request $request, QuotationController $quotationController)
    {
        $company = $this->currentCompany($request);

        $paymentMeansCodes = PaymentMeansCode::orderBy('medio')->get();
        $measurementUnits = MeasurementUnit::orderBy('descripcion')->get();
        $departments = Department::orderBy('descripcion')->get();
        $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();

        $clients = $company->clients()->orderBy('name')->get()->map($this->mapClientForJs(...));

        $priceTypes = $company->priceTypes()->orderBy('name')->get();

        $quotation = $quotationController->resolveFromQuotation($company, $request->query('from_quotation'));
        $quotationPrefill = $quotation ? $quotationController->mapQuotationLinesForJs($company, $quotation, $this) : null;
        $quotationId = $quotation ? (string) $quotation->_id : null;

        $canEditPrice = User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []);

        return view('documents.create', compact(
            'company',
            'paymentMeansCodes',
            'measurementUnits',
            'departments',
            'fiscalResponsibilities',
            'clients',
            'priceTypes',
            'quotationPrefill',
            'quotationId',
            'canEditPrice',
        ));
    }

    /**
     * Endpoint AJAX: dado un tipo de documento, devuelve las resoluciones
     * vigentes de ese tipo, para que el formulario de creación se actualice
     * sin recargar la página.
     */
    public function createOptions(Request $request)
    {
        $company = $this->currentCompany($request);

        $tipoDocumento = $request->query('tipo_documento', '01');
        if (! in_array($tipoDocumento, self::CREATABLE_DOCUMENT_TYPES, true)) {
            $tipoDocumento = '01';
        }

        $resolutions = $this->resolutionsFor($company, $tipoDocumento);

        return response()->json([
            'resolutions' => $resolutions->map(fn (Resolution $resolution) => [
                'id' => (string) $resolution->_id,
                'prefix' => $resolution->prefix,
                'next_number' => (string) ($resolution->current_number ?: $resolution->range_from),
                'label' => trim($resolution->prefix . ' - ' . __('Resolution') . ' ' . $resolution->resolution_number),
            ])->values(),
        ]);
    }

    /**
     * Endpoint AJAX: busca por numeral exacto una factura propia (01/02) ya
     * aceptada por la DIAN, para autocompletar la referencia de una nota sin
     * tener que cargar/listar todas las facturas de la empresa (puede haber
     * miles). Si no aparece, el formulario deja que el usuario suministre a
     * mano el UUID y la fecha de emisión (puede ser una factura de otro
     * proveedor tecnológico, fuera de nuestro sistema).
     */
    public function facturaLookup(Request $request)
    {
        $company = $this->currentCompany($request);
        $environment = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        $numeral = trim((string) $request->query('numeral', ''));
        if ($numeral === '') {
            return response()->json(['found' => false]);
        }

        $factura = $company->documentosEmitidos()
            ->where('ambiente', $environment)
            ->whereIn('tipo_documento', self::REFERENCEABLE_FACTURA_CODES)
            ->where('status', DocumentoEmitido::STATUS_ACCEPTED)
            ->where('numeral', $numeral)
            ->first();

        if (! $factura) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'numeral' => $factura->numeral,
            'uuid' => $factura->uuid,
            'issue_date' => $factura->issue_date?->setTimezone('America/Bogota')->format('Y-m-d'),
            'customer_name' => $factura->payload['accounting_customer_party']['PartyName'] ?? '',
            'total' => (float) $factura->total,
        ]);
    }

    /**
     * Endpoint AJAX: busca clientes propios por nombre o identificación, para
     * autocompletar el receptor del documento sin cargar de una vez todos
     * los clientes de la empresa (puede haber miles). Trae como máximo 20
     * coincidencias; si no aparece ninguna, el formulario deja los campos
     * de cliente en blanco para que el usuario cree uno nuevo.
     */
    public function clientSearch(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['clients' => []]);
        }

        $clients = $company->clients()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%')
                    ->orWhere('identificacion', 'like', '%' . $query . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'clients' => $clients->map($this->mapClientForJs(...))->values(),
        ]);
    }

    /**
     * Traduce un ThirdParty (rol cliente) al shape que consume el JS del
     * formulario de emisión -- usado tanto en la búsqueda incremental
     * (clientSearch()) como en el modal "buscar en todos los clientes"
     * (create()), para no duplicar la lista de campos en dos lados.
     */
    private function mapClientForJs($client): array
    {
        return [
            'id' => (string) $client->_id,
            'identification_type' => $client->identification_type,
            'identificacion' => $client->identificacion,
            'name' => $client->name,
            'person_type' => $client->person_type,
            'fiscal_responsibilities' => $client->fiscal_responsibilities,
            'address' => $client->address,
            'department_code' => $client->department_code,
            'city_code' => $client->city_code,
            'phone' => $client->phone,
            'email' => $client->email,
        ];
    }

    /**
     * Endpoint AJAX: busca productos por código, código de barras o
     * descripción mientras el usuario escribe una línea del documento (con
     * miles de productos, no se puede cargar todo el catálogo de una vez).
     * Trae, por cada coincidencia, sus precios (con el nombre del tipo) y
     * sus bodegas con stock (con el nombre), más una entrada "sin asignar"
     * si el producto controla inventario.
     *
     * El filtro de bodega/stock (parámetro warehouse_id) solo lo pide la
     * pantalla de venta del POS (ver pos/sell.blade.php); el selector de
     * líneas de facturación no lo manda y sigue sin filtrar por stock (ahí sí
     * puede hacer falta referenciar un producto sin existencias). "all" =
     * todas las bodegas, pero igual exige más de 1 unidad en total; un id
     * puntual exige más de 1 unidad en ESA bodega (elemMatch: ambas
     * condiciones sobre el mismo elemento del arreglo embebido, no en
     * cualquiera).
     *
     * @param  Request  $request  Query params: q (texto de búsqueda),
     *                            warehouse_id (opcional: vacío, "all" o un ID).
     * @return \Illuminate\Http\JsonResponse Lista de productos en el shape de mapProductsForJs().
     */
    public function productSearch(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = trim((string) $request->query('q', ''));
        $warehouseId = trim((string) $request->query('warehouse_id', ''));

        $products = $company->products()->active()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($builder) use ($query) {
                    $builder->where('code', 'like', '%' . $query . '%')
                        ->orWhere('barcode', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%');
                });
            })
            ->when($warehouseId === 'all', function ($builder) {
                $builder->where(fn ($builder) => $builder->where('stock', '>', 1)->orWhere('tracks_inventory', '!=', true));
            })
            ->when($warehouseId !== '' && $warehouseId !== 'all', function ($builder) use ($warehouseId) {
                $builder->where(function ($builder) use ($warehouseId) {
                    $builder->where('warehouse_stocks', 'elemMatch', [
                        'warehouse_id' => $warehouseId,
                        'stock' => ['$gt' => 1],
                    ])->orWhere('tracks_inventory', '!=', true);
                });
            })
            ->orderBy('description')
            ->limit(50)
            ->get();

        return response()->json([
            'products' => $this->mapProductsForJs($products),
        ]);
    }

    /**
     * Traduce productos al shape que consume el JS del selector de productos
     * (precios por tipo, stock por bodega) -- público para que PosController
     * arme el mismo shape en la carga inicial de la pantalla de venta (ver
     * pos/sell.blade.php), sin duplicar esta lógica.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    public function mapProductsForJs($products)
    {
        $priceTypeIds = $products->flatMap(fn ($product) => collect($product->extra_prices ?? [])->pluck('price_type_id'))->unique()->values()->all();
        $warehouseIds = $products->flatMap(fn ($product) => collect($product->warehouse_stocks ?? [])->pluck('warehouse_id'))->unique()->values()->all();

        $priceTypesById = PriceType::whereIn('_id', $priceTypeIds)->get()->keyBy(fn ($priceType) => (string) $priceType->_id);
        $warehousesById = Warehouse::whereIn('_id', $warehouseIds)->get()->keyBy(fn ($warehouse) => (string) $warehouse->_id);

        return $products->map(function (Product $product) use ($priceTypesById, $warehousesById) {
            $prices = collect($product->extra_prices ?? [])
                ->map(fn ($entry) => [
                    'price_type_id' => $entry['price_type_id'] ?? null,
                    'price_type_name' => $priceTypesById->get($entry['price_type_id'] ?? null)?->name ?? __('Price'),
                    'price' => (float) ($entry['price'] ?? 0),
                ])
                ->values();

            $warehouses = collect($product->warehouse_stocks ?? [])
                ->map(fn ($entry) => [
                    'warehouse_id' => $entry['warehouse_id'] ?? null,
                    'warehouse_name' => $warehousesById->get($entry['warehouse_id'] ?? null)?->name ?? __('Warehouse'),
                    'stock' => (float) ($entry['stock'] ?? 0),
                ])
                ->values();

            $warehouses->push([
                'warehouse_id' => null,
                'warehouse_name' => __('Unassigned'),
                'stock' => $product->unassigned_stock,
            ]);

            return [
                'id' => (string) $product->_id,
                'code' => $product->code,
                'barcode' => $product->barcode,
                'description' => $product->description,
                'unit_code' => $product->unit_code,
                'unit_price' => (float) $product->unit_price,
                'tracks_inventory' => (bool) $product->tracks_inventory,
                'stock' => (float) $product->stock,
                'prices' => $prices,
                'warehouses' => $warehouses,
                'image_url' => $product->image_url,
            ];
        })->values();
    }

    /**
     * Endpoint AJAX: valida un UUID/CUFE directamente contra la DIAN
     * (servicio GetDocumentInfo), para la factura referenciada que el
     * usuario suministró a mano porque no está en nuestro sistema (emitida
     * por otro proveedor tecnológico). Trae todo lo que la DIAN sabe de ese
     * documento (emisor, receptor, totales, eventos) para que el usuario
     * confirme que el UUID es real antes de emitir la nota.
     */
    public function validateUuid(Request $request, DianSoapClient $dianSoapClient)
    {
        $company = $this->currentCompany($request);

        $uuid = trim((string) $request->query('uuid', ''));
        if ($uuid === '') {
            return response()->json(['success' => false, 'message' => __('You must provide a UUID.')], 422);
        }

        try {
            $info = $dianSoapClient->getDocumentInfo($company, $uuid);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'info' => $info]);
    }

    /**
     * Resuelve las resoluciones vigentes para un tipo de documento dado, en
     * el ambiente DIAN actual de la empresa. Compartido entre create()
     * (carga inicial), createOptions() (AJAX al cambiar el tipo de documento
     * en el formulario) y, público, CashShiftController/PosController (para
     * ofrecer las resoluciones 'FV'/'01' al abrir un turno del POS).
     */
    public function resolutionsFor(Company $company, string $tipoDocumento): \Illuminate\Support\Collection
    {
        $environment = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;
        $documentTypes = in_array($tipoDocumento, self::FACTURA_CODES, true) ? self::FACTURA_CODES : [$tipoDocumento];

        return Resolution::where('company_id', (string) $company->_id)
            ->whereIn('document_type', $documentTypes)
            ->where('environment', $environment)
            ->active()
            ->get()
            ->filter(fn (Resolution $resolution) => ! $resolution->isExpired() && ! $resolution->isExhausted())
            ->values();
    }

    /**
     * Arma el JSON del documento (mismo shape que espera la API) a partir
     * del formulario, y lo emite con IssueDocumentService.
     */
    /**
     * Responde en JSON cuando el formulario lo pide (ver el fetch() en
     * documents/create.blade.php) -- así, si falla, se le muestra un modal
     * con el motivo sin recargar la página, en vez de perder todas las
     * líneas ya cargadas como pasaba antes con back()->withInput() (no
     * alcanza a reconstruir el estado armado por JS).
     */
    public function store(Request $request, IssueDocumentService $service)
    {
        try {
            $documento = $this->issueFromRequest($request, $service);
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            report($e);

            if ($request->wantsJson()) {
                return response()->json(['message' => __('Could not issue the document.')], 500);
            }

            return back()->withErrors(['message' => __('Could not issue the document.')])->withInput();
        }

        if ($quotationId = $request->input('quotation_id')) {
            $this->currentCompany($request)->quotations()->where('_id', $quotationId)->update(['documento_emitido_id' => (string) $documento->_id]);
        }

        session()->flash('toast', [
            'type' => $documento->status === DocumentoEmitido::STATUS_ACCEPTED ? 'success' : 'error',
            'message' => $documento->status === DocumentoEmitido::STATUS_ACCEPTED
                ? __('Document issued and accepted by the DIAN.')
                : __('The document was sent, but the DIAN did not accept it yet. Check the details.'),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'redirect_url' => route('documents.show', $documento->_id),
                'status' => $documento->status,
                'accepted' => $documento->status === DocumentoEmitido::STATUS_ACCEPTED,
                'status_message' => $documento->status_message,
            ]);
        }

        return redirect()->route('documents.show', $documento->_id);
    }

    /**
     * Valida el request, resuelve la resolución, reclama el siguiente
     * número y emite el documento -- el mismo pipeline que usa store(), pero
     * sin la redirección/flash propias del flujo web. Usado por la pantalla
     * normal de facturación electrónica; el POS tiene su propio flujo (ver
     * issuePosSale() más abajo), porque una venta del POS no llega con
     * "tipo_documento"/"resolution_id" en el request -- la numeración ya
     * quedó fija al abrir el turno de caja.
     *
     * @throws InvalidArgumentException|RuntimeException Errores de validación de negocio (resolución agotada, resolución inválida, etc.), pensados para mostrarse tal cual al usuario.
     */
    public function issueFromRequest(Request $request, IssueDocumentService $service): DocumentoEmitido
    {
        $company = $this->currentCompany($request);

        $document = $this->buildDocumentFromRequest($request, $company, claimNumber: true);

        return $service->issue($company, ['document' => $document], (string) $request->user()->_id);
    }

    /**
     * Valida el formulario y arma el JSON del documento (mismo shape que
     * espera la API) -- compartido entre issueFromRequest() (emisión real)
     * y preview() (vista previa, sin reclamar número). El único punto que
     * cambia entre los dos es "secuencial": la emisión real reclama el
     * siguiente número de la resolución (lo consume); la vista previa solo
     * lo consulta ("current_number" tal cual está, sin tocarlo) para
     * mostrar cuál sería, sin saltárselo si el usuario cancela.
     *
     * @throws InvalidArgumentException Si la resolución elegida no es válida.
     */
    private function buildDocumentFromRequest(Request $request, Company $company, bool $claimNumber): array
    {
        $data = $request->validate([
            'tipo_documento' => ['required', 'string', 'in:' . implode(',', self::CREATABLE_DOCUMENT_TYPES)],
            'tipo_operacion' => ['required', 'string', 'max:5'],
            'resolution_id' => ['required', 'string'],
            'issue_date' => ['nullable', 'date'],
            'issue_time' => ['nullable', 'string', 'max:20'],

            'referencia_factura_id' => ['required_if:tipo_operacion,20,30', 'nullable', 'string', 'max:50'],
            'referencia_factura_uuid' => ['nullable', 'string', 'max:100'],
            'referencia_factura_fecha_emision' => ['nullable', 'date'],
            'referencia_periodo_desde' => ['required_if:tipo_operacion,22,32', 'nullable', 'date'],
            'referencia_periodo_hasta' => ['required_if:tipo_operacion,22,32', 'nullable', 'date', 'after_or_equal:referencia_periodo_desde'],
            'referencia_concepto_codigo' => ['required_if:tipo_operacion,20,22,30,32', 'nullable', 'string', 'max:5'],

            'cliente_tipo_identificacion' => ['required', 'string', 'max:2'],
            'cliente_identificacion' => ['required', 'string', 'max:20'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_tipo_persona' => ['required', 'string', 'in:1,2'],
            'cliente_responsabilidades' => ['nullable', 'array'],
            'cliente_direccion' => ['required', 'string', 'max:255'],
            'cliente_departamento_codigo' => ['required', 'string', 'max:10'],
            'cliente_ciudad_codigo' => ['required', 'string', 'max:10'],
            'cliente_telefono' => ['nullable', 'string', 'max:50'],
            'cliente_email' => ['nullable', 'email', 'max:255'],

            'payment_means_id' => ['nullable', 'array'],
            'payment_means_id.*' => ['nullable', 'string', 'in:1,2'],
            'payment_means_code' => ['nullable', 'array'],
            'payment_means_code.*' => ['nullable', 'string', 'max:10'],
            'payment_due_date' => ['nullable', 'array'],
            'payment_due_date.*' => ['nullable', 'date'],

            'cargo_tipo' => ['nullable', 'array'],
            'cargo_tipo.*' => ['nullable', 'string', 'in:cargo,descuento'],
            'cargo_motivo' => ['nullable', 'array'],
            'cargo_motivo.*' => ['nullable', 'string', 'max:255'],
            'cargo_valor_tipo' => ['nullable', 'array'],
            'cargo_valor_tipo.*' => ['nullable', 'string', 'in:porcentaje,fijo'],
            'cargo_valor' => ['nullable', 'array'],
            'cargo_valor.*' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.codigo' => ['required', 'string', 'max:50'],
            'items.*.codigo_barras' => ['nullable', 'string', 'max:50'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['nullable', 'string', 'max:10'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.bodega_id' => ['nullable', 'string'],
            'items.*.descuento_valor_tipo' => ['nullable', 'string', 'in:porcentaje,fijo'],
            'items.*.descuento_valor' => ['nullable', 'numeric', 'min:0'],
            'items.*.descuento_motivo' => ['nullable', 'string', 'max:255'],
            'items.*.impuestos' => ['nullable', 'array'],
            'items.*.impuestos.*.tipo' => ['required_with:items.*.impuestos', 'string', 'max:5'],
            'items.*.impuestos.*.porcentaje' => ['required_with:items.*.impuestos', 'numeric', 'min:0'],
            'items.*.impuestos.*.base_gravable' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['items'] = $this->enforceCatalogPriceForNonAdmins($company, $data['items'], $company->membership->role, $company->membership->modules ?? []);

        $resolution = Resolution::where('company_id', (string) $company->_id)
            ->where('_id', $data['resolution_id'])
            ->first();

        if (! $resolution) {
            throw new InvalidArgumentException(__('The selected resolution is not valid.'));
        }

        $data['prefix'] = $resolution->prefix;

        $data['secuencial'] = $claimNumber
            ? $resolution->claimNextNumber()
            : (int) ($resolution->current_number ?: $resolution->range_from);

        return $this->buildDocumentJson($company, $data);
    }

    /**
     * Vista previa del documento antes de emitirlo de verdad: arma el
     * mismo PDF que se vería después de emitido (documents/invoice-pdf.blade.php),
     * pero sin firmar ni enviar nada a la DIAN, ni reclamar el número de
     * la resolución -- si el usuario cancela desde el modal de
     * confirmación, no se pierde ningún número. Se muestra embebido en un
     * iframe dentro de ese modal (ver el fetch() en documents/create.blade.php).
     */
    public function preview(Request $request, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        try {
            $document = $this->buildDocumentFromRequest($request, $company, claimNumber: false);
            $documento = $service->buildPreview($company, ['document' => $document]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        $pdf = Pdf::loadView('documents.invoice-pdf', [
            'company' => $company,
            'documento' => $documento,
            'paymentMeansCode' => $paymentMeansCode,
            'qrDataUri' => null,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('preview.pdf');
    }

    /**
     * Valida el request y arma + emite la venta del POS: SIEMPRE crea el
     * documento_pos (numerado con la resolución 'FV' del turno abierto), y
     * si el cajero marcó "emitir factura electrónica" para esta venta
     * puntual, además arma y envía la factura electrónica real (numerada
     * con la resolución de facturación electrónica del turno) y la enlaza.
     * No hay "tipo_documento"/"resolution_id" en el request -- a diferencia
     * de issueFromRequest(), la numeración ya quedó fija al abrir el turno
     * (ver CashShiftController::store()), así que no hay nada que elegir
     * por venta.
     *
     * Una venta puede pagarse con más de un medio (mitad efectivo, mitad
     * tarjeta, etc.); lo que venga en payment_method_amount debe sumar
     * exactamente el total (el POS no maneja impuestos por línea, así que el
     * total es solo cantidad × precio unitario) -- si no cuadra, se rechaza
     * antes de tocar inventario o numeración.
     *
     * @param  Request  $request  Debe traer cliente_*, items[] y opcionalmente payment_method_id[]/payment_method_amount[].
     * @param  Company  $company  Empresa emisora.
     * @param  CashShift  $shift  Turno abierto bajo el que se numera esta venta.
     * @param  IssueDocumentService  $service  Servicio que arma y guarda el DocumentoPos.
     * @return DocumentoPos Venta ya creada (talonario, con la resolución FV del turno).
     * @throws InvalidArgumentException|RuntimeException Errores de validación de negocio, pensados para mostrarse tal cual al usuario.
     */
    public function issuePosSale(Request $request, Company $company, CashShift $shift, IssueDocumentService $service): DocumentoPos
    {
        $data = $request->validate([
            
            'tipo_operacion' => ['nullable', 'string', 'max:5'],
            'issue_date' => ['nullable', 'date'],
            'issue_time' => ['nullable', 'string', 'max:20'],

            'cliente_tipo_identificacion' => ['required', 'string', 'max:2'],
            'cliente_identificacion' => ['required', 'string', 'max:20'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            
            'cliente_tipo_persona' => ['nullable', 'string', 'in:1,2'],
            'cliente_responsabilidades' => ['nullable', 'array'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_departamento_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_ciudad_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_telefono' => ['nullable', 'string', 'max:50'],
            'cliente_email' => ['nullable', 'email', 'max:255'],

            'seller_id' => ['nullable', 'string'],

            'payment_means_id' => ['nullable', 'array'],
            'payment_means_id.*' => ['nullable', 'string', 'in:1,2'],
            'payment_method_id' => ['nullable', 'array'],
            'payment_method_id.*' => ['nullable', 'string'],
            'payment_method_amount' => ['nullable', 'array'],
            'payment_method_amount.*' => ['nullable', 'numeric', 'min:0'],
            'payment_due_date' => ['nullable', 'array'],
            'payment_due_date.*' => ['nullable', 'date'],

            'cargo_tipo' => ['nullable', 'array'],
            'cargo_tipo.*' => ['nullable', 'string', 'in:cargo,descuento'],
            'cargo_motivo' => ['nullable', 'array'],
            'cargo_motivo.*' => ['nullable', 'string', 'max:255'],
            'cargo_valor_tipo' => ['nullable', 'array'],
            'cargo_valor_tipo.*' => ['nullable', 'string', 'in:porcentaje,fijo'],
            'cargo_valor' => ['nullable', 'array'],
            'cargo_valor.*' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.codigo' => ['required', 'string', 'max:50'],
            'items.*.codigo_barras' => ['nullable', 'string', 'max:50'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['nullable', 'string', 'max:10'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.bodega_id' => ['nullable', 'string'],
            'items.*.descuento_valor_tipo' => ['nullable', 'string', 'in:porcentaje,fijo'],
            'items.*.descuento_valor' => ['nullable', 'numeric', 'min:0'],
            'items.*.descuento_motivo' => ['nullable', 'string', 'max:255'],
            'items.*.impuestos' => ['nullable', 'array'],
            'items.*.impuestos.*.tipo' => ['required_with:items.*.impuestos', 'string', 'max:5'],
            'items.*.impuestos.*.porcentaje' => ['required_with:items.*.impuestos', 'numeric', 'min:0'],
            'items.*.impuestos.*.base_gravable' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['items'] = $this->enforceCatalogPriceForNonAdmins($company, $data['items'], $company->membership->role, $company->membership->modules ?? []);

        if (! $shift->fvResolution) {
            throw new InvalidArgumentException(__('The cash shift has no valid sales invoice resolution.'));
        }

        $paymentMethodIds = array_filter($data['payment_method_id'] ?? []);
        $paymentMethods = $company->paymentMethods()->whereIn('_id', $paymentMethodIds)->get()->keyBy(fn (PaymentMethod $m) => (string) $m->_id);
        $data['payment_means_code'] = collect($data['payment_method_id'] ?? [])
            ->map(fn (?string $id) => $id ? $paymentMethods->get($id)?->dian_payment_means_code : null)
            ->all();

        $saleTotal = round(collect($data['items'])->sum(fn (array $item) => $item['cantidad'] * $item['precio_unitario']), 2);
        $paymentAmounts = array_map(fn ($amount) => round((float) ($amount ?? 0), 2), $data['payment_method_amount'] ?? []);
        $assignedTotal = round(array_sum($paymentAmounts), 2);

        if (! empty($paymentMethodIds) && abs($assignedTotal - $saleTotal) > 0.01) {
            throw new InvalidArgumentException(__('The amounts assigned to the payment methods (:assigned) do not match the sale total (:total).', [
                'assigned' => number_format($assignedTotal, 2),
                'total' => number_format($saleTotal, 2),
            ]));
        }

        $data['tipo_documento'] = '01';
        $data['tipo_operacion'] = $data['tipo_operacion'] ?? '10';
        $data['cliente_tipo_persona'] = $data['cliente_tipo_persona'] ?? '2';
        $data['prefix'] = $shift->fvResolution->prefix;
        $data['secuencial'] = 0;

        $documentoPos = $service->issuePosSale($company, ['document' => $this->buildDocumentJson($company, $data)], $shift->fvResolution, $shift);

        $payments = collect($data['payment_method_id'] ?? [])
            ->map(function (?string $id, int $index) use ($paymentMethods, $paymentAmounts) {
                if (! $id) {
                    return null;
                }
                $method = $paymentMethods->get($id);

                return [
                    'payment_method_id' => $id,
                    'payment_method_name' => $method?->name,
                    'dian_code' => $method?->dian_payment_means_code,
                    'amount' => $paymentAmounts[$index] ?? 0,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $firstPayment = $payments[0] ?? null;
        $seller = ! empty($data['seller_id']) ? $company->sellers()->find($data['seller_id']) : null;

        $documentoPos->update([
            'payment_method_id' => $firstPayment['payment_method_id'] ?? null,
            'payment_method_name' => $firstPayment['payment_method_name'] ?? null,
            'payments' => $payments,
            'seller_id' => $seller ? (string) $seller->_id : null,
            'seller_name' => $seller?->name,
        ]);

        return $documentoPos;
    }

    /**
     * Edita una venta POS que aún no se facturó electrónicamente -- una vez la DIAN acepta la
     * factura, corregirla requiere una Nota Crédito, no una edición silenciosa. Como es un
     * talonario (no hay XML/UBL de por medio todavía), acá sí se puede tocar TODO: cliente,
     * vendedor, forma de pago y líneas, igual que si fuera una venta nueva -- mismos campos y
     * misma validación que issuePosSale(), solo que sin volver a numerar ni a abrir turno.
     *
     * @param  Request  $request  Mismo shape que issuePosSale() (cliente_*, seller_id, payment_method_id/amount, items[]).
     * @param  Company  $company  Empresa dueña de la venta.
     * @param  DocumentoPos  $documento  Venta a editar.
     * @param  IssueDocumentService  $service  Servicio que reversa/aplica el inventario, resuelve el cliente y guarda.
     * @return DocumentoPos Venta ya actualizada.
     *
     * @throws InvalidArgumentException|RuntimeException Si ya se facturó electrónicamente, o error de validación de negocio.
     */
    public function updatePosSaleItems(Request $request, Company $company, DocumentoPos $documento, IssueDocumentService $service): DocumentoPos
    {
        if ($documento->is_electronic) {
            throw new InvalidArgumentException(__('This sale was already issued as an electronic invoice; use a credit note instead of editing it.'));
        }

        $data = $request->validate([
            'cliente_tipo_identificacion' => ['required', 'string', 'max:2'],
            'cliente_identificacion' => ['required', 'string', 'max:20'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_tipo_persona' => ['nullable', 'string', 'in:1,2'],
            'cliente_responsabilidades' => ['nullable', 'array'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_departamento_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_ciudad_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_telefono' => ['nullable', 'string', 'max:50'],
            'cliente_email' => ['nullable', 'email', 'max:255'],

            'seller_id' => ['nullable', 'string'],

            'payment_method_id' => ['nullable', 'array'],
            'payment_method_id.*' => ['nullable', 'string'],
            'payment_method_amount' => ['nullable', 'array'],
            'payment_method_amount.*' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.codigo' => ['required', 'string', 'max:50'],
            'items.*.codigo_barras' => ['nullable', 'string', 'max:50'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['nullable', 'string', 'max:10'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.bodega_id' => ['nullable', 'string'],
            'items.*.descuento_valor_tipo' => ['nullable', 'string', 'in:porcentaje,fijo'],
            'items.*.descuento_valor' => ['nullable', 'numeric', 'min:0'],
            'items.*.descuento_motivo' => ['nullable', 'string', 'max:255'],
            'items.*.impuestos' => ['nullable', 'array'],
            'items.*.impuestos.*.tipo' => ['required_with:items.*.impuestos', 'string', 'max:5'],
            'items.*.impuestos.*.porcentaje' => ['required_with:items.*.impuestos', 'numeric', 'min:0'],
            'items.*.impuestos.*.base_gravable' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentMethodIds = array_filter($data['payment_method_id'] ?? []);
        $paymentMethods = $company->paymentMethods()->whereIn('_id', $paymentMethodIds)->get()->keyBy(fn (PaymentMethod $m) => (string) $m->_id);

        $saleTotal = round(collect($data['items'])->sum(fn (array $item) => $item['cantidad'] * $item['precio_unitario']), 2);
        $paymentAmounts = array_map(fn ($amount) => round((float) ($amount ?? 0), 2), $data['payment_method_amount'] ?? []);
        $assignedTotal = round(array_sum($paymentAmounts), 2);

        if (! empty($paymentMethodIds) && abs($assignedTotal - $saleTotal) > 0.01) {
            throw new InvalidArgumentException(__('The amounts assigned to the payment methods (:assigned) do not match the sale total (:total).', [
                'assigned' => number_format($assignedTotal, 2),
                'total' => number_format($saleTotal, 2),
            ]));
        }

        $accountingCustomerParty = [
            'AdditionalAccountID' => ($data['cliente_tipo_persona'] ?? '2') === '1' ? '1' : '2',
            'PartyName' => $data['cliente_nombre'],
            'CompanyID' => $data['cliente_identificacion'],
            'TypeCompanyID' => $data['cliente_tipo_identificacion'],
            'TaxLevelCode' => implode(';', $data['cliente_responsabilidades'] ?? []),
            'direccion' => $data['cliente_direccion'] ?? null,
            'cityCode' => $data['cliente_ciudad_codigo'] ?? null,
            'CountrySubentityCode' => $data['cliente_departamento_codigo'] ?? null,
            'telefono' => $data['cliente_telefono'] ?? null,
            'email' => $data['cliente_email'] ?? null,
        ];

        $documento = $service->updatePosSaleLines($company, $documento, $data['items'], $accountingCustomerParty, (string) $request->user()->_id);

        $payments = collect($data['payment_method_id'] ?? [])
            ->map(function (?string $id, int $index) use ($paymentMethods, $paymentAmounts) {
                if (! $id) {
                    return null;
                }
                $method = $paymentMethods->get($id);

                return [
                    'payment_method_id' => $id,
                    'payment_method_name' => $method?->name,
                    'dian_code' => $method?->dian_payment_means_code,
                    'amount' => $paymentAmounts[$index] ?? 0,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $firstPayment = $payments[0] ?? null;
        $seller = ! empty($data['seller_id']) ? $company->sellers()->find($data['seller_id']) : null;

        $documento->update([
            'payment_method_id' => $firstPayment['payment_method_id'] ?? null,
            'payment_method_name' => $firstPayment['payment_method_name'] ?? null,
            'payments' => $payments,
            'seller_id' => $seller ? (string) $seller->_id : null,
            'seller_name' => $seller?->name,
        ]);

        return $documento->fresh();
    }

    /**
     * Valida el request y arma + guarda una cotización: no hay pago ni
     * turno de caja de por medio (a diferencia de issuePosSale()), y el
     * inventario NO se descuenta -- una cotización no es una venta todavía.
     *
     * @param  Request  $request  Debe traer cliente_* e items[] (mismo shape que issuePosSale(), sin campos de pago).
     * @param  Company  $company  Empresa emisora.
     * @param  Resolution  $resolution  Resolución manual tipo 'COT' de la empresa.
     * @param  IssueDocumentService  $service  Servicio que arma y guarda la Quotation.
     * @return Quotation Cotización ya creada.
     */
    public function issueQuotation(Request $request, Company $company, Resolution $resolution, IssueDocumentService $service): Quotation
    {
        $data = $request->validate([
            'issue_date' => ['nullable', 'date'],
            'issue_time' => ['nullable', 'string', 'max:20'],

            'cliente_tipo_identificacion' => ['required', 'string', 'max:2'],
            'cliente_identificacion' => ['required', 'string', 'max:20'],
            'cliente_nombre' => ['required', 'string', 'max:255'],

            'cliente_tipo_persona' => ['nullable', 'string', 'in:1,2'],
            'cliente_responsabilidades' => ['nullable', 'array'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_departamento_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_ciudad_codigo' => ['nullable', 'string', 'max:10'],
            'cliente_telefono' => ['nullable', 'string', 'max:50'],
            'cliente_email' => ['nullable', 'email', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.codigo' => ['required', 'string', 'max:50'],
            'items.*.codigo_barras' => ['nullable', 'string', 'max:50'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['nullable', 'string', 'max:10'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.bodega_id' => ['nullable', 'string'],
        ]);

        $data['tipo_documento'] = '01';
        $data['tipo_operacion'] = '10';
        $data['cliente_tipo_persona'] = $data['cliente_tipo_persona'] ?? '2';
        $data['prefix'] = $resolution->prefix;
        $data['secuencial'] = 0;

        return $service->issueQuotation($company, ['document' => $this->buildDocumentJson($company, $data)], $resolution);
    }

    /**
     * Traduce los datos ya validados del formulario al mismo shape de JSON
     * que espera la API (document.*), para reusar DocumentJsonMapper tal
     * cual sin duplicar su lógica de resolución/creación de cliente.
     *
     * @param  Company  $company  Empresa emisora (dueña de la sesión activa).
     * @param  array  $data  Datos ya validados de la petición.
     * @return array Bloque "document" en el mismo shape que espera la API.
     *
     * Público (no privado) para que PosController arme el mismo JSON en el
     * checkout del POS sin duplicar esta lógica.
     */
    public function buildDocumentJson(Company $company, array $data): array
    {
        $document = [
            'DocumentType' => $data['tipo_documento'],
            'CustomizationID' => $data['tipo_operacion'],
            'PREFIX' => $data['prefix'],
            'secuencial' => $data['secuencial'],
            'DocumentCurrencyCode' => 'COP',
            'AccountingSupplierParty' => [
                'CompanyID' => $company->identificacion,
            ],
            'AccountingCustomerParty' => [
                'AdditionalAccountID' => $data['cliente_tipo_persona'] === '1' ? '1' : '2',
                'PartyName' => $data['cliente_nombre'],
                'CompanyID' => $data['cliente_identificacion'],
                'TypeCompanyID' => $data['cliente_tipo_identificacion'],
                'TaxLevelCode' => implode(';', $data['cliente_responsabilidades'] ?? []),
                'direccion' => $data['cliente_direccion'] ?? null,
                'cityCode' => $data['cliente_ciudad_codigo'] ?? null,
                'CountrySubentityCode' => $data['cliente_departamento_codigo'] ?? null,
                'telefono' => $data['cliente_telefono'] ?? null,
                'email' => $data['cliente_email'] ?? null,
            ],
            'InvoiceLine' => array_map(
                fn (array $item, int $index) => $this->buildInvoiceLine($item, $index),
                $data['items'],
                array_keys($data['items']),
            ),
        ];

        if (! empty($data['issue_date'])) {
            $document['IssueDate'] = $data['issue_date'];
            $document['IssueTime'] = $data['issue_time'] ?? '00:00:00-05:00';
        }

        if (! empty($data['payment_due_date'][0])) {
            $document['DueDate'] = $data['payment_due_date'][0];
        }

        if (! empty($data['referencia_factura_id'])) {
            $document['BillingReference'] = [
                'InvoiceDocumentReference' => array_filter([
                    'ID' => $data['referencia_factura_id'],
                    'UUID' => $data['referencia_factura_uuid'] ?? null,
                    'IssueDate' => $data['referencia_factura_fecha_emision'] ?? null,
                ]),
            ];
        } elseif (! empty($data['referencia_periodo_desde']) && ! empty($data['referencia_periodo_hasta'])) {
            $document['InvoicePeriod'] = [
                'StartDate' => $data['referencia_periodo_desde'],
                'EndDate' => $data['referencia_periodo_hasta'],
            ];
        }

        if (! empty($data['referencia_factura_id']) || ! empty($data['referencia_periodo_desde'])) {
            $document['DiscrepancyResponse'] = [
                'ResponseCode' => $data['referencia_concepto_codigo'],
            ];
        }

        $paymentMeansIds = $data['payment_means_id'] ?? [];
        $paymentMeansCodes = $data['payment_means_code'] ?? [];
        $paymentDueDates = $data['payment_due_date'] ?? [];
        $paymentRowCount = max(count($paymentMeansIds), count($paymentMeansCodes), count($paymentDueDates));

        $paymentMeans = [];
        for ($i = 0; $i < $paymentRowCount; $i++) {
            if (empty($paymentMeansIds[$i]) && empty($paymentMeansCodes[$i]) && empty($paymentDueDates[$i])) {
                continue;
            }

            $paymentMeans[] = [
                'ID' => $paymentMeansIds[$i] ?? '1',
                'PaymentMeansCode' => $paymentMeansCodes[$i] ?? '10',
                'PaymentDueDate' => $paymentDueDates[$i] ?? null,
                
                'PaymentID' => (string) (count($paymentMeans) + 1),
            ];
        }

        if (! empty($paymentMeans)) {
            $document['PaymentMeans'] = $paymentMeans;
        }

        $cargoTipos = $data['cargo_tipo'] ?? [];
        $cargoMotivos = $data['cargo_motivo'] ?? [];
        $cargoValorTipos = $data['cargo_valor_tipo'] ?? [];
        $cargoValores = $data['cargo_valor'] ?? [];
        $cargoRowCount = max(count($cargoTipos), count($cargoMotivos), count($cargoValorTipos), count($cargoValores));

        $cargos = [];
        for ($i = 0; $i < $cargoRowCount; $i++) {
            if (empty($cargoMotivos[$i]) || ! isset($cargoValores[$i]) || $cargoValores[$i] === '') {
                continue;
            }

            $esPorcentaje = ($cargoValorTipos[$i] ?? 'fijo') === 'porcentaje';

            $cargos[] = [
                'ChargeIndicator' => ($cargoTipos[$i] ?? 'descuento') === 'cargo',
                'AllowanceChargeReason' => $cargoMotivos[$i],
                'MultiplierFactorNumeric' => $esPorcentaje ? (float) $cargoValores[$i] : null,
                'Amount' => $esPorcentaje ? null : (float) $cargoValores[$i],
            ];
        }

        if (! empty($cargos)) {
            $document['AllowanceCharge'] = $cargos;
        }

        return $document;
    }

    /**
     * Si quien hace la petición NO es administrador/owner, revisa que el
     * "precio_unitario" que mandó el navegador sea alguno de los precios
     * reales del catálogo de ese producto (el base o cualquiera de sus
     * listas de precio) -- si no coincide con ninguno (lo escribió a mano
     * saltándose el bloqueo de la UI, ver "canEditPrice" en
     * pos/sell.blade.php y documents/create.blade.php), lo reemplaza por el
     * precio base. No hace falta que el navegador diga qué lista eligió:
     * cualquier precio que de verdad esté en el catálogo del producto es
     * válido para un cajero/vendedor, así no haya que sincronizar el tipo de
     * precio elegido en cada pantalla. Los productos que no existen en el
     * catálogo (líneas manuales) se dejan tal cual, porque no hay contra qué
     * validarlos.
     *
     * @param  Company  $company  Empresa activa, para resolver los productos por código.
     * @param  array  $items  Líneas ya validadas ("items.*").
     * @param  string|null  $role  Rol global de la membresía del usuario en esta empresa.
     * @param  array  $modules  Roles por módulo de la membresía.
     * @return array Mismas líneas, con "precio_unitario" forzado al precio base si no coincide con ningún precio real del catálogo y el usuario no es administrador.
     */
    private function enforceCatalogPriceForNonAdmins(Company $company, array $items, ?string $role, array $modules): array
    {
        if (User::hasCompanyAdminAccess($role, $modules)) {
            return $items;
        }

        $codes = collect($items)->pluck('codigo')->filter()->values()->all();
        $productsByCode = Product::where('company_id', (string) $company->_id)->whereIn('code', $codes)->get()->keyBy('code');

        return collect($items)->map(function (array $item) use ($productsByCode) {
            $product = $productsByCode->get($item['codigo'] ?? null);

            if (! $product) {
                return $item;
            }

            $allowedPrices = collect($product->extra_prices ?? [])
                ->pluck('price')
                ->push($product->unit_price)
                ->map(fn ($price) => round((float) $price, 2));

            $submittedPrice = round((float) ($item['precio_unitario'] ?? 0), 2);

            if (! $allowedPrices->contains($submittedPrice)) {
                $item['precio_unitario'] = (float) $product->unit_price;
            }

            return $item;
        })->values()->all();
    }

    /**
     * Traduce una línea ya validada del formulario ("items.*") al mismo
     * shape "InvoiceLine" que espera DocumentJsonMapper::mapLines(): un
     * TaxSubtotal por cada impuesto de la línea (antes solo se permitía
     * IVA), un AllowanceCharge de línea si el usuario puso descuento, y la
     * bodega elegida (campo propio, no es parte del estándar UBL) para que
     * IssueDocumentService::discountInventory() sepa de dónde descontar.
     *
     * @param  array  $item  Datos ya validados de "items.N".
     * @param  int  $index  Posición de la línea (0-indexed).
     * @return array Bloque "InvoiceLine.N" en el mismo shape que espera la API.
     */
    private function buildInvoiceLine(array $item, int $index): array
    {
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];
        $baseAmount = $cantidad * $precioUnitario;

        $line = [
            'ID' => (string) ($index + 1),
            'unitCode' => $item['unidad_medida'] ?? 'EA',
            'InvoicedQuantity' => $cantidad,
            'bodega_id' => $item['bodega_id'] ?? null,
            'Item' => array_filter([
                'Description' => $item['descripcion'],
                'SellersItemIdentification' => ['ID' => $item['codigo']],
                'StandardItemIdentification' => ! empty($item['codigo_barras']) ? ['ID' => $item['codigo_barras']] : null,
            ]),
            'Price' => [
                'PriceAmount' => $precioUnitario,
                'BaseQuantity' => 1,
            ],
        ];

        if (! empty($item['impuestos'])) {
            $line['TaxTotal'] = [
                'TaxSubtotal' => array_map(fn (array $impuesto) => array_filter([
                    'TaxableAmount' => isset($impuesto['base_gravable']) && $impuesto['base_gravable'] !== '' ? (float) $impuesto['base_gravable'] : null,
                    'TaxCategory' => [
                        'Percent' => (float) $impuesto['porcentaje'],
                        'TaxScheme' => ['ID' => $impuesto['tipo'], 'Name' => $impuesto['nombre'] ?? null],
                    ],
                ], fn ($value) => $value !== null), $item['impuestos']),
            ];
        }

        if (! empty($item['descuento_valor']) && (float) $item['descuento_valor'] > 0) {
            $esPorcentaje = ($item['descuento_valor_tipo'] ?? 'porcentaje') === 'porcentaje';
            
            $descuentoValor = $esPorcentaje
                ? min((float) $item['descuento_valor'], 100)
                : min((float) $item['descuento_valor'], $baseAmount);

            $line['AllowanceCharge'] = [
                'ChargeIndicator' => false,
                'AllowanceChargeReason' => $item['descuento_motivo'] ?? __('Discount'),
                'MultiplierFactorNumeric' => $esPorcentaje ? $descuentoValor : null,
                'Amount' => $esPorcentaje ? null : $descuentoValor,
            ];
        }

        return $line;
    }

    /**
     * Muestra el detalle de un documento emitido: datos del receptor, líneas,
     * totales, XML enviado y la respuesta de la DIAN.
     */
    public function show(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosEmitidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        [$customerDepartmentName, $customerCityName] = $this->resolveLocationNames(
            data_get($documento->payload, 'accounting_customer_party.departamento_codigo'),
            data_get($documento->payload, 'accounting_customer_party.ciudad_codigo'),
        );

        $warehouseNames = $company->warehouses()->get()->mapWithKeys(fn ($warehouse) => [(string) $warehouse->_id => $warehouse->name]);

        return view('documents.show', compact(
            'company',
            'documento',
            'paymentMeansCode',
            'customerDepartmentName',
            'customerCityName',
            'warehouseNames',
        ));
    }

    /**
     * Marca/desmarca una factura a crédito como pagada -- solo tiene
     * sentido para payment_means_id = credit (las de contado y las ventas
     * POS ya se consideran pagadas al momento de emitirse).
     */
    public function togglePaid(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosEmitidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);
        abort_unless($documento->is_credit, 422);

        $documento->paid_at = $documento->is_paid ? null : now();
        $documento->save();

        return back();
    }

    /**
     * Botón "Validar" del show del documento -- reintenta uno que quedó
     * pendiente o rechazado (ver IssueDocumentService::retry() para el
     * criterio: regla 90 vs reenvío completo). Solo tiene sentido para esos
     * dos estados; para uno ya aceptado no hace nada.
     */
    public function retry(Request $request, string $documento, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosEmitidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);
        abort_unless(in_array($documento->status, [DocumentoEmitido::STATUS_PENDING, DocumentoEmitido::STATUS_REJECTED], true), 422);

        try {
            $documento = $service->retry($company, $documento, (string) $request->user()->_id);
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()]);
        }

        if ($documento->status !== DocumentoEmitido::STATUS_ACCEPTED) {
            $reglas = $documento->status_message['reglas'] ?? [];
            $resumen = $documento->status_message['resumen'] ?? null;
            $message = $resumen ?: __('The DIAN rejected the document again.');

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'reglas' => $reglas], 422);
            }

            return back()->withErrors(['message' => $message]);
        }

        if ($request->wantsJson()) {
            return response()->json(['redirect_url' => route('documents.show', $documento->_id)]);
        }

        return redirect()->route('documents.show', $documento->_id);
    }

    /**
     * Recibo en PDF (formato angosto, tipo ticket) para descargar -- usado
     * sobre todo por el checkout del POS, pero disponible para cualquier
     * documento ya emitido. Reusa los mismos datos que documents.show(), sin
     * el detalle completo (XML, respuesta de la DIAN, etc.).
     */
    public function receiptPdf(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosEmitidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        $cashReceived = session('pos_cash_received');
        $isElectronic = true;
        $uuid = $documento->uuid;

        $pdf = Pdf::loadView('documents.receipt-pdf', compact(
            'company',
            'documento',
            'paymentMeansCode',
            'cashReceived',
            'isElectronic',
            'uuid',
        ))->setPaper([0, 0, 226.77, 800], 'portrait'); 

        return $pdf->download('recibo-' . $documento->numeral . '.pdf');
    }

    /**
     * Representación gráfica tamaño carta de un documento electrónico ya
     * emitido (factura, nota crédito/débito) -- a diferencia de receiptPdf()
     * (tirilla angosta, pensada para el POS), esta cumple el contenido
     * mínimo exigido por la DIAN para la representación gráfica de la
     * factura electrónica: https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/guia-de-uso-facturacion-gratuita-dian/
     * Solo aplica a documentos con CUFE/CUDE (emitidos electrónicamente);
     * las remisiones no tienen representación legal como factura. Se
     * muestra embebida (stream) en vez de forzar la descarga -- mismo
     * criterio que PosController::receiptPreview() y QuotationController::preview().
     */
    public function invoicePreview(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosEmitidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);
        abort_unless($documento->uuid, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        $qrDataUri = null;
        if ($documento->qr_validation_url) {
            $qrCode = new QrCode(data: $documento->qr_validation_url, size: 300, margin: 8);
            $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
        }

        $pdf = Pdf::loadView('documents.invoice-pdf', compact(
            'company',
            'documento',
            'paymentMeansCode',
            'qrDataUri',
        ))->setPaper('letter', 'portrait');

        return $pdf->stream($documento->numeral . '.pdf');
    }

    /**
     * Resuelve el nombre del departamento y el municipio a partir de sus
     * códigos DIAN, buscando en el catálogo de departamentos/municipios.
     *
     * @param  string|null  $departmentCode  Código DIAN del departamento.
     * @param  string|null  $cityCode  Código DIAN del municipio.
     * @return array{0: string|null, 1: string|null} Nombre del departamento y del municipio, o null si no se encontró.
     */
    private function resolveLocationNames(?string $departmentCode, ?string $cityCode): array
    {
        if (! $departmentCode) {
            return [null, null];
        }

        $department = Department::where('codigo', $departmentCode)->first();

        if (! $department) {
            return [null, null];
        }

        $city = collect($department->municipios ?? [])->firstWhere('codigo', $cityCode);

        return [$department->descripcion, $city['descripcion'] ?? null];
    }
}
