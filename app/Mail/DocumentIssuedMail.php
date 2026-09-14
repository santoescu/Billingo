<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\PaymentMeansCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Sin ShouldQueue a propósito: si algún día se agrega, hay que confirmar primero que el worker
// de colas corre siempre en producción (QUEUE_CONNECTION=database) -- si no, el correo se queda
// pegado en la tabla "jobs" sin que nadie se entere. Mientras tanto, se manda sincrónico.
class DocumentIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    private static array $documentTypeLabels = [
        '01' => 'Electronic sales invoice',
        '02' => 'Electronic sales invoice (export)',
        '03' => 'Electronic transmission instrument (type 03)',
        '04' => 'Electronic sales invoice (type 04)',
        '91' => 'Credit note',
        '92' => 'Debit note',
    ];

    public function __construct(public Company $company, public DocumentoEmitido $documento)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':document :numeral from :company', [
                'document' => __(self::$documentTypeLabels[$this->documento->tipo_documento] ?? 'Document'),
                'numeral' => $this->documento->numeral,
                'company' => $this->company->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.documents.issued',
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
