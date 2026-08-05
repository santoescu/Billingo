<?php

use App\Http\Controllers\Api\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['company.api_token'])->group(function () {
    Route::post('documentos', [DocumentoController::class, 'store'])->name('api.documentos.store');
});
