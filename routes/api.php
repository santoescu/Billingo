<?php

use App\Http\Controllers\Api\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['company.api_token'])->group(function () {
    Route::post('documentos', [DocumentoController::class, 'store'])
        ->middleware('company.api_feature:documentos.store')
        ->name('api.documentos.store');

    Route::post('documentos/import-uuid', [DocumentoController::class, 'importUuid'])
        ->middleware('company.api_feature:documentos.import-uuid')
        ->name('api.documentos.import-uuid');
});
