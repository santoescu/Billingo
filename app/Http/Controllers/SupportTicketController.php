<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    /**
     * Solicitudes de ayuda/PQR de la empresa activa, más recientes primero
     * (por última actividad, no por creación -- un ticket contestado hace
     * rato pero con un mensaje nuevo hoy debe subir al tope igual que uno
     * recién creado).
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $tickets = $company->supportTickets()->orderByDesc('updated_at')->get();

        return view('support.index', [
            'company' => $company,
            'tickets' => $tickets,
            'modules' => $company->availableModules(),
        ]);
    }

    /**
     * Crea el ticket y su primer mensaje (el cuerpo de la solicitud). El
     * estado siempre arranca "abierto" -- de ahí en adelante solo el staff
     * de Billingo lo cambia a mano (ver AdminSupportTicketController::
     * updateStatus()), nunca automático por un mensaje nuevo.
     */
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'module' => 'nullable|in:' . implode(',', array_merge(['general'], array_keys($company->availableModules()))),
            'priority' => 'nullable|in:' . implode(',', SupportTicket::PRIORITIES),
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'company_id' => (string) $company->_id,
            'user_id' => (string) $request->user()->_id,
            'subject' => $data['subject'],
            'module' => $data['module'] ?? 'general',
            'priority' => $data['priority'] ?? SupportTicket::PRIORITY_MEDIUM,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => (string) $request->user()->_id,
            'author_role' => SupportTicketMessage::AUTHOR_COMPANY,
            'body' => $data['body'],
        ]);

        $this->notifyStaff($ticket, $company->name, __('New support request'));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Your request was sent, we will answer soon.'),
        ]);

        return redirect()->route('support.show', $ticket->_id);
    }

    /**
     * Hilo de mensajes de un ticket, verificando que sea de la empresa
     * activa. Nunca incluye las notas internas del staff (is_internal) --
     * esas no son parte de la conversación con la empresa. Los cambios de
     * estado/asignación se intercalan en el mismo hilo (ver
     * support.partials.messages) -- sin la prioridad, que es solo para
     * que el staff se organice, no le aporta nada a la empresa.
     */
    public function show(Request $request, string $supportTicket)
    {
        $company = $this->currentCompany($request);

        $ticket = $company->supportTickets()->where('_id', $supportTicket)->first();

        abort_unless($ticket, 404);

        $activities = $ticket->activities()
            ->whereIn('action', [SupportTicketActivity::ACTION_STATUS, SupportTicketActivity::ACTION_ASSIGNED])
            ->get();

        $userIds = $activities->pluck('user_id')->merge($activities->pluck('to'))->filter()->unique()->values()->all();
        $activityUsers = User::whereIn('_id', $userIds)->get()->keyBy(fn ($user) => (string) $user->_id);

        return view('support.show', [
            'company' => $company,
            'ticket' => $ticket,
            'messages' => $ticket->messages()->where('is_internal', '!=', true)->get(),
            'activities' => $activities,
            'activityUsers' => $activityUsers,
        ]);
    }

    /**
     * Responder desde el lado de la empresa. El estado NO cambia solo por
     * responder -- lo sigue manejando el staff a mano.
     */
    public function reply(Request $request, string $supportTicket)
    {
        $company = $this->currentCompany($request);

        $ticket = $company->supportTickets()->where('_id', $supportTicket)->first();

        abort_unless($ticket, 404);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => (string) $request->user()->_id,
            'author_role' => SupportTicketMessage::AUTHOR_COMPANY,
            'body' => $data['body'],
        ]);

        $ticket->touch();

        $this->notifyStaff($ticket, $company->name, __('New message on a request'));

        return redirect()->route('support.show', $ticket->_id);
    }

    /**
     * La empresa marca su propia solicitud como resuelta -- no hace falta
     * esperar a que el staff la cierre. Queda igual en el historial que un
     * cambio de estado hecho por el staff (mismo modelo, solo cambia quién
     * aparece como autor).
     */
    public function close(Request $request, string $supportTicket)
    {
        $company = $this->currentCompany($request);

        $ticket = $company->supportTickets()->where('_id', $supportTicket)->first();

        abort_unless($ticket, 404);

        $this->changeStatus($ticket, $request->user(), SupportTicket::STATUS_CLOSED);

        return redirect()->route('support.show', $ticket->_id);
    }

    /**
     * Reabrir una solicitud que la empresa (o el staff) había cerrado.
     */
    public function reopen(Request $request, string $supportTicket)
    {
        $company = $this->currentCompany($request);

        $ticket = $company->supportTickets()->where('_id', $supportTicket)->first();

        abort_unless($ticket, 404);

        $this->changeStatus($ticket, $request->user(), SupportTicket::STATUS_OPEN);

        return redirect()->route('support.show', $ticket->_id);
    }

    /**
     * Encuesta de satisfacción ("¿te sirvió?"): solo tiene sentido con el
     * ticket ya cerrado, y solo se puede calificar una vez -- si se
     * reabre y se vuelve a cerrar, queda disponible de nuevo (la
     * calificación anterior no se borra al reabrir, solo cambia el
     * estado).
     */
    public function submitSatisfaction(Request $request, string $supportTicket)
    {
        $company = $this->currentCompany($request);

        $ticket = $company->supportTickets()->where('_id', $supportTicket)->first();

        abort_unless($ticket, 404);
        abort_unless($ticket->status === SupportTicket::STATUS_CLOSED, 422);
        abort_if($ticket->satisfaction_rating, 422);

        $data = $request->validate([
            'satisfaction_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'satisfaction_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update([
            'satisfaction_rating' => $data['satisfaction_rating'],
            'satisfaction_comment' => $data['satisfaction_comment'] ?? null,
            'satisfaction_submitted_at' => now(),
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Thanks for your feedback!'),
        ]);

        return redirect()->route('support.show', $ticket->_id);
    }

    private function changeStatus(SupportTicket $ticket, User $actor, string $newStatus): void
    {
        if ($newStatus === $ticket->status) {
            return;
        }

        SupportTicketActivity::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => (string) $actor->_id,
            'action' => SupportTicketActivity::ACTION_STATUS,
            'from' => $ticket->status,
            'to' => $newStatus,
        ]);

        $ticket->update(['status' => $newStatus]);
    }

    /**
     * @param  SupportTicket  $ticket
     * @param  string  $companyName  Para el título del aviso, sin tener que recargar la empresa desde el lado del staff.
     * @param  string  $title  Distingue en la campanita si es un ticket nuevo o un mensaje nuevo en uno existente.
     */
    private function notifyStaff(SupportTicket $ticket, string $companyName, string $title): void
    {
        // Si el ticket ya tiene un superadmin asignado, el aviso le llega
        // solo a esa persona -- no a todo el equipo -- de ahí en adelante.
        $staffIds = $ticket->assigned_to
            ? [(string) $ticket->assigned_to]
            : User::where('role', 'superadmin')->get()->pluck('_id')->map(fn ($id) => (string) $id)->all();

        Notification::notifyUsers(
            $staffIds,
            $title,
            __(':company: :subject', ['company' => $companyName, 'subject' => $ticket->subject]),
            route('admin.tickets.show', $ticket->_id)
        );
    }
}
