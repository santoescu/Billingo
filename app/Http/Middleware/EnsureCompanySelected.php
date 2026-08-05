<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    /**
     * Exige que el usuario tenga una empresa seleccionada en sesión
     * antes de entrar a módulos que dependen de ella (clientes, productos, facturas...).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('selected_company')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
