<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Documentación pública de la API (OpenAPI 3.1 + Scalar) -- sin auth, para que
 * quien vaya a integrarse (o su desarrollador) pueda consultarla sin tener cuenta en
 * Billingo. No expone ningún dato sensible, solo la forma de las peticiones/respuestas.
 */
class ApiDocsController extends Controller
{
    /**
     * Pantalla con el visor de Scalar, apuntando al spec servido por openapi().
     */
    public function index()
    {
        return view('docs.api');
    }

    /**
     * Sirve el archivo YAML del spec tal cual está en el repo (resources/openapi/),
     * para no tener que copiarlo/regenerarlo en public/ cada vez que se edita.
     */
    public function openapi(): Response
    {
        $path = resource_path('openapi/billingo-api.yaml');

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml',
        ]);
    }
}
