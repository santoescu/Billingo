<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        // Dominio donde se reciben las facturas por correo (ver SesInboundWebhookController) --
        // ej. "recepcion.billingo.com.co". El bucket es donde SES deja el correo crudo antes de
        // avisar por SNS. Es una sola dirección compartida por todas las empresas -- cada
        // adjunto se resuelve a su empresa leyendo el NIT del comprador dentro del propio XML,
        // no hace falta un alias distinto por empresa.
        'inbound_domain' => env('AWS_SES_INBOUND_DOMAIN'),
        'inbound_address' => env('AWS_SES_INBOUND_DOMAIN') ? 'documentos@'.env('AWS_SES_INBOUND_DOMAIN') : null,
        'inbound_bucket' => env('AWS_SES_INBOUND_BUCKET'),
        // "options" se manda tal cual en cada sendRawEmail() de Laravel (ver
        // Illuminate\Mail\MailManager::createSesTransport()) -- el Configuration Set es lo que
        // hace que SES publique los eventos de entregado/abierto/rebotado/spam al tópico de SNS
        // que escucha SesEventWebhookController. Sin este valor, los correos se mandan igual,
        // pero no se generan esos eventos (el EmailLog se queda pegado en "Enviado" para siempre).
        'options' => array_filter([
            'ConfigurationSetName' => env('AWS_SES_CONFIGURATION_SET'),
        ]),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Secreto compartido con Cloud Scheduler para poder llamar a
    // /api/internal/queue-work sin exponerlo públicamente (ver QueueWorkerController).
    'queue_worker' => [
        'secret' => env('QUEUE_WORKER_SECRET'),
    ],

    'dian' => [
        
        'endpoint' => env('DIAN_ENDPOINT', 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc'),
    ],

];
