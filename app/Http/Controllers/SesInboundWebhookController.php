<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Dian\ReceivedDocumentIngestionService;
use App\Services\Dian\ReceivedDocumentParser;
use Aws\S3\S3Client;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

class SesInboundWebhookController extends Controller
{
    /**
     * Recibe el aviso de SNS de que llegó un correo nuevo a la casilla de recepción compartida
     * (una sola dirección para todas las empresas, ver config('services.ses.inbound_address')),
     * lo baja de S3 (ahí es donde SES deja el correo crudo completo), y procesa cada adjunto
     * XML/zip con el mismo servicio que usa la subida manual (ver
     * ReceivedDocumentIngestionService::ingest()) -- así los dos caminos (subir a mano, o que
     * llegue por correo) terminan guardando el documento exactamente igual.
     *
     * Como la dirección es compartida, la empresa dueña de cada adjunto se resuelve leyendo el
     * NIT del comprador (accounting_customer_party) que ya viene dentro del propio XML -- no
     * hace falta un alias distinto por empresa (ver resolveCompanyFromIdentificacion()).
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
    public function handle(Request $request, ReceivedDocumentIngestionService $ingestionService, ReceivedDocumentParser $parser)
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

        foreach ($email->getAllAttachmentParts() as $attachment) {
            $this->ingestAttachment($ingestionService, $parser, $attachment);
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
     * .xml/.zip (firmas de correo, logos incrustados, etc.), y deja un log (sin abortar el resto
     * del correo) si el que sí parece un documento falla al procesarse o resolverse.
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
}
