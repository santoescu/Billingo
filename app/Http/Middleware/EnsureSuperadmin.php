<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    /**
     * Exige que el usuario autenticado tenga un rol global de administración
     * (acceso a todo el sistema, sin importar de qué empresa sea miembro o dueño).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isGlobalAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
