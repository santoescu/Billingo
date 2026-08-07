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
use App\Models\ThirdParty;
use App\Models\User;
use App\Services\Dian\IssueDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Pantalla de venta rápida del POS: grid de productos + carrito simple (ver
 * pos/sell.blade.php), pensada para que un cajero venda rápido sin llenar el
 * formulario completo de facturación. Reusa la validación/armado del
 * documento de DocumentoEmitidoController::issuePosSale(), solo agrega lo
 * propio del POS -- exigir turno de caja abierto, registrar el movimiento de
 * caja de la venta, y mandar al recibo en PDF en vez de a la pantalla de
 * detalle del documento.
 */
class PosController extends Controller
{
    public function create(Request $request)
    {
        $company = $this->currentCompany($request);

        $shift = $this->openShiftFor($company, $request);

        if (! $shift) {
            return redirect()->route('pos.shift');
        }

        $paymentMethods = $company->paymentMethods()->orderBy('name')->get();

        // Mismo catálogo inicial que ProductSearch::productSearch() sin
        // texto de búsqueda (los primeros productos, para que el grid no
        // arranque vacío) -- se reusa esa misma ruta AJAX cuando el cajero
        // escribe en el buscador.
        $products = $company->products()->active()->orderBy('description')->limit(60)->get();

        return view('pos.sell', [
            'shift' => $shift,
            'company' => $company,
            'paymentMethods' => $paymentMethods,
            'products' => $products,
            'defaultClient' => $this->defaultClient($company),
            'departments' => Department::orderBy('descripcion')->get(),
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
        ]);
    }

    /**
     * "Consumidor final" propio de la empresa: se crea una sola vez (la
     * primera venta que lo necesite) con la identificación genérica que usa
     * el comercio colombiano para mostrador, para que siempre haya un
     * cliente válido y ya existente en third_parties sin obligar al cajero a
     * buscar o crear uno en cada venta -- ver DocumentJsonMapper::resolveCustomerParty(),
     * que solo exige los datos completos del cliente si todavía no existe.
     */
    private function defaultClient(Company $company): ThirdParty
    {
        $client = $company->clients()->where('identificacion', '222222222222')->first();

        if ($client) {
            return $client;
        }

        return ThirdParty::create([
            'company_id' => (string) $company->_id,
            'roles' => ['cliente'],
            'name' => __('Final consumer'),
            'identification_type' => '13',
            'identificacion' => '222222222222',
            'person_type' => '2',
        ]);
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

        // Un cajero solo ve su propia caja acá (una sola tarjeta, la suya, si
        // tiene turno abierto); el owner o un administrador del módulo POS
        // ve TODAS las cajas abiertas de la empresa -- la seguridad de qué
        // cajas puede ver cada quién se decide acá, del lado del servidor,
        // no ocultando tarjetas con CSS.
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

        // Solo se registra el primer medio de pago para efectos de caja: el
        // POS de venta rápida no soporta hoy dividir una venta entre varios
        // medios de pago distintos. Se usa el código DIAN ya resuelto en el
        // documento (a partir del PaymentMethod elegido, ver
        // DocumentoEmitidoController::issuePosSale()), no un input crudo --
        // solo así CashMovement::affectsCashBalance() sabe si de verdad fue
        // en efectivo (código '10'), sin importar cómo se llame el medio de
        // pago propio de la empresa.
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
            // Guardado por venta (no flash()): el modal de resultado puede
            // pedir este mismo recibo varias veces (vista previa, imprimir,
            // descargar), cada una en una petición HTTP aparte -- flash()
            // solo sobrevive una petición más, así que se perdería antes de
            // la segunda o tercera vez que se pide el mismo PDF.
            session()->put('pos_cash_received.' . $documentoPos->_id, $cashReceived);
        }

        return response()->json([
            'sale_id' => (string) $documentoPos->_id,
            'numeral' => $documentoPos->numeral,
            'total_formatted' => $documentoPos->total_formatted,
            'can_issue_electronic' => (bool) $shift->invoicing_resolution_id,
            'receipt_url' => route('pos.sales.receipt-pdf', $documentoPos->_id),
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

        // La venta rápida del POS permite un cliente mínimo (solo nombre e
        // identificación, ver pos/sell.blade.php); una factura electrónica sí
        // necesita dirección/ciudad/departamento completos -- se valida acá,
        // no al crear la venta, para no frenar la venta talonario por un dato
        // que solo hace falta si de verdad se termina emitiendo electrónica.
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

        return view('pos.sales.show', compact('company', 'documento', 'paymentMeansCode'));
    }

    public function receiptPdf(Request $request, string $sale)
    {
        $company = $this->currentCompany($request);

        $documento = $company->documentosPos()->where('_id', $sale)->first();

        abort_unless($documento, 404);

        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        // El efectivo recibido solo existe si el checkout se acaba de hacer
        // con pago en efectivo -- guardado por venta (ver checkout()), para
        // que siga apareciendo sin importar cuántas veces se pida este mismo
        // recibo (vista previa, imprimir, descargar son peticiones aparte).
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
        ))->setPaper([0, 0, 226.77, 800], 'portrait'); // ~80mm de ancho, alto libre

        $filename = 'recibo-' . $documento->numeral . '.pdf';

        // "inline=1": para la vista previa en el modal de resultado del POS
        // (iframe) y el botón "Imprimir" (pestaña nueva) -- el navegador lo
        // muestra en vez de forzar la descarga. El botón "Descargar" pega
        // directo a esta misma URL sin el parámetro.
        return $request->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    private function openShiftFor($company, Request $request): ?CashShift
    {
        return CashShift::where('company_id', (string) $company->_id)
            ->where('user_id', (string) $request->user()->_id)
            ->open()
            ->first();
    }
}
