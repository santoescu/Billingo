<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use App\Models\Company;
use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSupportTicketController extends Controller
{
    /**
     * Panorama general de soporte: cuántos tickets hay por estado, el
     * desglose por módulo, el tiempo promedio hasta la primera respuesta
     * del staff, y la satisfacción promedio de los que ya se calificaron
     * (ver SupportTicketController::submitSatisfaction()). Todo calculado
     * en PHP sobre la colección ya filtrada por rango de fechas -- mismo
     * criterio que index(), que ya trae los tickets a memoria para
     * filtrar/ordenar; si el volumen crece mucho, ahí habría que revisar
     * los dos a la vez.
     *
     * El rango filtra por "created_at" (cuándo se abrió el ticket), no por
     * última actividad -- así un ticket abierto en agosto pero cerrado en
     * septiembre sigue contando para agosto al comparar mes a mes.
     */
    public function dashboard(Request $request)
    {
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : null;

        $query = SupportTicket::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $tickets = $query->get();

        $totalOpen = $tickets->where('status', SupportTicket::STATUS_OPEN)->count();
        $totalAssigned = $tickets->where('status', SupportTicket::STATUS_ASSIGNED)->count();
        $totalClosed = $tickets->where('status', SupportTicket::STATUS_CLOSED)->count();

        $byModule = $tickets
            ->groupBy(fn (SupportTicket $ticket) => $ticket->module ?: 'general')
            ->map(fn ($group, $module) => [
                'label' => $module === 'general' ? __('General') : (config('modules')[$module]['name'] ?? $module),
                'badge_classes' => config("modules.$module.badge_classes", 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-200'),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        // Primer mensaje del staff por ticket -- el tiempo de respuesta se
        // mide desde que se creó el ticket hasta esa primera respuesta, no
        // hasta cualquier mensaje de staff (uno de seguimiento no cuenta).
        $firstStaffReplyByTicket = SupportTicketMessage::where('author_role', SupportTicketMessage::AUTHOR_STAFF)
            ->orderBy('created_at')
            ->get()
            ->groupBy('support_ticket_id')
            ->map(fn ($messages) => $messages->first());

        $responseMinutes = $tickets
            ->map(function (SupportTicket $ticket) use ($firstStaffReplyByTicket) {
                $firstReply = $firstStaffReplyByTicket->get((string) $ticket->_id);

                if (! $firstReply || ! $ticket->created_at) {
                    return null;
                }

                return $ticket->created_at->diffInMinutes($firstReply->created_at);
            })
            ->filter(fn ($minutes) => $minutes !== null);

        $avgResponseMinutes = $responseMinutes->isNotEmpty() ? (int) round($responseMinutes->avg()) : null;

        $ratedTickets = $tickets->pluck('satisfaction_rating')->filter();
        $avgSatisfaction = $ratedTickets->isNotEmpty() ? round($ratedTickets->avg(), 1) : null;

        return view('admin.tickets.dashboard', [
            'totalOpen' => $totalOpen,
            'totalAssigned' => $totalAssigned,
            'totalClosed' => $totalClosed,
            'byModule' => $byModule,
            'avgResponseMinutes' => $avgResponseMinutes,
            'avgSatisfaction' => $avgSatisfaction,
            'ratedCount' => $ratedTickets->count(),
            'dateFrom' => $from?->format('Y-m-d'),
            'dateTo' => $to?->format('Y-m-d'),
        ]);
    }

    /**
     * Formulario para que el staff de Billingo abra un ticket a nombre de
     * una empresa cualquiera (p. ej. un aviso o seguimiento que arranca
     * desde este lado, no desde una solicitud de la empresa).
     */
    public function create()
    {
        return view('admin.tickets.create', [
            'companies' => Company::orderBy('name')->get(),
            'modules' => config('modules'),
        ]);
    }

    /**
     * Crea el ticket a nombre del staff (primer mensaje con author_role
     * "staff") y avisa a quien administra, en la empresa elegida, el
     * módulo elegido -- no a todos los admins de la empresa.
     */
    public function store(Request $request)
    {
        $company = Company::findOrFail($request->input('company_id'));

        $data = $request->validate([
            'company_id' => 'required|string',
            'module' => 'nullable|in:' . implode(',', array_merge(['general'], array_keys(config('modules')))),
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'company_id' => (string) $company->_id,
            'user_id' => null,
            'subject' => $data['subject'],
            'module' => $data['module'] ?? 'general',
            'priority' => SupportTicket::PRIORITY_MEDIUM,
            'status' => SupportTicket::STATUS_OPEN,
            'staff_last_viewed_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => (string) $request->user()->_id,
            'author_role' => SupportTicketMessage::AUTHOR_STAFF,
            'body' => $data['body'],
        ]);

        Notification::notifyUsers(
            $company->administratorUserIdsForModule($ticket->module),
            __('New message from Billingo support'),
            $ticket->subject,
            route('support.show', $ticket->_id)
        );

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Ticket')]),
        ]);

        return redirect()->route('admin.tickets.show', $ticket->_id);
    }

    /**
     * Todos los tickets del sistema, con filtros opcionales por estado,
     * módulo, empresa y asignado -- los abiertos primero (los que
     * necesitan atención), y dentro de cada grupo el de última actividad
     * más reciente primero.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $tickets = $query->orderByDesc('updated_at')->get();

        $companyNames = Company::whereIn('_id', $tickets->pluck('company_id')->unique()->all())
            ->get()
            ->keyBy(fn ($company) => (string) $company->_id);

        $assigneeNames = User::whereIn('_id', $tickets->pluck('assigned_to')->filter()->unique()->all())
            ->get()
            ->keyBy(fn ($user) => (string) $user->_id);

        $tickets = $tickets
            ->map(function (SupportTicket $ticket) use ($companyNames, $assigneeNames) {
                $ticket->company_name = $companyNames->get((string) $ticket->company_id)?->name ?? $ticket->contact_name;
                $ticket->assignee_name = $ticket->assigned_to ? $assigneeNames->get((string) $ticket->assigned_to)?->name : null;

                return $ticket;
            })
            ->sortBy(fn (SupportTicket $ticket) => $ticket->status === SupportTicket::STATUS_CLOSED ? 1 : 0)
            ->values();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'companies' => Company::orderBy('name')->get(),
            'staffUsers' => User::where('role', 'superadmin')->orderBy('name')->get(),
            'modules' => config('modules'),
            'filters' => $request->only(['status', 'module', 'company_id', 'assigned_to']),
        ]);
    }

    public function show(string $supportTicket)
    {
        $ticket = SupportTicket::findOrFail($supportTicket);
        $company = $ticket->company_id ? Company::find($ticket->company_id) : null;

        // "timestamps = false" antes de guardar para NO tocar "updated_at"
        // -- abrir un ticket para leerlo no debe hacerlo "saltar" al tope
        // del listado como si hubiera actividad nueva (el driver de Mongo
        // sí actualiza "updated_at" en un update() por query builder, a
        // diferencia del Eloquent normal -- por eso no basta con eso solo).
        $ticket->timestamps = false;
        $ticket->update(['staff_last_viewed_at' => now()]);
        $ticket->timestamps = true;

        $activities = $ticket->activities()->get();

        $assignmentActivities = $activities->where('action', SupportTicketActivity::ACTION_ASSIGNED);
        $activityUserIds = $activities->pluck('user_id')
            ->merge($assignmentActivities->pluck('to'))
            ->merge($assignmentActivities->pluck('from'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $activityUsers = User::whereIn('_id', $activityUserIds)->get()->keyBy(fn ($user) => (string) $user->_id);

        // Para el hilo del chat solo se intercalan estado/asignación -- la
        // prioridad es info de triage interna, se queda solo en el
        // historial de la barra lateral (abajo, con badges).
        $chatActivities = $activities->whereIn('action', [SupportTicketActivity::ACTION_STATUS, SupportTicketActivity::ACTION_ASSIGNED]);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'company' => $company,
            'messages' => $ticket->messages()->get(),
            'staffUsers' => User::where('role', 'superadmin')->orderBy('name')->get(),
            'activities' => $activities,
            'activityUsers' => $activityUsers,
            'chatActivities' => $chatActivities,
            'cannedResponses' => CannedResponse::orderBy('title')->get(),
        ]);
    }

    /**
     * Responder desde el lado de Billingo -- o dejar una nota interna
     * (is_internal) si "is_internal" viene en 1, que nunca se le muestra a
     * la empresa ni dispara aviso. El estado NO cambia solo por responder
     * (ver updateStatus()).
     */
    public function reply(Request $request, string $supportTicket)
    {
        $ticket = SupportTicket::findOrFail($supportTicket);
        $company = $ticket->company_id ? Company::find($ticket->company_id) : null;

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $isInternal = $request->boolean('is_internal');

        SupportTicketMessage::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => (string) $request->user()->_id,
            'author_role' => SupportTicketMessage::AUTHOR_STAFF,
            'body' => $data['body'],
            'is_internal' => $isInternal,
        ]);

        $ticket->update(['staff_last_viewed_at' => now()]);

        // Un lead del formulario "Contáctanos" no tiene empresa ni cuenta
        // -- no hay a quién avisarle in-app (solo por correo, que todavía
        // no existe para este flujo, ver memoria del backlog pendiente).
        if (! $isInternal && $company) {
            Notification::notifyUsers(
                $company->administratorUserIdsForModule($ticket->module),
                __('Your support request was answered'),
                $ticket->subject,
                route('support.show', $ticket->_id)
            );
        }

        return redirect()->route('admin.tickets.show', $ticket->_id);
    }

    /**
     * Cambiar el estado del ticket a mano (menú al lado del hilo) -- nunca
     * se infiere de si hay un mensaje nuevo o no.
     */
    public function updateStatus(Request $request, string $supportTicket)
    {
        $ticket = SupportTicket::findOrFail($supportTicket);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', SupportTicket::STATUSES),
        ]);

        if ($data['status'] !== $ticket->status) {
            SupportTicketActivity::create([
                'support_ticket_id' => (string) $ticket->_id,
                'user_id' => (string) $request->user()->_id,
                'action' => SupportTicketActivity::ACTION_STATUS,
                'from' => $ticket->status,
                'to' => $data['status'],
            ]);
        }

        $ticket->update(['status' => $data['status'], 'staff_last_viewed_at' => now()]);

        return redirect()->route('admin.tickets.show', $ticket->_id);
    }

    /**
     * Prioridad del ticket (baja/media/alta/urgente) -- igual que el
     * estado, siempre a mano y queda en el mismo historial (no cambia
     * quién le llega el aviso a nadie, es solo para que el staff ordene
     * qué atender primero, pero igual conviene ver quién la subió/bajó).
     */
    public function updatePriority(Request $request, string $supportTicket)
    {
        $ticket = SupportTicket::findOrFail($supportTicket);

        $data = $request->validate([
            'priority' => 'required|in:' . implode(',', SupportTicket::PRIORITIES),
        ]);

        if ($data['priority'] !== $ticket->priority) {
            SupportTicketActivity::create([
                'support_ticket_id' => (string) $ticket->_id,
                'user_id' => (string) $request->user()->_id,
                'action' => SupportTicketActivity::ACTION_PRIORITY,
                'from' => $ticket->priority,
                'to' => $data['priority'],
            ]);
        }

        $ticket->update(['priority' => $data['priority'], 'staff_last_viewed_at' => now()]);

        return redirect()->route('admin.tickets.show', $ticket->_id);
    }

    /**
     * Asignar el ticket a un superadmin puntual (o quitarle la asignación
     * con la opción "Sin asignar") -- desde ese momento, los avisos de
     * mensajes nuevos de la empresa le llegan solo a esa persona (ver
     * SupportTicketController::notifyStaff()).
     */
    public function assign(Request $request, string $supportTicket)
    {
        $ticket = SupportTicket::findOrFail($supportTicket);

        $data = $request->validate([
            'assigned_to' => 'nullable|string',
        ]);

        $assignedTo = $data['assigned_to'] ?? null;

        if ($assignedTo && ! User::where('_id', $assignedTo)->where('role', 'superadmin')->exists()) {
            abort(422);
        }

        $previousAssignedTo = $ticket->assigned_to ? (string) $ticket->assigned_to : null;

        if ($assignedTo !== $previousAssignedTo) {
            SupportTicketActivity::create([
                'support_ticket_id' => (string) $ticket->_id,
                'user_id' => (string) $request->user()->_id,
                'action' => SupportTicketActivity::ACTION_ASSIGNED,
                'from' => $previousAssignedTo,
                'to' => $assignedTo,
            ]);
        }

        $ticket->update(['assigned_to' => $assignedTo, 'staff_last_viewed_at' => now()]);

        return redirect()->route('admin.tickets.show', $ticket->_id);
    }
}
