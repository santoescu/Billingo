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
            });
        </script>
    </body>
</html>
