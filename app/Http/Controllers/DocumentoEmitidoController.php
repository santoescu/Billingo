<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentoEmitido;
use App\Models\FiscalResponsibility;
use App\Models\MeasurementUnit;
use App\Models\PaymentMeansCode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\Resolution;
use App\Models\Warehouse;
use App\Services\Dian\DianSoapClient;
use App\Services\Dian\IssueDocumentService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Consulta (solo lectura) y emisión de documentos electrónicos desde el
 * panel web (facturas): la consulta filtra por la empresa activa y su
 * ambiente DIAN actual; la emisión arma el mismo JSON que espera la API
 * (ver Api\DocumentoController) y usa el mismo IssueDocumentService, para no
 * duplicar la lógica de facturación entre la web y la API.
 */
class DocumentoEmitidoController extends Controller
{
    private const FACTURA_CODES = ['01', '02', '03', '04'];

    // Solo factura de venta nacional y de exportación son referenciables desde
    // una nota: 03/04 (contingencia) comparten rango de numeración pero no
    // tiene sentido dejarlas como origen de una nota crédito/débito aquí.
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

        return view('documents.index', compact('company', 'documentos'));
    }

    /**
     * Muestra el formulario para emitir un documento desde el panel web, con
     * los catálogos necesarios (productos, medios de pago,
     * departamentos/ciudades, responsabilidades fiscales). Las resoluciones,
     * facturas referenciables y clientes se cargan por AJAX (ver
     * createOptions()/clientSearch()) para no recargar la página ni cargar
     * de una vez catálogos que pueden tener miles de registros.
     */
    public function create(Request $request)
    {
        $company = $this->currentCompany($request);

        // Los productos ya no se cargan aquí de una vez: se buscan por AJAX
        // mientras se escribe una línea (ver productSearch()), igual que los
        // clientes (clientSearch()) -- puede haber miles.
        $paymentMeansCodes = PaymentMeansCode::orderBy('medio')->get();
        $measurementUnits = MeasurementUnit::orderBy('descripcion')->get();
        $departments = Department::orderBy('descripcion')->get();
        $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();

        return view('documents.create', compact(
            'company',
            'paymentMeansCodes',
            'measurementUnits',
            'departments',
            'fiscalResponsibilities',
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
            'clients' => $clients->map(fn ($client) => [
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
            ])->values(),
        ]);
    }

    /**
     * Endpoint AJAX: busca productos por código, código de barras o
     * descripción mientras el usuario escribe una línea del documento (con
     * miles de productos, no se puede cargar todo el catálogo de una vez).
     * Trae, por cada coincidencia, sus precios (con el nombre del tipo) y
     * sus bodegas con stock (con el nombre), más una entrada "sin asignar"
     * si el producto controla inventario.
     */
    public function productSearch(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['products' => []]);
        }

        $products = $company->products()->active()
            ->where(function ($builder) use ($query) {
                $builder->where('code', 'like', '%' . $query . '%')
                    ->orWhere('barcode', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->orderBy('description')
            ->limit(20)
            ->get();

        $priceTypeIds = $products->flatMap(fn ($product) => collect($product->extra_prices ?? [])->pluck('price_type_id'))->unique()->values()->all();
        $warehouseIds = $products->flatMap(fn ($product) => collect($product->warehouse_stocks ?? [])->pluck('warehouse_id'))->unique()->values()->all();

        $priceTypesById = PriceType::whereIn('_id', $priceTypeIds)->get()->keyBy(fn ($priceType) => (string) $priceType->_id);
        $warehousesById = Warehouse::whereIn('_id', $warehouseIds)->get()->keyBy(fn ($warehouse) => (string) $warehouse->_id);

        return response()->json([
            'products' => $products->map(function (Product $product) use ($priceTypesById, $warehousesById) {
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

                // Siempre se incluye "sin asignar" (aunque el producto no
                // controle inventario), para que el selector de bodega
                // siempre tenga algo que mostrar en el formulario.
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
                    'prices' => $prices,
                    'warehouses' => $warehouses,
                ];
            })->values(),
        ]);
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
     * (carga inicial) y createOptions() (AJAX al cambiar el tipo de
     * documento en el formulario).
     */
    private function resolutionsFor(Company $company, string $tipoDocumento): \Illuminate\Support\Collection
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
    public function store(Request $request, IssueDocumentService $service)
    {
        $company = $this->currentCompany($request);

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

        $resolution = Resolution::where('company_id', (string) $company->_id)
            ->where('_id', $data['resolution_id'])
            ->first();

        if (! $resolution) {
            return back()->withErrors(['message' => __('The selected resolution is not valid.')])->withInput();
        }

        $data['prefix'] = $resolution->prefix;
        $data['secuencial'] = $resolution->current_number ?: $resolution->range_from;

        $document = $this->buildDocumentJson($company, $data);

        try {
            $documento = $service->issue($company, ['document' => $document]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['message' => __('Could not issue the document.')])->withInput();
        }

        session()->flash('toast', [
            'type' => $documento->status === DocumentoEmitido::STATUS_ACCEPTED ? 'success' : 'error',
            'message' => $documento->status === DocumentoEmitido::STATUS_ACCEPTED
                ? __('Document issued and accepted by the DIAN.')
                : __('The document was sent, but the DIAN did not accept it yet. Check the details.'),
        ]);

        return redirect()->route('documents.show', $documento->_id);
    }

    /**
     * Traduce los datos ya validados del formulario al mismo shape de JSON
     * que espera la API (document.*), para reusar DocumentJsonMapper tal
     * cual sin duplicar su lógica de resolución/creación de cliente.
     *
     * @param  Company  $company  Empresa emisora (dueña de la sesión activa).
     * @param  array  $data  Datos ya validados de la petición.
     * @return array Bloque "document" en el mismo shape que espera la API.
     */
    private function buildDocumentJson(Company $company, array $data): array
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
                'direccion' => $data['cliente_direccion'],
                'cityCode' => $data['cliente_ciudad_codigo'],
                'CountrySubentityCode' => $data['cliente_departamento_codigo'],
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
                // La referencia de pago ya no la escribe el usuario: se numera
                // sucesivamente según la posición entre los medios de pago
                // realmente enviados (1, 2, 3...).
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
            // Tope del lado del servidor (no solo visual): un porcentaje
            // nunca puede pasar de 100, y un valor fijo nunca puede superar
            // el subtotal de la línea (si no, el total de la línea quedaría
            // negativo).
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

        return view('documents.show', compact(
            'company',
            'documento',
            'paymentMeansCode',
            'customerDepartmentName',
            'customerCityName',
        ));
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
