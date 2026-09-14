<?php

namespace App\Http\Controllers;

use App\Models\DocumentoRecibido;
use App\Models\ThirdParty;
use App\Services\Dian\ReceivedDocumentIngestionService;
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

        // Solo genera el token (y por lo tanto el alias) si el canal por correo ya está
        // configurado -- mientras no haya dominio de recepción, no tiene sentido guardarle un
        // token a cada empresa que visite la bandeja.
        if (config('services.ses.inbound_domain') && ! $company->reception_email_token) {
            $company->ensureReceptionEmailToken();
        }

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
    public function store(Request $request, ReceivedDocumentIngestionService $ingestionService)
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
                $ingestionService->ingest($company, $file->getRealPath(), $extension, $file->getClientOriginalName(), (string) $request->user()->_id);

                $creados++;
            } catch (InvalidArgumentException|RuntimeException $e) {
                $errores[] = $e->getMessage();
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
     * líneas -- ver Company::resolvePdfView() y custom/general/received-pdf.blade.php) -- el
     * PDF es opcional en la subida, nunca bloquea guardar el documento.
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

        $pdf = Pdf::loadView($company->resolvePdfView('received-pdf'), compact('documento'))->setPaper('letter', 'portrait');

        return $pdf->stream($documento->numeral . '.pdf');
    }
}
