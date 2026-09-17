---
title: "Factura electrónica rechazada por la DIAN: causas comunes y cómo evitarla"
meta_description: "Las causas más frecuentes de rechazo de una factura electrónica en Colombia y cómo evitarlas antes de que el cliente se entere."
target_keyword: "factura electrónica rechazada DIAN"
buyer_stage: "Awareness (dolor)"
content_type: "How-to"
pillar: "Cumplimiento DIAN"
status: "published"
published_at: "2026-09-15"
---

# Factura electrónica rechazada por la DIAN: causas comunes y cómo evitarla

Le enviaste la factura a tu cliente, tu sistema dice "enviado" — y horas después te enteras de que la DIAN la rechazó. Ahora tienes que corregir, reemitir, y explicarle al cliente por qué la factura que ya tiene en su bandeja no sirve. Es uno de los problemas más frustrantes de facturar electrónicamente, y casi siempre es evitable.

## Por qué se rechaza una factura que "sí se envió"

Que un documento se haya *enviado* no significa que haya *pasado* la validación de la DIAN. El envío es solo el transporte; la DIAN revisa la estructura y el contenido del XML después de recibirlo, y si algo no cuadra, lo rechaza — independientemente de que tu software haya mostrado "enviado exitosamente".

## Las causas más comunes

**1. Identificación del cliente mal digitada o inválida**
Un NIT sin dígito de verificación correcto, una cédula con un dígito de más o de menos, o un tipo de identificación que no corresponde a la persona (por ejemplo, marcar NIT para una persona natural). Es, con diferencia, la causa más frecuente.

**2. Resolución de numeración vencida o agotada**
Si tu resolución venció o ya usaste todo el rango autorizado, cada documento que intentes emitir con esa numeración se rechaza — sin importar que todo lo demás esté perfecto. *(Ver [resolución de facturación electrónica DIAN].)*

**3. Impuestos mal calculados o mal asociados**
Un ítem que debería llevar IVA y no lo lleva, o un porcentaje de impuesto que no corresponde al tipo de producto/servicio. La DIAN valida que los totales cuadren matemáticamente con los impuestos declarados por línea.

**4. Firma digital vencida o mal configurada**
Si el certificado de firma digital expiró, o quedó mal asociado al software, el documento no pasa la validación de autenticidad — aunque el contenido esté perfecto.

**5. Formato XML/UBL malformado**
Un campo obligatorio vacío, una estructura que no sigue exactamente el estándar UBL que exige la DIAN. Esto casi nunca lo ve el usuario directamente — depende de que el software que generó el XML lo haya hecho bien desde el principio.

**6. Totales que no cuadran**
La suma de los ítems, menos descuentos, más impuestos, tiene que coincidir exactamente con el total del documento. Un error de redondeo mal manejado es suficiente para el rechazo.

## Cómo evitarlo: validar antes de enviar, no después

La diferencia real está en el momento en que se detecta el error. Un software que solo transmite el documento y espera la respuesta de la DIAN te avisa del error **después** — cuando el cliente ya lo tiene o ya lo está esperando.

Un software que **valida la estructura y los datos antes de enviar** — identificación, impuestos, totales, numeración vigente — te muestra el error en el momento en que lo estás armando, mientras todavía lo puedes corregir sin que nadie más se entere.

## Qué hacer si ya te rechazaron una factura

1. Revisa el motivo exacto que reporta la DIAN (no es genérico, casi siempre dice qué campo falló).
2. Corrige el dato específico — no reemitas a ciegas.
3. Vuelve a emitir el documento con el mismo número (o el siguiente disponible, según el caso) — el original rechazado no cuenta como consumido de tu rango.
4. Si es un patrón repetido (por ejemplo, siempre falla en el mismo campo), revisa la configuración de tu software, no solo el documento puntual.

---

**Cómo lo evita Billingo:** cada documento se valida contra las reglas de la DIAN — identificación, impuestos, totales, numeración vigente — **antes** de intentar enviarlo. Si algo falla, lo ves en el momento, no cuando el cliente ya está preguntando por su factura. [Conoce cómo funciona →](https://billingo.com.co)
