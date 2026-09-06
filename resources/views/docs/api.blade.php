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
                // El ejemplo de JSON a enviar ya no depende de esto -- vive como
                // un bloque ```json``` dentro de la descripción de cada endpoint
                // (ver resources/openapi/billingo-api.yaml), igual que se ve el
                // JSON de las respuestas. Por eso ahora sí se puede esconder el
                // selector de lenguajes por completo sin perder el ejemplo.
                hiddenClients: true,
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
