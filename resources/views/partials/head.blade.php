<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>{{ $title ?? 'Billingo' }}</title>

<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/x-icon" href="/favicon.ico" sizes="any">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

{{--
    Schema.org Organization, sitio entero -- ver ai-seo skill: ayuda a que los motores de IA
    (ChatGPT, Perplexity, Gemini) identifiquen a Billingo como entidad, no solo como texto
    suelto. Solo importa de verdad en las páginas públicas (esta misma vista se comparte con el
    layout autenticado, pero eso no es rastreable de todos modos).

    El array va dentro de @php/@endphp a propósito: Blade escanea el texto de la plantilla en
    busca de directivas ANTES de importarle si está dentro de un string PHP, así que
    "'@context' => ..." escrito directo en el HTML se compilaba como si fuera una directiva de
    Blade (probablemente @context de algún paquete) y rompía el JSON. Dentro de @php/@endphp
    Blade no toca el contenido, es PHP crudo.
--}}
@php
    $organizationSchema = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Billingo',
        'url' => url('/'),
        'logo' => asset('images/billingo-logo-email.png'),
        'description' => 'Plataforma de facturación electrónica, punto de venta, cotizaciones, nómina y recepción de documentos para pymes colombianas, validada ante la DIAN.',
        'areaServed' => 'CO',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
<script type="application/ld+json">{!! $organizationSchema !!}</script>

@stack('head')

@vite(['resources/css/app.css', 'resources/js/app.js'])
