<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOwner
{
    /**
     * Exige que el usuario tenga acceso administrativo a la empresa activa
     * (sea 'owner', o 'administrador' en al menos uno de sus módulos). Usado
     * para acciones a nivel de compañía (editarla, gestionar miembros, etc.)
     * que no pertenecen a un módulo específico.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('selected_company.role') === 'owner') {
            return $next($request);
        }

        if (in_array('administrador', session('selected_company.modules', []), true)) {
            return $next($request);
        }

        abort(403);
    }
}
