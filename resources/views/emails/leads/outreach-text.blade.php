@php
    $name = $lead->contact_name ?: 'equipo de ' . $lead->razon_social;
@endphp
@if ($variant === \App\Mail\LeadOutreachMail::VARIANT_INITIAL)
Hola {{ $name }},

@if ($lead->pitch_note)
{{ $lead->pitch_note }}

@endif
Por lo que he visto, la mayoría de negocios así terminan facturando en un sistema, vendiendo en otro, y llevando cotizaciones en Excel o WhatsApp -- cada uno con su propio listado de clientes. El resultado casi siempre es el mismo: digitar el mismo cliente varias veces, y enterarse de un rechazo de la DIAN cuando ya es tarde para corregirlo.

Armamos Billingo (https://billingo.com.co) para que facturación, punto de venta, cotizaciones y recepción de documentos compartan el mismo cliente e inventario desde el día uno -- y la factura se valida ante la DIAN antes de mandarse, no después. Lo usamos nosotros mismos todos los días para operar.

¿Te interesaría ver cómo se vería para {{ $lead->razon_social }}?

@elseif ($variant === \App\Mail\LeadOutreachMail::VARIANT_FOLLOWUP)
Hola {{ $name }},

Una cosa que veo seguido: un negocio manda una factura, la DIAN la rechaza horas o días después, y para cuando se enteran ya tienen que rehacer todo el proceso con el cliente.

Billingo (https://billingo.com.co) valida el documento contra las reglas de la DIAN antes de enviarlo, así que ese tipo de sorpresa no pasa.

¿Vale la pena que te muestre cómo funciona con un ejemplo real?

@else
Hola {{ $name }},

No quiero llenarte la bandeja -- este es mi último correo.

Si en algún momento la facturación/punto de venta/cotizaciones sueltas te empiezan a pesar, quedo atento. Aquí te dejo el link por si quieres echarle un ojo cuando te sirva: https://billingo.com.co

Éxitos con {{ $lead->razon_social }}.
@endif
