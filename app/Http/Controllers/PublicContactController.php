<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicContactController extends Controller
{
    /**
     * Formulario "Contáctanos" del panel de login -- un prospecto sin
     * cuenta todavía pide que lo contactemos. Se guarda como un
     * SupportTicket más (sin company_id/user_id, con los datos de contacto
     * sueltos) para que el staff lo vea en el mismo panel de soporte que
     * ya usa para las empresas.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_name' => 'required|string|max:150',
            'contact_phone' => 'nullable|string|max:30',
            'contact_email' => 'required|email|max:150',
            'body' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'],
            'subject' => __('Contact request from :name', ['name' => $data['contact_name']]),
            'module' => 'general',
            'priority' => SupportTicket::PRIORITY_MEDIUM,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => (string) $ticket->_id,
            'user_id' => null,
            'author_role' => SupportTicketMessage::AUTHOR_COMPANY,
            'body' => $data['body'],
        ]);

        $staffIds = User::where('role', 'superadmin')->get()->pluck('_id')->map(fn ($id) => (string) $id)->all();

        Notification::notifyUsers(
            $staffIds,
            __('New contact request'),
            $data['contact_name'] . ': ' . Str::limit($data['body'], 100),
            route('admin.tickets.show', $ticket->_id)
        );

        return redirect()->route('login')->with('status', __('Thanks! We got your message and will contact you soon.'));
    }
}
