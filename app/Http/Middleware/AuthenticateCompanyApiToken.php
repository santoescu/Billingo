<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanyApiToken
{
    /**
     * Autentica una petición de la API por el token de la empresa (header
     * "Authorization: Bearer <token>") y deja la Company resuelta disponible
     * en el request como "company".
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => __('Missing API token.')], 401);
        }

        $company = Company::findByApiToken($token);

        if (! $company) {
            return response()->json(['message' => __('Invalid API token.')], 401);
        }

        $request->attributes->set('company', $company);

        return $next($request);
    }
}
