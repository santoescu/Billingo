<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\PaymentMeansCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use ZipArchive;

/**
 * Arma el .zip que se manda por correo (ver DocumentIssuedMail) y el que se puede descargar a
 * mano desde documents.show -- un solo lugar para esta lógica, porque el AttachedDocument (ver
 * AttachedDocumentBuilder) es sensible a la normativa DIAN y no conviene tenerlo armado de dos
 * formas distintas en el código.
 */
class DocumentAttachmentZipBuilder
{
    public function __construct(
        private readonly AttachedDocumentBuilder $attachedDocumentBuilder = new AttachedDocumentBuilder(),
        private readonly UblDocumentSigner $signer = new UblDocumentSigner(),
    ) {
    }

    /**
     * @param  Company  $company  Empresa emisora.
     * @param  DocumentoEmitido  $documento  Documento ya aceptado por la DIAN.
     * @return string Contenido del .zip (AttachedDocument firmado + PDF), listo para adjuntar o descargar.
     */
    public function build(Company $company, DocumentoEmitido $documento): string
    {
        $unsignedAttachedDocument = $this->attachedDocumentBuilder->build($company, $documento);
        $signedAttachedDocument = $this->signer->sign($company, $unsignedAttachedDocument);
        $signedAttachedDocument = $this->attachedDocumentBuilder->restoreCdataSections($signedAttachedDocument);

        $tmpPath = tempnam(sys_get_temp_dir(), 'billingo-issued-');

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::OVERWRITE);
        $zip->addFromString($documento->numeral . '.xml', $signedAttachedDocument);
        $zip->addFromString($documento->numeral . '.pdf', $this->renderPdf($company, $documento));
        $zip->close();

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }

    private function renderPdf(Company $company, DocumentoEmitido $documento): string
    {
        $paymentMeansCode = $documento->payment_means_code
            ? PaymentMeansCode::where('codigo', $documento->payment_means_code)->first()
            : null;

        $qrDataUri = null;
        if ($documento->qr_validation_url) {
            $qrCode = new QrCode(data: $documento->qr_validation_url, size: 300, margin: 8);
            $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
        }

        return Pdf::loadView($company->resolvePdfView('invoice-pdf'), [
            'company' => $company,
            'documento' => $documento,
            'paymentMeansCode' => $paymentMeansCode,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('letter', 'portrait')->output();
    }
}
