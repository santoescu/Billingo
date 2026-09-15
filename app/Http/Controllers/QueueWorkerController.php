<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class QueueWorkerController extends Controller
{
    /**
     * Procesa lo que haya pendiente en la cola ("jobs" en Mongo) y se apaga solo cuando la
     * vacía -- pensado para Cloud Run/App Engine, donde no hay un servidor persistente en el que
     * dejar corriendo "php artisan queue:work" para siempre (a diferencia de una VM con
     * supervisor). Cloud Scheduler llama esta ruta cada minuto (ver docs de despliegue), así que
     * nunca queda un job esperando más de ese margen.
     *
     * Protegido por un secreto compartido en vez de por sesión/company.api_token -- lo llama
     * Cloud Scheduler, no un usuario ni una empresa.
     */
    public function run(Request $request)
    {
        $secret = config('services.queue_worker.secret');
        abort_if(! $secret || ! hash_equals($secret, (string) $request->header('X-Queue-Worker-Secret')), 403);

        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 50,
            '--tries' => 3,
        ]);

        return response(Artisan::output(), 200);
    }
}
