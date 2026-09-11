<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DocumentoRecibido;
use App\Models\ThirdParty;
use App\Services\Dian\ReceivedDocumentParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;

class DocumentoRecibidoController extends Controller
{
    // Mismos códigos DIAN que DocumentoEmitidoController::DOCUMENT_TYPE_LABELS_KEYS.
    private const DOCUMENT_TYPE_LABELS_KEYS = [
        '01' => 'Electronic sales invoice',
        '02' => 'Electronic sales invoice (export)',
        '03' => 'Electronic transmission instrument (type 03)',
        '04' => 'Electronic sales invoice (type 04)',
        '91' => 'Credit note',
        '92' => 'Debit note',
    ];

    /**
     * $documentos vacío a propósito: la tabla se llena por AJAX (ver data()) apenas termina de
     * cargar la página, en vez de bloquear el primer render con la consulta completa del
     * historial -- mismo patrón que DocumentoEmitidoController::index()/data().
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $documentos = collect();

        return view('received-documents.index', compact('company', 'documentos'));
    }

    /**
     * Lista los documentos recibidos por la empresa activa, más recientes primero, filtrados
     * directamente en la consulta (no en el navegador) -- con potencialmente decenas de miles de
     * documentos por empresa, traer todo de una para filtrar del lado del cliente no escala.
     * Todos los filtros son opcionales; sin ninguno, trae el historial completo (el frontend
     * siempre manda al menos "from"/"to" con el rango por defecto -- ver
     * $receivedDocumentsDefaultFrom/To en received-documents/index.blade.php -- así que en la
     * práctica esto nunca pasa desde esa pantalla). Devuelve los datos crudos en JSON -- el
     * frontend arma las celdas (ver received-documents/index.blade.php), el backend no arma HTML.
     *
     * @param  Request  $request  Query params opcionales: from, to (fecha "Y-m-d", sobre
     *                             issue_date), provider_id (_id del ThirdParty elegido en el
     *                             buscador -- ver providerSearch()), numeral, document_type
     *                             (código DIAN), payment_form ("contado"|"credito").
     */
    public function data(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = $company->documentosRecibidos()->with('proveedor');

        if ($request->filled('from')) {
            $query->where('issue_date', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('issue_date', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }

        if ($numeral = trim((string) $request->query('numeral', ''))) {
            $query->where('numeral', 'like', '%' . $numeral . '%');
        }

        if ($documentType = trim((string) $request->query('document_type', ''))) {
            $query->where('tipo_documento', $documentType);
        }

        if ($paymentForm = $request->query('payment_form', '')) {
            if ($paymentForm === 'credito') {
                $query->where('payment_means_id', DocumentoRecibido::PAYMENT_MEANS_CREDIT);
            } else {
                $query->where('payment_means_id', '!=', DocumentoRecibido::PAYMENT_MEANS_CREDIT);
            }
        }

        if ($providerId = trim((string) $request->query('provider_id', ''))) {
            $query->where('proveedor_id', $providerId);
        }

        $documentos = $query->orderByDesc('created_at')->get();

        $rows = $documentos->map(function (DocumentoRecibido $documento) {
            $emisor = $documento->payload['accounting_supplier_party'] ?? [];

            return [
                'id' => (string) $documento->_id,
                'issue_date' => optional($documento->issue_date)->format('Y-m-d'),
                'numeral' => $documento->numeral,
                'tipo_documento' => $documento->tipo_documento,
                'provider_name' => $documento->proveedor->name ?? ($emisor['razon_social'] ?? null),
                'provider_identification' => $emisor['identificacion'] ?? null,
                'total_formatted' => $documento->total_formatted,
                'status_label' => $documento->status_label,
                'status_badge_classes' => $documento->status_badge_classes,
                'payment_form' => $documento->is_credit ? 'credito' : 'contado',
                'payment_form_label' => $documento->is_credit ? __('Credit') : __('Cash'),
                'urls' => [
                    'pdf' => route('received-documents.pdf', $documento->_id),
                    'show' => route('received-documents.show', $documento->_id),
                ],
            ];
        });

        return response()->json([
            'rows' => $rows,
            'document_type_labels' => collect(self::DOCUMENT_TYPE_LABELS_KEYS)->mapWithKeys(fn ($labelKey, $code) => [$code => __($labelKey)]),
        ]);
    }

    /**
     * Endpoint AJAX: busca proveedores propios por nombre o identificación, para el buscador de
     * proveedor del filtro de la bandeja -- mismo criterio que
     * DocumentoEmitidoController::clientSearch() (ahí busca clientes, acá proveedores), no trae
     * de una todos los proveedores de la empresa (puede haber miles).
     */
    public function providerSearch(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['providers' => []]);
        }

        $providers = $company->providers()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%')
                    ->orWhere('identificacion', 'like', '%' . $query . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'providers' => $providers->map(fn (ThirdParty $provider) => [
                'id' => (string) $provider->_id,
                'name' => $provider->name,
                'identificacion' => $provider->identificacion,
            ])->values(),
        ]);
    }

    /**
     * Sube uno o varios documentos recibidos, uno por uno: cada archivo puede ser el XML suelto
     * o el .zip que casi siempre manda el proveedor (con el AttachedDocument y el PDF adentro --
     * ver ReceivedDocumentParser::parseUploadedFile()). Busca o crea el proveedor por
     * identificación (mismo criterio que ThirdPartyController::store(): si ya existe un tercero
     * con esa identificación, solo le agrega el rol "proveedor" en vez de duplicarlo) y guarda un
     * DocumentoRecibido por archivo. Valida que el documento de verdad esté dirigido a la
     * empresa activa (su NIT tiene que coincidir con el AccountingCustomerParty del XML) antes de
     * guardarlo -- si no, cualquiera podría subir la factura de otra empresa a su propia bandeja.
     * Si un archivo falla (XML inválido, .zip sin XML adentro, no es para esta empresa, formato
     * no soportado), se salta y se sigue con los demás -- no se aborta la subida completa por un
     * solo archivo malo. El AttachedDocument que manda la DIAN ya trae, en su propio
     * ParentDocumentLineReference, la respuesta de validación de la DIAN a ese documento (ver
     * ReceivedDocumentParser::extractDianValidation()) -- no hace falta consultarla aparte por
     * SOAP: si el código de validación es "02" (documento validado), el documento queda aceptado;
     * si no, queda pendiente de revisión manual.
     */
    public function store(Request $request, ReceivedDocumentParser $parser)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:xml,zip', 'max:10240'],
        ]);

        $creados = 0;
        $errores = [];

        foreach ($data['files'] as $file) {
            try {
                $extension = strtolower($file->getClientOriginalExtension());
                $parsed = $parser->parseUploadedFile($file->getRealPath(), $extension);

                $receptorNit = trim((string) ($parsed['payload']['accounting_customer_party']['identificacion'] ?? ''));
                if ($receptorNit !== trim((string) $company->identificacion)) {
                    $errores[] = __(':numeral: this document does not belong to :company, it belongs to identification :nit.', [
                        'numeral' => $parsed['numeral'] ?: $file->getClientOriginalName(),
                        'company' => $company->name,
                        'nit' => $receptorNit ?: '—',
                    ]);

                    continue;
                }

                if (DocumentoRecibido::where('company_id', (string) $company->_id)->where('uuid', $parsed['uuid'])->where('uuid', '!=', '')->exists()) {
                    $errores[] = __(':numeral: this document was already uploaded before (same UUID).', ['numeral' => $parsed['numeral'] ?: $file->getClientOriginalName()]);

                    continue;
                }

                $this->consumeContractQuota($company, 'receiving');

                $emisor = $parsed['payload']['accounting_supplier_party'];
                $proveedor = $this->resolveProveedor($company, $emisor);

                [$status, $statusMessage] = $this->resolveDianValidationStatus($parsed['dian_validation']);

                $payload = $parsed['payload'];
                $payload['lineas'] = $parsed['lineas'];
                $primerPago = $payload['payment_means_list'][0] ?? null;

                DocumentoRecibido::create([
                    'company_id' => (string) $company->_id,
                    'proveedor_id' => $proveedor ? (string) $proveedor->_id : null,
                    'uploaded_by' => (string) $request->user()->_id,
                    'tipo_documento' => $parsed['tipo_documento'],
                    'prefix' => $parsed['prefix'],
                    'numeral' => $parsed['numeral'],
                    'secuencial' => $parsed['secuencial'],
                    'payload' => $payload,
                    'xml' => $parsed['xml'],
                    'pdf' => $parsed['pdf'],
                    'file_name' => $file->getClientOriginalName(),
                    'uuid' => $parsed['uuid'],
                    'status' => $status,
                    'status_message' => $statusMessage,
                    'issue_date' => $parsed['issue_date'] ?: null,
                    'due_date' => $parsed['due_date'] ?: null,
                    'subtotal' => $parsed['subtotal'],
                    'tax_total' => $parsed['tax_total'],
                    'total' => $parsed['total'],
                    'currency' => $parsed['currency'],
                    'payment_means_id' => $primerPago['id'] ?? null,
                    'payment_means_code' => $primerPago['codigo'] ?? null,
                ]);

                $creados++;
            } catch (InvalidArgumentException|RuntimeException $e) {
                $errores[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        if ($creados > 0) {
            session()->flash('toast', [
                'type' => empty($errores) ? 'success' : 'warning',
                'message' => trans_choice(':count document uploaded.|:count documents uploaded.', $creados, ['count' => $creados]),
            ]);
        }

        if (! empty($errores)) {
            session()->flash('received-documents-errors', $errores);
        }

        return redirect()->route('received-documents.index');
    }

    /**
     * Busca un proveedor existente por identificación (mismo NIT que en emisión, sin importar
     * el rol que ya tenga) y le agrega el rol "proveedor" si no lo tenía; si no existe ninguno,
     * lo crea con los datos que vinieron en el XML. Si el documento no trae identificación (XML
     * mal formado), no crea nada y el documento queda sin proveedor enlazado.
     */
    private function resolveProveedor(Company $company, array $emisor): ?ThirdParty
    {
        $identificacion = $emisor['identificacion'] ?? '';

        if ($identificacion === '') {
            return null;
        }

        $proveedor = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identificacion', $identificacion)
            ->first();

        if ($proveedor) {
            $proveedor->update([
                'roles' => collect($proveedor->roles ?? [])->push('proveedor')->unique()->values()->all(),
            ]);

            return $proveedor;
        }

        return ThirdParty::create([
            'company_id' => (string) $company->_id,
            'identification_type' => '31',
            'identificacion' => $identificacion,
            'dv' => $emisor['dv'] ?: null,
            'person_type' => '2',
            'name' => $emisor['razon_social'] ?: $identificacion,
            'address' => $emisor['direccion'] ?: null,
            'city_code' => $emisor['ciudad_codigo'] ?: null,
            'department_code' => $emisor['departamento_codigo'] ?: null,
            'phone' => $emisor['telefono'] ?: null,
            'email' => $emisor['email'] ?: null,
            'roles' => ['proveedor'],
            'status' => 'active',
        ]);
    }

    /**
     * Reclama un documento contra el cupo del contrato de la empresa para el módulo
     * "receiving" -- mismo criterio que IssueDocumentService::consumeContractQuota() para
     * invoicing/pos/cotizaciones, uno por documento subido (no por lote).
     *
     * @throws RuntimeException Si la empresa no tiene contrato vigente para este módulo, o si ya no queda cupo.
     */
    private function consumeContractQuota(Company $company, string $module): void
    {
        $contract = $company->activeContractFor($module);

        if (! $contract) {
            throw new RuntimeException(__('This company has no active contract covering this module.'));
        }

        $contract->claimUsage($module, (string) $company->_id);
    }

    /**
     * Traduce la validación de la DIAN que ya viene en el AttachedDocument (ver
     * ReceivedDocumentParser::extractDianValidation()) al status/status_message que se guardan en
     * DocumentoRecibido -- mismo shape ("resumen"/"reglas") que usa DocumentoEmitido para el
     * mensaje de la DIAN, así ambas pantallas .show() lo pintan igual. Si el XML subido no traía
     * ninguna validación (era el documento suelto, sin el sobre AttachedDocument, o la DIAN
     * todavía no había respondido cuando se armó el correo), el documento queda pendiente sin
     * mensaje -- no es un error, solo no hay con qué confirmarlo todavía.
     *
     * @param  array|null  $dianValidation
     * @return array{0: int, 1: array|null} [DocumentoRecibido::STATUS_*, status_message].
     */
    private function resolveDianValidationStatus(?array $dianValidation): array
    {
        if (! $dianValidation) {
            return [DocumentoRecibido::STATUS_PENDING, null];
        }

        $status = $dianValidation['es_valido']
            ? DocumentoRecibido::STATUS_ACCEPTED
            : DocumentoRecibido::STATUS_PENDING;

        $statusMessage = [
            'resumen' => $dianValidation['response_description'] ?: $dianValidation['response_code'],
            'reglas' => $dianValidation['reglas'],
        ];

        return [$status, $statusMessage];
    }

    /**
     * Detalle de un documento recibido: datos del proveedor, totales y el XML original.
     */
    public function show(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosRecibidos()->with('proveedor')->where('_id', $documento)->first();

        abort_unless($documento, 404);

        return view('received-documents.show', compact('company', 'documento'));
    }

    /**
     * Marca/desmarca un documento a crédito como pagado (por esta empresa, como comprador) --
     * mismo criterio que DocumentoEmitidoController::togglePaid() del lado emisión.
     */
    public function togglePaid(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosRecibidos()->where('_id', $documento)->first();

        abort_unless($documento, 404);
        abort_unless($documento->is_credit, 422);

        $documento->paid_at = $documento->is_paid ? null : now();
        $documento->save();

        return back();
    }

    /**
     * Representación en PDF del documento: el PDF que trajo el proveedor si venía en el .zip que
     * subieron (la mayoría de las veces), o si no, una representación genérica armada por
     * Billingo con los datos que sí se alcanzaron a parsear del XML (proveedor y totales, sin
     * líneas -- ver received-documents/generated-pdf.blade.php) -- el PDF es opcional en la
     * subida, nunca bloquea guardar el documento.
     */
    public function pdf(Request $request, string $documento)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosRecibidos()->with('proveedor')->where('_id', $documento)->first();

        abort_unless($documento, 404);

        if ($documento->pdf) {
            return new Response(base64_decode($documento->pdf), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $documento->numeral . '.pdf"',
            ]);
        }

        $pdf = Pdf::loadView('received-documents.generated-pdf', compact('documento'))->setPaper('letter', 'portrait');

        return $pdf->stream($documento->numeral . '.pdf');
    }
}
