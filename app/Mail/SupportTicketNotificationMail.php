<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso puntual de ticket (nuevo, asignación, cambio de estado -- ver
 * Notification::notifyUsersWithEmail()) -- NO implementa ShouldQueue a propósito, igual que
 * LeadOutreachMail: son pocos correos a la vez, no ráfagas grandes como DocumentIssuedMail, y
 * acá sí importa que lleguen de inmediato (no depender del Cloud Scheduler, que puede tardar
 * varios minutos en procesar la cola).
 */
class SupportTicketNotificationMail extends Mailable
{
    public const KIND_NEW = 'new';
    public const KIND_ASSIGNED = 'assigned';
    public const KIND_STATUS = 'status';

    /**
     * Mismos colores que ya usa la app para el estado del ticket (ver
     * SupportTicket::statusBadgeClasses()/priorityBadgeClasses(), traducidos de clases Tailwind
     * a hex porque el correo no puede usar esas clases) -- así la insignia del correo se ve
     * igual a como se vería en el panel, en vez de inventar una paleta aparte. "new" usa el
     * mismo ámbar que el estado "abierto" (un ticket nuevo siempre nace abierto), "assigned" el
     * mismo azul que el estado "asignado", y "status" toma el color del estado real al que
     * cambió el ticket (ver content()).
     */
    private const PALETTE = [
        'amber' => ['soft' => '#fef3c7', 'accent' => '#92400e', 'border' => '#fde68a'],
        'blue' => ['soft' => '#dbeafe', 'accent' => '#1e40af', 'border' => '#bfdbfe'],
        'gray' => ['soft' => '#f3f4f6', 'accent' => '#374151', 'border' => '#e5e7eb'],
    ];

    private const KIND_LABELS = [
        self::KIND_NEW => 'Nuevo ticket',
        self::KIND_ASSIGNED => 'Asignación',
        self::KIND_STATUS => 'Cambio de estado',
    ];

    public function __construct(
        public SupportTicket $ticket,
        public string $title,
        public string $summary,
        public string $viewUrl,
        public string $kind = self::KIND_NEW,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        $palette = self::PALETTE[$this->paletteKey()];

        return new Content(
            view: 'emails.support.notification',
            with: [
                'title' => $this->title,
                'summary' => $this->summary,
                'viewUrl' => $this->viewUrl,
                'subject' => $this->ticket->subject,
                'badgeLabel' => self::KIND_LABELS[$this->kind] ?? self::KIND_LABELS[self::KIND_NEW],
                'accentColor' => $palette['accent'],
                'softColor' => $palette['soft'],
                'borderColor' => $palette['border'],
            ],
        );
    }

    private function paletteKey(): string
    {
        return match ($this->kind) {
            self::KIND_ASSIGNED => 'blue',
            self::KIND_STATUS => match ($this->ticket->status) {
                SupportTicket::STATUS_ASSIGNED => 'blue',
                SupportTicket::STATUS_CLOSED => 'gray',
                default => 'amber',
            },
            default => 'amber',
        };
    }
}
