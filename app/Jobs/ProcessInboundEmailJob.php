<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Dian\ReceivedDocumentIngestionService;
use App\Services\Dian\ReceivedDocumentParser;
use Aws\S3\S3Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Procesa un correo entrante de SES en segundo plano -- SesInboundWebhookController solo
 * despacha este job y responde 200 de inmediato, en vez de bajar de S3/parsear/guardar dentro de
 * la misma petición HTTP que atiende la notificación de SNS. Sin esto, una ráfaga de varios
 * correos casi simultáneos (ej. reenvío masivo desde Gmail) puede saturar los workers PHP del
 * servidor web y perder documentos silenciosamente, aunque el procesamiento en sí no tenga
 * ningún error (ver incidente del 2026-09-15: 15 documentos válidos que nunca se guardaron por
 * esta causa, recuperados a mano).
 */
class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $bucket, private readonly string $key)
    {
    }

    public function handle(ReceivedDocumentIngestionService $ingestionService, ReceivedDocumentParser $parser): void
    {
        $raw = $this->downloadRawEmail();
        $email = (new MailMimeParser())->parse($raw, false);

        foreach ($email->getAllAttachmentParts() as $attachment) {
            $this->ingestAttachment($ingestionService, $parser, $attachment);
        }
    }

    private function downloadRawEmail(): string
    {
        return (string) $this->s3Client()->getObject(['Bucket' => $this->bucket, 'Key' => $this->key])['Body'];
    }

    /**
     * Lee el NIT del comprador (accounting_customer_party) directo del XML del adjunto y resuelve
     * a qué empresa le pertenece -- la dirección de recepción es compartida entre todas las
     * empresas, así que este NIT es la única forma de saber para quién es el documento.
     *
     * @param  string  $identificacion
     * @return Company|null
     */
    private function resolveCompanyFromIdentificacion(string $identificacion): ?Company
    {
        if ($identificacion === '') {
            return null;
        }

        $company = Company::where('identificacion', $identificacion)->first();

        if (! $company) {
            Log::warning('Correo entrante SES: no hay ninguna empresa con ese NIT de comprador.', ['identificacion' => $identificacion]);
        }

        return $company;
    }

    /**
     * Guarda el adjunto en un archivo temporal, lo parsea para saber a qué empresa pertenece
     * (por el NIT del comprador) y lo procesa igual que un archivo subido a mano (ver
     * ReceivedDocumentIngestionService::ingest()) -- ignora en silencio los adjuntos que no sean
     * .xml/.zip (firmas de correo, logos incrustados, etc.), y deja un log (sin reintentar el
     * job completo) si el que sí parece un documento falla al procesarse o resolverse.
     */
    private function ingestAttachment(ReceivedDocumentIngestionService $ingestionService, ReceivedDocumentParser $parser, IMessagePart $attachment): void
    {
        $filename = $attachment->getFilename() ?: 'documento';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xml', 'zip'], true)) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ses-inbound-');

        try {
            file_put_contents($tempPath, $attachment->getBinaryContentStream()->getContents());

            $parsed = $parser->parseUploadedFile($tempPath, $extension);
            $identificacion = trim((string) ($parsed['payload']['accounting_customer_party']['identificacion'] ?? ''));
            $company = $this->resolveCompanyFromIdentificacion($identificacion);

            if (! $company) {
                return;
            }

            $ingestionService->ingest($company, $tempPath, $extension, $filename, null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            Log::warning('Correo entrante SES: no se pudo procesar un adjunto.', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    private function s3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => config('services.ses.region'),
            'credentials' => [
                'key' => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
    }
}
