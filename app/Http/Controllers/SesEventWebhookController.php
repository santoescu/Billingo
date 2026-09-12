<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SesEventWebhookController extends Controller
{
    /**
     * Recibe los eventos de entrega/apertura/rebote/spam de un correo que Billingo mandó (ver
     * DocumentoEmitidoController::sendEmail()) -- misma validación de firma de SNS que
     * SesInboundWebhookController, pero un tópico distinto: este es para EVENTOS de correos que
     * Billingo mandó, el otro es para CORREOS NUEVOS que le llegaron a la casilla de recepción.
     * Necesita que el "Configuration Set" de SES (ver services.ses.options.ConfigurationSetName)
     * tenga este tópico configurado como destino de sus eventos "Send, Delivery, Bounce,
     * Complaint, Open, Click".
     *
     * Siempre responde 200 salvo firma inválida (403) -- mismo criterio que
     * SesInboundWebhookController::handle() para que SNS no reintente por cosas que no se van a
     * resolver solas (ej. un Message-ID que no encontramos, porque se mandó antes de tener este
     * webhook armado).
     */
    public function handle(Request $request)
    {
        try {
            $message = SnsMessage::fromJsonString($request->getContent());
            (new MessageValidator())->validate($message);
        } catch (Throwable $e) {
            Log::warning('Evento de correo SES: mensaje de SNS inválido, se descarta.', ['error' => $e->getMessage()]);

            return response('', 403);
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            file_get_contents($message['SubscribeURL']);

            return response('', 200);
        }

        if ($message['Type'] !== 'Notification') {
            return response('', 200);
        }

        $event = json_decode($message['Message'], true);
        $messageId = $event['mail']['messageId'] ?? null;

        if (! $messageId) {
            return response('', 200);
        }

        $log = EmailLog::where('ses_message_id', $messageId)->first();

        if (! $log) {
            Log::info('Evento de correo SES: no hay ningún EmailLog con ese Message-ID.', ['message_id' => $messageId]);

            return response('', 200);
        }

        match ($event['eventType'] ?? null) {
            'Delivery' => $log->update(['delivered_at' => $log->delivered_at ?? now()]),
            'Open' => $log->update(['opened_at' => $log->opened_at ?? now()]),
            'Bounce' => $log->update([
                'bounced_at' => now(),
                'bounce_reason' => $event['bounce']['bouncedRecipients'][0]['diagnosticCode']
                    ?? $event['bounce']['bounceType']
                    ?? null,
            ]),
            'Complaint' => $log->update(['complained_at' => now()]),
            default => null,
        };

        return response('', 200);
    }
}
