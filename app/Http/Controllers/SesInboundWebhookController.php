<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Dian\ReceivedDocumentIngestionService;
use Aws\S3\S3Client;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

class SesInboundWebhookController extends Controller
{
    /**
     * Recibe el aviso de SNS de que llegó un correo nuevo a la casilla de recepción (ver
     * Company::reception_email_alias), lo baja de S3 (ahí es donde SES deja el correo crudo
     * completo), saca los adjuntos XML/zip, y los procesa con el mismo servicio que usa la
     * subida manual (ver ReceivedDocumentIngestionService::ingest()) -- así los dos caminos
     * (subir a mano, o que llegue por correo) terminan guardando el documento exactamente igual.
     *
     * Dos tipos de mensaje de SNS llegan acá:
     * - "SubscriptionConfirmation": el primer aviso al suscribir esta URL al tópico de SNS -- hay
     *   que visitar la "SubscribeURL" que trae para confirmar la suscripción, si no SNS nunca
     *   empieza a mandar notificaciones de verdad.
     * - "Notification": un correo nuevo. Trae, adentro de "Message" (un JSON codificado como
     *   string dentro del JSON de sobre de SNS), el bucket/key de S3 donde quedó el correo.
     *
     * Siempre responde 200 salvo que la firma de SNS no sea válida (403) -- SNS reintenta el
     * envío si no recibe 200, y no queremos que reintente por errores de negocio (empresa no
     * encontrada, adjunto que no es un documento válido) que nunca se van a resolver solos.
     */
    public function handle(Request $request, ReceivedDocumentIngestionService $ingestionService)
    {
        try {
            $message = SnsMessage::fromJsonString($request->getContent());
            (new MessageValidator())->validate($message);
        } catch (Throwable $e) {
            Log::warning('Correo entrante SES: mensaje de SNS inválido, se descarta.', ['error' => $e->getMessage()]);

            return response('', 403);
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            file_get_contents($message['SubscribeURL']);

            return response('', 200);
        }

        if ($message['Type'] !== 'Notification') {
            return response('', 200);
        }

        $notification = json_decode($message['Message'], true);
        $bucket = $notification['receipt']['action']['bucketName'] ?? null;
        $key = $notification['receipt']['action']['objectKey'] ?? null;

        if (! $bucket || ! $key) {
            Log::warning('Correo entrante SES: notificación sin bucket/key de S3, se descarta.');

            return response('', 200);
        }

        $raw = $this->downloadRawEmail($bucket, $key);
        $email = (new MailMimeParser())->parse($raw, false);

        $company = $this->resolveCompanyFromRecipient((string) $email->getHeaderValue(HeaderConsts::TO));

        if (! $company) {
            return response('', 200);
        }

        foreach ($email->getAllAttachmentParts() as $attachment) {
            $this->ingestAttachment($ingestionService, $company, $attachment);
        }

        return response('', 200);
    }

    /**
     * @param  string  $bucket
     * @param  string  $key
     * @return string Contenido crudo del correo (el .eml completo, con headers y adjuntos).
     */
    private function downloadRawEmail(string $bucket, string $key): string
    {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => config('services.ses.region'),
            'credentials' => [
                'key' => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);

        return (string) $s3->getObject(['Bucket' => $bucket, 'Key' => $key])['Body'];
    }

    /**
     * Saca el token de recepción del alias al que llegó el correo (el "To": "recepcion-<token>@...")
     * y resuelve a qué empresa le pertenece -- ver Company::ensureReceptionEmailToken().
     *
     * @param  string  $to
     * @return Company|null
     */
    private function resolveCompanyFromRecipient(string $to): ?Company
    {
        if (! preg_match('/recepcion-([a-f0-9]+)@/i', $to, $matches)) {
            Log::warning('Correo entrante SES: no se pudo sacar el alias de recepción del "To".', ['to' => $to]);

            return null;
        }

        $company = Company::findByReceptionEmailToken(strtolower($matches[1]));

        if (! $company) {
            Log::warning('Correo entrante SES: no hay ninguna empresa con ese token de recepción.', ['token' => $matches[1]]);
        }

        return $company;
    }

    /**
     * Guarda el adjunto en un archivo temporal y lo procesa igual que un archivo subido a mano
     * (ver ReceivedDocumentIngestionService::ingest()) -- ignora en silencio los adjuntos que no
     * sean .xml/.zip (firmas de correo, logos incrustados, etc.), y deja un log (sin abortar el
     * resto del correo) si el que sí parece un documento falla al procesarse.
     */
    private function ingestAttachment(ReceivedDocumentIngestionService $ingestionService, Company $company, IMessagePart $attachment): void
    {
        $filename = $attachment->getFilename() ?: 'documento';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xml', 'zip'], true)) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ses-inbound-');

        try {
            file_put_contents($tempPath, $attachment->getBinaryContentStream()->getContents());
            $ingestionService->ingest($company, $tempPath, $extension, $filename, null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            Log::warning('Correo entrante SES: no se pudo procesar un adjunto.', [
                'company_id' => (string) $company->_id,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }
}
