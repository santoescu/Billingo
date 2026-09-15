<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

/**
 * Guarda en el EmailLog correspondiente el "Message-ID" real que SES le asignó al correo, una
 * vez Symfony ya lo mandó de verdad -- necesario porque DocumentIssuedMail se manda en cola (ver
 * DocumentoEmitidoController::sendEmail()), así que ese dato no existe todavía en el momento en
 * que se crea el EmailLog (antes de encolarlo); solo se conoce después de que el job corre y SES
 * responde. Se correlaciona con el header "X-Billingo-Email-Log-Id" que el propio Mailable le
 * agrega al mensaje antes de encolarlo.
 */
class RecordSesMessageId
{
    public function handle(MessageSent $event): void
    {
        $headers = $event->sent->getOriginalMessage()->getHeaders();

        $emailLogId = $headers->get('X-Billingo-Email-Log-Id')?->getBodyAsString();

        if (! $emailLogId) {
            return;
        }

        // Ver el mismo comentario en DocumentoEmitidoController::sendEmail() sobre por qué este
        // header (que SesTransport agrega aparte, no es el Message-ID que genera Symfony) es el
        // que hay que guardar -- sin MAIL_MAILER=ses (ej. MAIL_MAILER=log en desarrollo) no
        // existe, y el correo simplemente queda sin tracking de eventos.
        $sesMessageId = $headers->get('X-SES-Message-ID')?->getBodyAsString();

        EmailLog::where('_id', $emailLogId)->update([
            'ses_message_id' => $sesMessageId,
            'sent_at' => now(),
        ]);
    }
}
