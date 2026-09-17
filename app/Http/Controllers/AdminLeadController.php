<?php

namespace App\Http\Controllers;

use App\Mail\LeadOutreachMail;
use App\Models\Lead;
use App\Models\LeadEmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Prospección de Billingo mismo (correo en frío a negocios que todavía no son clientes) -- ver
 * Lead/LeadEmailLog. Solo para superadmin, no tiene nada que ver con las empresas que ya usan la
 * plataforma.
 */
class AdminLeadController extends Controller
{
    /**
     * Alias por encabezado -> campo del modelo. Cubre tanto el formato simple en inglés (para
     * listas armadas a mano) como el de un export real de cámara de comercio (columnas en
     * español, con tildes/mayúsculas) -- normalizado sin acentos y en minúscula antes de
     * comparar, así "Nombre/Razón Social" y "razon_social" caen en el mismo alias.
     */
    private const COLUMN_ALIASES = [
        'nit' => 'nit',
        'nro. identificacion' => 'nit',
        'nro identificacion' => 'nit',
        'no. identificacion' => 'nit',
        'razon_social' => 'razon_social',
        'nombre/razon social' => 'razon_social',
        'nombre' => 'razon_social',
        'contact_name' => 'contact_name',
        'representante' => 'contact_name',
        'email' => 'email',
        'correo' => 'email',
        'city' => 'city',
        'municipio' => 'city',
        'ciudad' => 'city',
        'barrio comercial' => 'neighborhood',
        'neighborhood' => 'neighborhood',
        'direccion' => 'address',
        'address' => 'address',
        'sector' => 'sector',
        'tipo org.' => 'sector',
        'tipo org' => 'sector',
        'size' => 'size',
        'tamano' => 'size',
        'registered_at' => 'registered_at',
        'fecha matricula' => 'registered_at',
        'pitch_note' => 'pitch_note',
    ];

    /**
     * $leads vacío a propósito -- la tabla se llena por AJAX (ver data()) apenas termina de
     * cargar la página, mismo patrón que DocumentoRecibidoController::index()/data() (DataTable
     * con paginación propia estilo Preline, ver partials/datatable-pagination.blade.php).
     */
    public function index()
    {
        return view('admin.leads.index', [
            'variants' => [
                LeadOutreachMail::VARIANT_INITIAL => __('Initial contact'),
                LeadOutreachMail::VARIANT_FOLLOWUP => __('Follow-up'),
                LeadOutreachMail::VARIANT_BREAKUP => __('Breakup (last touch)'),
                LeadOutreachMail::VARIANT_CUSTOM => __('Custom message'),
            ],
            // Ya envueltos en "{{...}}" acá, no en el .blade.php -- escribir el texto literal
            // "{{" ahí (incluso dentro de un comentario o un bloque @php) le hace perder el
            // hilo al compilador de Blade y se traga el resto del archivo en silencio.
            'mergeVariableTokens' => array_map(
                fn ($variable) => '{{' . $variable . '}}',
                array_keys((new Lead())->mergeVariables())
            ),
            'statuses' => [
                'not_contacted' => __('Not contacted'),
                'sent' => __('Sent'),
                'delivered' => __('Delivered'),
                'opened' => __('Opened'),
                'clicked' => __('Clicked'),
                'bounced' => __('Bounced'),
                'spam' => __('Spam'),
            ],
        ]);
    }

    /**
     * Trae todos los prospectos de una (no hay filtros de servidor que lo justifiquen, a
     * diferencia de documentos recibidos) -- DataTables pagina/busca del lado del cliente sobre
     * este mismo arreglo ya cargado.
     */
    public function data()
    {
        $leads = Lead::orderByDesc('created_at')->get();

        $latestLogs = LeadEmailLog::whereIn('lead_id', $leads->pluck('_id')->map(fn ($id) => (string) $id)->all())
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('lead_id')
            ->map(fn ($logs) => $logs->first());

        $rows = $leads->map(function (Lead $lead) use ($latestLogs) {
            $lastLog = $latestLogs->get((string) $lead->_id);

            return [
                'id' => (string) $lead->_id,
                'razon_social' => $lead->razon_social,
                'meta' => collect([$lead->sector, $lead->size, $lead->registered_at?->format('Y-m-d')])->filter()->implode(' · ') ?: null,
                'nit' => $lead->nit,
                'email' => $lead->email,
                'city' => $lead->city,
                'status_code' => $this->statusCode($lastLog),
                'status_label' => $lastLog?->status_label ?? __('Not contacted'),
                'status_badge_classes' => $lastLog?->status_badge_classes ?? 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
                'status_reason' => $lastLog?->bounce_reason ?? $lastLog?->complaint_reason,
                'sent_at' => $lastLog?->sent_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
                'urls' => [
                    'destroy' => route('admin.leads.destroy', $lead->_id),
                    'history' => route('admin.leads.history', $lead->_id),
                ],
            ];
        });

        return response()->json(['rows' => $rows]);
    }

