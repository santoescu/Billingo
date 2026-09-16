<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo en frío a un prospecto (ver Lead, AdminLeadController) -- NO implementa ShouldQueue a
 * propósito: se manda uno o pocos a la vez, a mano, no en ráfagas grandes como los documentos
 * emitidos, así que el problema que justificó encolar DocumentIssuedMail no aplica acá. Se manda
 * sincrónico para poder sacar el Message-ID de SES de una, igual que se hacía antes con los
 * documentos (ver AdminLeadController::send()).
 */
class LeadOutreachMail extends Mailable
{
    public const VARIANT_INITIAL = 'initial';
    public const VARIANT_FOLLOWUP = 'followup';
    public const VARIANT_BREAKUP = 'breakup';

    private const SUBJECTS = [
        self::VARIANT_INITIAL => 'facturación',
        self::VARIANT_FOLLOWUP => 'pregunta rápida',
        self::VARIANT_BREAKUP => 'último mensaje',
    ];

    /**
     * $replyToAddress, no $replyTo -- Mailable ya declara su propia propiedad interna
     * "$replyTo" (la usa ->replyTo() internamente), y una promovida del constructor con el mismo
     * nombre choca con esa declaración ("Type of ...::$replyTo must not be defined").
     */
    public function __construct(public Lead $lead, public string $variant, public string $replyToAddress)
    {
    }

    /**
     * "notificaciones@billingo.com.co" (MAIL_FROM_ADDRESS) es una dirección automática, no algo
     * que alguien revise -- para un correo en frío sí importa que las respuestas lleguen a una
     * bandeja real, así que se elige a mano cada vez que se manda (ver
     * AdminLeadController::send()), no un valor fijo en el .env.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::SUBJECTS[$this->variant] ?? self::SUBJECTS[self::VARIANT_INITIAL],
            replyTo: [new Address($this->replyToAddress)],
        );
    }

    /**
     * Manda texto Y una versión HTML mínima (mismo texto, sin logo/colores/plantilla) -- la skill
     * de cold-email pide que un correo en frío se vea como algo que escribió una persona, no como
     * marketing, así que la versión HTML no tiene ningún diseño encima, solo el link real como
     * <a> en vez de una URL suelta. Es necesario mandar HTML aunque sea mínimo porque SES solo
     * puede reescribir links para hacer click-tracking (ver SesEventWebhookController) dentro de
     * contenido HTML -- un correo 100% texto plano nunca genera eventos "Click" en SES.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.leads.outreach-html',
            text: 'emails.leads.outreach-text',
            with: [
                'lead' => $this->lead,
                'variant' => $this->variant,
            ],
        );
    }
}
