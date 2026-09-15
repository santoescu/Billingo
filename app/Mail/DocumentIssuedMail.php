<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Services\Dian\DocumentAttachmentZipBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

// Se manda en cola (ver DocumentoEmitidoController::sendEmail(), que llama ->queue() en vez de
// ->send()) -- el worker de colas corre vía SQS + Cloud Scheduler (ver QueueWorkerController),
// no la tabla "jobs" de Mongo, que no es compatible con el locking que necesita el driver
// "database" de Laravel.
class DocumentIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * _id del EmailLog que ya se creó (antes de encolar este Mailable, ver
     * DocumentoEmitidoController::sendEmail()) -- viaja como header del propio correo para que
     * RecordSesMessageId pueda encontrar a qué EmailLog corresponde una vez que el job realmente
     * corre y SES ya le asignó un Message-ID (que no existe todavía en el momento de encolar).
     */
    public ?string $emailLogId = null;

    public function __construct(public Company $company, public DocumentoEmitido $documento)
    {
    }

    /**
     * El asunto NO es libre: el Anexo Técnico 1.9 de la DIAN (sección 9.1) exige este formato
     * exacto separado por ";" -- NIT del facturador, nombre del facturador, número del
     * documento, código del tipo de documento, nombre comercial del facturador. Mismo patrón
     * que se ve en los correos que mandan otros proveedores (ver SesInboundWebhookController).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: implode(';', [
                $this->company->identificacion,
                $this->company->name,
                $this->documento->numeral,
                $this->documento->tipo_documento,
                $this->company->name,
            ]),
        );
    }

    public function withEmailLogId(string $emailLogId): static
    {
        $this->emailLogId = $emailLogId;

        return $this;
    }

    public function headers(): Headers
    {
        return new Headers(
            text: $this->emailLogId ? ['X-Billingo-Email-Log-Id' => $this->emailLogId] : [],
        );
    }

    /**
     * "document-issued-email" en vez de una ruta fija -- ver Company::resolvePdfView() (mismo
     * mecanismo que ya existía para los PDFs por empresa): si el cliente necesita un cuerpo de
     * correo distinto al estándar, la plantilla se agrega en resources/views/custom/<NIT>/, sin
     * tocar código.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->company->resolvePdfView('document-issued-email'),
            with: [
                'company' => $this->company,
                'documento' => $this->documento,
            ],
        );
    }

    /**
     * Un solo adjunto .zip con el AttachedDocument y el PDF adentro -- así es como la DIAN
     * dispone el envío de documentos electrónicos (Anexo Técnico 1.9), y es el mismo formato que
     * mandan los proveedores de quienes recibimos documentos (ver ReceivedDocumentParser). Se
     * arma con DocumentAttachmentZipBuilder -- mismo servicio que usa el botón de descarga manual
     * en documents.show, para no tener esta lógica sensible a la normativa DIAN en dos lugares.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => (new DocumentAttachmentZipBuilder())->build($this->company, $this->documento),
                $this->documento->numeral . '.zip',
            )->withMime('application/zip'),
        ];
    }
}
