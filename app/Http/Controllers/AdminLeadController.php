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
                'status_label' => $lastLog?->status_label ?? __('Not contacted'),
                'status_badge_classes' => $lastLog?->status_badge_classes ?? 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
                'sent_at' => $lastLog?->sent_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
                'urls' => [
                    'destroy' => route('admin.leads.destroy', $lead->_id),
                ],
            ];
        });

        return response()->json(['rows' => $rows]);
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
            ])],
            'reply_to' => ['required', 'email'],
        ]);

        $leads = Lead::whereIn('_id', $data['lead_ids'])->get();

        foreach ($leads as $lead) {
            $mailable = new LeadOutreachMail($lead, $data['variant'], $data['reply_to']);
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
