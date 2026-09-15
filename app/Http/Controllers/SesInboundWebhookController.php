<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundEmailJob;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SesInboundWebhookController extends Controller
{
    /**
     * Recibe el aviso de SNS de que llegó un correo nuevo a la casilla de recepción compartida
     * (una sola dirección para todas las empresas, ver config('services.ses.inbound_address')) y
     * despacha ProcessInboundEmailJob para que lo baje de S3, lo parsee y lo guarde en segundo
     * plano -- este método responde 200 de inmediato sin hacer ese trabajo en la misma petición
     * HTTP. Antes sí se procesaba acá directo, pero una ráfaga de varios correos casi
     * simultáneos (ej. reenvío masivo desde Gmail) podía saturar los workers PHP del servidor
     * web y perder documentos silenciosamente (ver incidente del 2026-09-15).
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
    public function handle(Request $request)
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

        ProcessInboundEmailJob::dispatch($bucket, $key);

        return response('', 200);
    }
}
