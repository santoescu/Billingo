<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\LeadEmailLog;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Database\Eloquent\Model;
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

        // Busca primero en EmailLog (documentos a clientes) y si no, en LeadEmailLog (correo en
        // frío a prospectos, ver Lead/AdminLeadController) -- ambos comparten la misma cuenta de
        // SES/Configuration Set, así que un mismo Message-ID solo puede estar en uno de los dos.
        $log = EmailLog::where('ses_message_id', $messageId)->first()
            ?? LeadEmailLog::where('ses_message_id', $messageId)->first();

        if (! $log) {
            Log::info('Evento de correo SES: no hay ningún EmailLog/LeadEmailLog con ese Message-ID.', ['message_id' => $messageId]);

            return response('', 200);
        }

        $this->applyEvent($log, $event);

        return response('', 200);
    }

    private function applyEvent(Model $log, array $event): void
    {
        match ($event['eventType'] ?? null) {
            'Delivery' => $log->update(['delivered_at' => $log->delivered_at ?? now()]),
            'Open' => $log->update(['opened_at' => $log->opened_at ?? now()]),
            'Click' => $log->update([
                'clicked_at' => $log->clicked_at ?? now(),
                'clicked_link' => $event['click']['link'] ?? null,
            ]),
            'Bounce' => $log->update([
                'bounced_at' => now(),
                'bounce_reason' => $event['bounce']['bouncedRecipients'][0]['diagnosticCode']
                    ?? $event['bounce']['bounceType']
                    ?? null,
            ]),
            // "complaintFeedbackType" (ej. "abuse", "not-spam", "virus") solo viene cuando el
            // proveedor de correo del destinatario lo reporta como parte del feedback loop -- no
            // todos lo hacen, así que puede quedar en null aunque sí haya habido una queja.
            'Complaint' => $log->update([
                'complained_at' => now(),
                'complaint_reason' => $event['complaint']['complaintFeedbackType'] ?? null,
            ]),
            default => null,
        };
    }
}
