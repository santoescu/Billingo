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
    public const VARIANT_CUSTOM = 'custom';

    private const SUBJECTS = [
        self::VARIANT_INITIAL => 'facturación',
        self::VARIANT_FOLLOWUP => 'pregunta rápida',
        self::VARIANT_BREAKUP => 'último mensaje',
    ];

    /**
     * $replyToAddress, no $replyTo -- Mailable ya declara su propia propiedad interna
     * "$replyTo" (la usa ->replyTo() internamente), y una promovida del constructor con el mismo
     * nombre choca con esa declaración ("Type of ...::$replyTo must not be defined").
     *
     * $customSubject/$customBody solo se usan cuando $variant es VARIANT_CUSTOM -- ahí el
     * asunto/cuerpo no viene de una de las 3 plantillas fijas, sino de lo que escribió quien
     * manda, con variables "{{nombre}}", "{{razon_social}}", etc. (ver Lead::mergeVariables())
     * que se reemplazan por los datos reales de CADA lead antes de mandarse.
     */
    public function __construct(
        public Lead $lead,
        public string $variant,
        public string $replyToAddress,
        public ?string $customSubject = null,
        public ?string $customBody = null,
    ) {
    }

    /**
     * "notificaciones@billingo.com.co" (MAIL_FROM_ADDRESS) es una dirección automática, no algo
     * que alguien revise -- para un correo en frío sí importa que las respuestas lleguen a una
     * bandeja real, así que se elige a mano cada vez que se manda (ver
     * AdminLeadController::send()), no un valor fijo en el .env.
     */
    public function envelope(): Envelope
    {
        $subject = $this->variant === self::VARIANT_CUSTOM
            ? $this->lead->fillMergeVariables((string) $this->customSubject)
            : self::SUBJECTS[$this->variant] ?? self::SUBJECTS[self::VARIANT_INITIAL];

        return new Envelope(
            subject: $subject,
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
        // "mergedCustomBody", no "customBody" -- Mailable expone TODAS las propiedades públicas
        // del correo a la vista con su mismo nombre (ver Mailable::buildViewData()), así que
        // pasar la versión ya reemplazada bajo el nombre "customBody" quedaba pisada por la
        // propiedad cruda de la clase ($this->customBody, sin reemplazar) -- eso hacía que el
        // asunto sí saliera bien (envelope() no pasa por la vista) pero el cuerpo del correo
        // mostrara "{{nombre}}" tal cual, sin reemplazar.
        $mergedCustomBody = $this->variant === self::VARIANT_CUSTOM
            ? $this->lead->fillMergeVariables((string) $this->customBody)
            : null;

        return new Content(
            view: 'emails.leads.outreach-html',
            text: 'emails.leads.outreach-text',
            with: [
                'lead' => $this->lead,
                'variant' => $this->variant,
                'mergedCustomBody' => $mergedCustomBody,
            ],
        );
    }
}