    /**
     * Historial completo de envíos a un lead (no solo el último, a diferencia de data()) -- mismo
     * criterio que el "Historial de correo" de documents/show.blade.php, para poder ver cuándo se
     * mandó, entregó, abrió, dio clic o rebotó cada intento, no solo el más reciente.
     */
    public function history(string $lead)
    {
        $logs = LeadEmailLog::where('lead_id', $lead)->orderByDesc('sent_at')->get();

        $variantLabels = [
            LeadOutreachMail::VARIANT_INITIAL => __('Initial contact'),
            LeadOutreachMail::VARIANT_FOLLOWUP => __('Follow-up'),
            LeadOutreachMail::VARIANT_BREAKUP => __('Breakup (last touch)'),
            LeadOutreachMail::VARIANT_CUSTOM => __('Custom message'),
        ];

        $rows = $logs->map(fn (LeadEmailLog $log) => [
            'subject' => $log->subject,
            'variant_label' => $variantLabels[$log->variant] ?? $log->variant,
            'sent_at' => $log->sent_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'delivered_at' => $log->delivered_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'opened_at' => $log->opened_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'clicked_at' => $log->clicked_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'bounced_at' => $log->bounced_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'bounce_reason' => $log->bounce_reason,
            'complained_at' => $log->complained_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'complaint_reason' => $log->complaint_reason,
        ]);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Código en inglés sin acentos (a diferencia de status_label, que ya viene traducido) para
     * que el filtro de estado del front (ver admin/leads/index.blade.php) compare por valor
     * estable en vez de por el texto visible -- misma prioridad que
     * LeadEmailLog::getStatusLabelAttribute().
     */
    private function statusCode(?LeadEmailLog $lastLog): string
    {
        return match (true) {
            ! $lastLog => 'not_contacted',
            (bool) $lastLog->complained_at => 'spam',
            (bool) $lastLog->bounced_at => 'bounced',
            (bool) $lastLog->clicked_at => 'clicked',
            (bool) $lastLog->opened_at => 'opened',
            (bool) $lastLog->delivered_at => 'delivered',
            default => 'sent',
        };
    }

    /**
     * Importa prospectos desde un .csv/.xlsx -- reconoce los encabezados por nombre (ver
     * COLUMN_ALIASES), sin importar el orden ni si vienen en inglés (formato simple armado a
     * mano) o en español (export real de una cámara de comercio). Se omiten filas sin email o
     * con un email que ya existe.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (empty($rows)) {
            return back()->with('leads-errors', [__('The file has no rows.')]);
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rows[0]);
        $columnIndex = [];
        foreach ($headers as $index => $header) {
            if (isset(self::COLUMN_ALIASES[$header]) && ! isset($columnIndex[self::COLUMN_ALIASES[$header]])) {
                $columnIndex[self::COLUMN_ALIASES[$header]] = $index;
            }
        }

        if (! isset($columnIndex['email']) || ! isset($columnIndex['razon_social'])) {
            return back()->with('leads-errors', [__('The file must have at least "razon_social" and "email" columns.')]);
        }

        $existingEmails = Lead::pluck('email')->map(fn ($email) => mb_strtolower((string) $email))->all();

        $created = 0;
        $skipped = 0;

        foreach (array_slice($rows, 1) as $row) {
            $email = trim((string) ($row[$columnIndex['email']] ?? ''));

            if ($email === '' || in_array(mb_strtolower($email), $existingEmails, true)) {
                $skipped++;

                continue;
            }

            $data = ['email' => $email];
            foreach ($columnIndex as $column => $index) {
                if ($column === 'email') {
                    continue;
                }

                $value = trim((string) ($row[$index] ?? ''));

                if ($column === 'registered_at') {
                    $data[$column] = $this->parseDate($value);

                    continue;
                }

                $data[$column] = $value ?: null;
            }

            Lead::create($data + ['imported_by' => (string) $request->user()->_id]);
            $existingEmails[] = mb_strtolower($email);
            $created++;
        }

        return back()->with('leads-imported', ['created' => $created, 'skipped' => $skipped]);
    }

    /**
     * Asunto + cuerpo real que se va a mandar, para mostrarlo en el modal de confirmación antes
     * de enviar (ver admin/leads/index.blade.php) -- usa el primer lead seleccionado para que la
     * vista previa refleje el nombre de contacto y el pitch_note reales, no un ejemplo genérico.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'variant' => ['required', 'string', 'in:' . implode(',', [
                LeadOutreachMail::VARIANT_INITIAL,
                LeadOutreachMail::VARIANT_FOLLOWUP,
                LeadOutreachMail::VARIANT_BREAKUP,
                LeadOutreachMail::VARIANT_CUSTOM,
            ])],
            'lead_id' => ['nullable', 'string'],
            'custom_subject' => ['required_if:variant,' . LeadOutreachMail::VARIANT_CUSTOM, 'nullable', 'string'],
            'custom_body' => ['required_if:variant,' . LeadOutreachMail::VARIANT_CUSTOM, 'nullable', 'string'],
        ]);

        $lead = ! empty($data['lead_id']) ? Lead::find($data['lead_id']) : null;
        $lead ??= new Lead(['razon_social' => __('Example company'), 'email' => 'ejemplo@empresa.com']);

        $mailable = new LeadOutreachMail($lead, $data['variant'], 'preview@billingo.com.co', $data['custom_subject'] ?? null, $data['custom_body'] ?? null);

        $mergedCustomBody = $data['variant'] === LeadOutreachMail::VARIANT_CUSTOM
            ? $lead->fillMergeVariables($data['custom_body'])
            : null;

        return response()->json([
            'subject' => $mailable->envelope()->subject,
            'body' => trim(view('emails.leads.outreach-text', ['lead' => $lead, 'variant' => $data['variant'], 'mergedCustomBody' => $mergedCustomBody])->render()),
        ]);
    }

    /**
     * Manda LeadOutreachMail (una de sus 3 variantes) a los prospectos seleccionados,
     * sincrónico (no en cola, ver LeadOutreachMail) -- se manda uno por uno y se guarda el
     * Message-ID real de SES de una, igual que se hacía con los documentos antes de encolarlos.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'lead_ids' => ['required', 'array'],
            'lead_ids.*' => ['required', 'string'],
            'variant' => ['required', 'string', 'in:' . implode(',', [
                LeadOutreachMail::VARIANT_INITIAL,
                LeadOutreachMail::VARIANT_FOLLOWUP,
                LeadOutreachMail::VARIANT_BREAKUP,
                LeadOutreachMail::VARIANT_CUSTOM,
            ])],
            'reply_to' => ['required', 'email'],
            'custom_subject' => ['required_if:variant,' . LeadOutreachMail::VARIANT_CUSTOM, 'nullable', 'string'],
            'custom_body' => ['required_if:variant,' . LeadOutreachMail::VARIANT_CUSTOM, 'nullable', 'string'],
        ]);

        $leads = Lead::whereIn('_id', $data['lead_ids'])->get();

        foreach ($leads as $lead) {
            $mailable = new LeadOutreachMail($lead, $data['variant'], $data['reply_to'], $data['custom_subject'] ?? null, $data['custom_body'] ?? null);
            $sent = Mail::to($lead->email)->send($mailable);

            $sesMessageId = $sent?->getOriginalMessage()->getHeaders()->get('X-SES-Message-ID')?->getBodyAsString();

            LeadEmailLog::create([
                'lead_id' => (string) $lead->_id,
                'to' => $lead->email,
                'subject' => $mailable->envelope()->subject,
                'variant' => $data['variant'],
                'ses_message_id' => $sesMessageId,
                'sent_at' => now(),
            ]);
        }

        return back()->with('leads-sent', $leads->count());
    }

    public function destroy(string $lead)
    {
        Lead::where('_id', $lead)->delete();

        return back();
    }

    /**
     * Quita tildes y pasa a minúscula/recortado, para que "Nombre/Razón Social" y
     * "nombre/razon social" (o cualquier variación de mayúsculas) caigan en el mismo alias.
     */
    private function normalizeHeader(string $value): string
    {
        $withoutAccents = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);

        return Str::of($withoutAccents)->lower()->trim()->toString();
    }

    /**
     * "Fecha Matrícula" viene como "2026-05-11" en el export real de cámara de comercio -- se
     * guarda tal cual si ya es un formato que Carbon reconoce; si no, se descarta (mejor sin
     * fecha que con una fecha mal parseada).
     */
    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
