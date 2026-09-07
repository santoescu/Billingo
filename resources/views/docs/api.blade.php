<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <title>{{ __('Billingo API — Documentation') }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <div id="app"></div>
        <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
        <script>
            Scalar.createApiReference('#app', {
                url: '{{ route('api-docs.openapi') }}',
                // No se esconde el panel de cliente/request: es donde Scalar
                // muestra el selector de ejemplos (requestBody.examples) de
                // cada endpoint, al lado derecho junto al response.
                hiddenClients: false,
                // Por defecto Scalar ordena las propiedades de cada schema
                // alfabéticamente, sin importar el orden real del YAML -- con esto
                // respeta el orden en que están escritas (que sigue el orden del
                // anexo técnico de la DIAN).
                orderSchemaPropertiesBy: 'preserve',
                orderRequiredPropertiesFirst: false,
                localization: {
                    locale: 'es',
                },
            });
        </script>
    </body>
</html>
