<?php

use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\SesEventWebhookController;
use App\Http\Controllers\SesInboundWebhookController;
use Illuminate\Support\Facades\Route;

// Sin "company.api_token": los llama Amazon SNS, no una empresa con su propio token -- la
// autenticidad se valida adentro con la firma del mensaje de SNS (ver
// SesInboundWebhookController::handle() y SesEventWebhookController::handle()), no con nuestro
// esquema de token normal. Dos tópicos de SNS distintos: uno para correos NUEVOS que llegan a la
// casilla de recepción, otro para EVENTOS (entregado/abierto/rebotado/spam) de correos que
// Billingo mandó.
Route::post('webhooks/ses-inbound', [SesInboundWebhookController::class, 'handle'])->name('api.webhooks.ses-inbound');
Route::post('webhooks/ses-events', [SesEventWebhookController::class, 'handle'])->name('api.webhooks.ses-events');

Route::middleware(['company.api_token'])->group(function () {
    Route::post('documentos', [DocumentoController::class, 'store'])
        ->middleware('company.api_feature:documentos.store')
        ->name('api.documentos.store');

    Route::post('documentos/import-uuid', [DocumentoController::class, 'importUuid'])
        ->middleware('company.api_feature:documentos.import-uuid')
        ->name('api.documentos.import-uuid');
});
