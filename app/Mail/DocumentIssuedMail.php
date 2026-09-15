<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\PaymentMeansCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
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
     * Adjunta el mismo PDF que se vería en documents.invoice-pdf (idéntica representación
     * gráfica a la que el cliente vería si entrara a verla desde la app -- ver
     * DocumentoEmitidoController::invoicePreview()) y el XML firmado, si ya lo tiene.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(fn () => $this->renderPdf(), $this->documento->numeral . '.pdf')
                ->withMime('application/pdf'),
        ];

        if ($this->documento->xml) {
            $attachments[] = Attachment::fromData(fn () => $this->documento->xml, $this->documento->numeral . '.xml')
                ->withMime('application/xml');
        }

        return $attachments;
    }

    private function renderPdf(): string
    {
        $paymentMeansCode = $this->documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $this->documento->payment_means_code)->first()
            : null;

        $qrDataUri = null;
        if ($this->documento->qr_validation_url) {
            $qrCode = new QrCode(data: $this->documento->qr_validation_url, size: 300, margin: 8);
            $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
        }

        return Pdf::loadView($this->company->resolvePdfView('invoice-pdf'), [
            'company' => $this->company,
            'documento' => $this->documento,
            'paymentMeansCode' => $paymentMeansCode,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('letter', 'portrait')->output();
    }
}
