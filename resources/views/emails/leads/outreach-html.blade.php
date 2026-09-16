@php
    $name = $lead->contact_name ?: 'equipo de ' . $lead->razon_social;
    $link = '<a href="https://billingo.com.co" style="color:inherit;">https://billingo.com.co</a>';
@endphp
<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #1f2937; line-height: 1.5;">
@if ($variant === \App\Mail\LeadOutreachMail::VARIANT_INITIAL)
<p>Hola {{ $name }},</p>

@if ($lead->pitch_note)
<p>{{ $lead->pitch_note }}</p>
@endif

<p>Por lo que he visto, la mayoría de negocios así terminan facturando en un sistema, vendiendo en otro, y llevando cotizaciones en Excel o WhatsApp -- cada uno con su propio listado de clientes. El resultado casi siempre es el mismo: digitar el mismo cliente varias veces, y enterarse de un rechazo de la DIAN cuando ya es tarde para corregirlo.</p>

<p>Armamos Billingo ({!! $link !!}) para que facturación, punto de venta, cotizaciones y recepción de documentos compartan el mismo cliente e inventario desde el día uno -- y la factura se valida ante la DIAN antes de mandarse, no después. Lo usamos nosotros mismos todos los días para operar.</p>

<p>¿Te interesaría ver cómo se vería para {{ $lead->razon_social }}?</p>
@elseif ($variant === \App\Mail\LeadOutreachMail::VARIANT_FOLLOWUP)
<p>Hola {{ $name }},</p>

<p>Una cosa que veo seguido: un negocio manda una factura, la DIAN la rechaza horas o días después, y para cuando se enteran ya tienen que rehacer todo el proceso con el cliente.</p>

<p>Billingo ({!! $link !!}) valida el documento contra las reglas de la DIAN antes de enviarlo, así que ese tipo de sorpresa no pasa.</p>

<p>¿Vale la pena que te muestre cómo funciona con un ejemplo real?</p>
@else
<p>Hola {{ $name }},</p>

<p>No quiero llenarte la bandeja -- este es mi último correo.</p>

<p>Si en algún momento la facturación/punto de venta/cotizaciones sueltas te empiezan a pesar, quedo atento. Aquí te dejo el link por si quieres echarle un ojo cuando te sirva: {!! $link !!}</p>

<p>Éxitos con {{ $lead->razon_social }}.</p>
@endif
</body>
</html>
