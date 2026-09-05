<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyApiFeature
{
    /**
     * Bloquea el acceso a una feature puntual de la API (ver config/api_features.php)
     * para la empresa resuelta por AuthenticateCompanyApiToken -- debe correr después de
     * ese middleware, ya que depende de "company" ya estar en el request. Un token válido
     * no es suficiente: la empresa también necesita el módulo del que depende esta feature
     * activo, y la feature en concreto habilitada para ella (ver Company::hasApiFeature()).
     *
     * @param  string  $feature  Clave de config/api_features.php (ej. "documentos.import-uuid").
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $company = $request->attributes->get('company');
        $module = config("api_features.{$feature}.module");

        if ($module && ! in_array($module, $company->modules ?? [], true)) {
            return response()->json(['message' => __('This company does not have the required module active for this API.')], 403);
        }

        if (! $company->hasApiFeature($feature)) {
            return response()->json(['message' => __('This API is not enabled for this company.')], 403);
        }

        return $next($request);
    }
}
