<?php

namespace App\Services\Dian;

use InvalidArgumentException;

class DocumentTotalsCalculator
{
    /**
     * Punto de entrada único: calcula líneas, impuestos agrupados, cargos y
     * totales del documento completo, en el mismo orden/criterio que antes
     * hacían las primeras líneas de UblDocumentBuilder::build().
     *
     * @param  array  $lineasPayload  Líneas del documento tal como vienen en el payload.
     * @param  array  $cargosPayload  Bloque "cargos_descuentos" del payload.
     * @return array{lineas: array, impuestos: array, line_extension_amount: float, cargos: array, totales: array}
     */
    public function calcularTotalesDocumento(array $lineasPayload, array $cargosPayload): array
    {
        $lineas = $this->buildLineasCalculadas($lineasPayload);
        $impuestos = $this->agruparImpuestos($lineas);
        $lineExtensionAmount = round(array_sum(array_column($lineas, 'line_extension_amount')), 2);
        $taxExclusiveAmount = round(array_sum(array_column($lineas, 'primer_tributo_taxable_amount')), 2);

        $cargos = $this->buildCargosCalculados($cargosPayload);
        $totales = $this->calcularTotales($lineExtensionAmount, $taxExclusiveAmount, $impuestos, $cargos);

        return [
            'lineas' => $lineas,
            'impuestos' => $impuestos,
            'line_extension_amount' => $lineExtensionAmount,
            'cargos' => $cargos,
            'totales' => $totales,
        ];
    }

    /**
     * Calcula los valores derivados de cada línea (subtotal, descuento, impuestos)
     * a partir de los datos "crudos" que vienen en el JSON.
     *
     * @param  array  $lineasPayload  Líneas del documento tal como vienen en el payload.
     * @return array Líneas con los montos calculados.
     */
    public function buildLineasCalculadas(array $lineasPayload): array
    {
        if (empty($lineasPayload)) {
            throw new InvalidArgumentException('El documento debe tener al menos una línea.');
        }

        return array_map(function (array $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 1);
            $precioUnitario = (float) ($linea['precio_unitario'] ?? 0);
            $baseQuantity = (float) ($linea['precio_base_quantity'] ?? 1) ?: 1.0;
            // LineExtensionAmount = Cantidad x (Price.PriceAmount / Price.BaseQuantity) -- anexo
            // técnico, regla FAV06/FAV07: "PriceAmount" es el valor por "BaseQuantity" unidades,
            // no necesariamente por una sola unidad (ej. PriceAmount=10000 con BaseQuantity=6
            // significa $10000 cada 6 unidades).
            $baseAmount = round($cantidad * ($precioUnitario / $baseQuantity), 2);

            $cargosDescuentos = array_map(function (array $cargo) {
                return [
                    'es_descuento' => ($cargo['tipo'] ?? 'descuento') !== 'cargo',
                    'motivo' => $cargo['motivo'] ?: (($cargo['tipo'] ?? 'descuento') !== 'cargo' ? 'Descuento' : 'Cargo'),
                    'porcentaje' => $cargo['porcentaje'],
                    'base_amount' => $cargo['base_amount'],
                    'amount' => $cargo['amount'],
                ];
            }, $linea['cargos_descuentos'] ?? []);

            $descuentoAmount = round(array_sum(array_column(array_filter($cargosDescuentos, fn (array $c) => $c['es_descuento']), 'amount')), 2);
            $cargoAmount = round(array_sum(array_column(array_filter($cargosDescuentos, fn (array $c) => ! $c['es_descuento']), 'amount')), 2);

            $lineExtensionAmount = round($baseAmount - $descuentoAmount + $cargoAmount, 2);

            if (isset($linea['line_extension_amount_expected'])) {
                $this->assertLineExtensionAmountMatches(
                    (float) $linea['line_extension_amount_expected'],
                    $lineExtensionAmount,
                    $linea['codigo'] ?? $linea['descripcion'] ?? '(sin código)'
                );
            }

            $impuestosLinea = [];
            foreach ($linea['impuestos'] ?? [] as $impuesto) {
                $porcentaje = (float) $impuesto['porcentaje'];
                $baseGravable = (float) $impuesto['base_gravable'];

                $this->assertTaxableAmountMatches($baseGravable, $lineExtensionAmount, $linea['codigo'] ?? $linea['descripcion'] ?? '(sin código)');

                $perUnitAmount = $impuesto['per_unit_amount'] ?? null;
                $baseUnitMeasure = $impuesto['base_unit_measure'] ?? null;

                $taxAmount = $perUnitAmount !== null
                    ? round($perUnitAmount * $baseUnitMeasure / 100, 2)
                    : round($baseGravable * ($porcentaje / 100), 2);

                if (isset($impuesto['tax_amount_expected'])) {
                    $this->assertSubtotalTaxAmountMatches(
                        (float) $impuesto['tax_amount_expected'],
                        $taxAmount,
                        $linea['codigo'] ?? $linea['descripcion'] ?? '(sin código)',
                        $impuesto['tipo']
                    );
                }

                $impuestosLinea[] = [
                    'codigo' => $impuesto['tipo'],
                    'nombre' => $impuesto['nombre'] ?? $this->nombreImpuesto($impuesto['tipo']),
                    'porcentaje' => $porcentaje,
                    'base_gravable' => $baseGravable,
                    'tax_amount' => $taxAmount,
                    'per_unit_amount' => $perUnitAmount,
                    'base_unit_measure' => $baseUnitMeasure,
                    'base_unit_measure_unit_code' => $impuesto['base_unit_measure_unit_code'] ?? null,
                ];
            }

            return [
                'codigo' => $linea['codigo'] ?? null,
                'codigo_barras' => $linea['codigo_barras'] ?? null,
                'codigo_barras_scheme_id' => $linea['codigo_barras_scheme_id'] ?? '999',
                'descripcion' => $linea['descripcion'] ?? '',
                'marca' => $linea['marca'] ?? [],
                'modelo' => $linea['modelo'] ?? [],
                'mandante' => $linea['mandante'] ?? null,
                'cantidad' => $cantidad,
                'unidad_medida' => $linea['unidad_medida'] ?? 'EA',
                'precio_unitario' => $precioUnitario,
                'precio_base_quantity' => (float) ($linea['precio_base_quantity'] ?? 1),
                'base_amount' => $baseAmount,
                'cargos_descuentos' => $cargosDescuentos,
                'line_extension_amount' => $lineExtensionAmount,
                'impuestos' => $impuestosLinea,
                'primer_tributo_taxable_amount' => $this->primerTributoTaxableAmount($impuestosLinea),
            ];
        }, $lineasPayload);
    }

    /**
     * Suma las bases gravables del primer tributo de la línea (el primer código distinto en
     * orden de aparición, con todas sus tarifas si tiene varias) -- el anexo técnico define
     * "Base Imponible" del documento (regla FAU04) como
     * "sum(//cac:InvoiceLine/cac:TaxTotal[1]/cac:TaxSubtotal/cbc:TaxableAmount)", es decir solo
     * el primer "cac:TaxTotal" de cada línea, no todos los tributos de la línea.
     *
     * @param  array  $impuestosLinea  Impuestos ya calculados de una línea (ver buildLineasCalculadas()).
     * @return float Suma de "base_gravable" del primer tributo de la línea (0 si no tiene impuestos).
     */
    private function primerTributoTaxableAmount(array $impuestosLinea): float
    {
        if (empty($impuestosLinea)) {
            return 0.0;
        }

        $primerCodigo = $impuestosLinea[0]['codigo'];

        return round(array_sum(array_column(
            array_filter($impuestosLinea, fn (array $i) => $i['codigo'] === $primerCodigo),
            'base_gravable'
        )), 2);
    }

    /**
     * Si el caller mandó "InvoiceLine.LineExtensionAmount" para una línea, lo compara contra
     * lo que Billingo calculó (cantidad x precio, menos el descuento de línea) y rechaza el
     * documento explicando la diferencia si no coincide, en vez de emitirlo con un valor que
     * el caller no esperaba.
     *
     * @param  float  $esperado  "LineExtensionAmount" tal como lo mandó el caller.
     * @param  float  $calculado  Valor calculado por Billingo para esa línea.
     * @param  string  $identificadorLinea  Código o descripción de la línea, para el mensaje de error.
     *
     * @throws InvalidArgumentException Si no coincide (tolerancia de 1 centavo).
     */
    private function assertLineExtensionAmountMatches(float $esperado, float $calculado, string $identificadorLinea): void
    {
        if (abs($esperado - $calculado) > 0.01) {
            throw new InvalidArgumentException(
                "InvoiceLine.LineExtensionAmount de la línea \"{$identificadorLinea}\" ({$esperado}) no coincide con el valor calculado por Billingo a partir de cantidad, precio y descuento ({$calculado})."
            );
        }
    }

    /**
     * La DIAN exige que "TaxableAmount" (la base gravable de cada impuesto de la línea) sea
     * obligatorio (anexo técnico, regla FAX05) -- Billingo no lo calcula, pero sí valida que
     * ningún tributo de la línea tenga una base gravable mayor a "LineExtensionAmount" de esa
     * línea (varios tributos de una misma línea pueden repartirse la base, ej. IVA sobre una
     * porción e INC sobre otra, pero ninguno puede excederla).
     *
     * @param  float  $esperado  "TaxableAmount" tal como lo mandó el caller.
     * @param  float  $lineExtensionAmount  "LineExtensionAmount" ya calculado de la línea.
     * @param  string  $identificadorLinea  Código o descripción de la línea, para el mensaje de error.
     *
     * @throws InvalidArgumentException Si la excede (tolerancia de 1 centavo).
     */
    private function assertTaxableAmountMatches(float $esperado, float $lineExtensionAmount, string $identificadorLinea): void
    {
        if ($esperado - $lineExtensionAmount > 0.01) {
            throw new InvalidArgumentException(
                "Lines.TaxTotal.TaxSubtotal.TaxableAmount de la línea \"{$identificadorLinea}\" ({$esperado}) no puede ser mayor que el \"LineExtensionAmount\" de esa línea ({$lineExtensionAmount})."
            );
        }
    }

    /**
     * "TaxSubtotal.TaxAmount" (el valor del tributo de un solo "TaxSubtotal") -- Billingo no lo
     * calcula para armar el XML, lo valida contra lo que calculó para ese tributo puntual
     * ("TaxableAmount x Percent / 100" o, para tributos nominales, "PerUnitAmount x
     * BaseUnitMeasure / 100") y rechaza la petición explicando la diferencia si no coincide.
     *
     * @param  float  $esperado  "TaxSubtotal.TaxAmount" tal como lo mandó el caller.
     * @param  float  $calculado  Valor calculado por Billingo para ese tributo.
     * @param  string  $identificadorLinea  Código o descripción de la línea, para el mensaje de error.
     * @param  string  $codigoTributo  Código DIAN del tributo, para el mensaje de error.
     *
     * @throws InvalidArgumentException Si no coincide (tolerancia de 1 centavo).
     */
    private function assertSubtotalTaxAmountMatches(float $esperado, float $calculado, string $identificadorLinea, string $codigoTributo): void
    {
        if (abs($esperado - $calculado) > 0.01) {
            throw new InvalidArgumentException(
                "Lines.TaxTotal.TaxSubtotal.TaxAmount del tributo \"{$codigoTributo}\" en la línea \"{$identificadorLinea}\" ({$esperado}) no coincide con el valor calculado por Billingo ({$calculado})."
            );
        }
    }

    /**
     * Agrupa los impuestos de todas las líneas por código de tributo para armar los
     * cac:TaxTotal a nivel de documento -- el anexo técnico (regla FAS01a/FAS01b) exige que
     * solo exista un "cac:TaxTotal" por cada tributo; si hay varias tarifas del mismo tributo
     * (ej. IVA al 19% y al 5%) van como varios "cac:TaxSubtotal" dentro de ese único TaxTotal,
     * nunca en TaxTotal separados (la DIAN rechaza el documento si detecta más de un grupo para
     * el mismo tributo).
     *
     * @param  array  $lineas  Líneas ya calculadas (ver buildLineasCalculadas()).
     * @return array Impuestos agrupados por código, cada uno con su lista de "subtotals" (una
     *               entrada por tarifa distinta de ese tributo).
     */
    public function agruparImpuestos(array $lineas): array
    {
        $grupos = [];
        foreach ($lineas as $linea) {
            foreach ($linea['impuestos'] as $impuesto) {
                $codigo = $impuesto['codigo'];
                $grupos[$codigo] ??= [
                    'codigo' => $codigo,
                    'nombre' => $impuesto['nombre'],
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'subtotals' => [],
                ];
                $grupos[$codigo]['taxable_amount'] = round($grupos[$codigo]['taxable_amount'] + $impuesto['base_gravable'], 2);
                $grupos[$codigo]['tax_amount'] = round($grupos[$codigo]['tax_amount'] + $impuesto['tax_amount'], 2);

                $claveSubtotal = $impuesto['porcentaje'] . '|' . ($impuesto['per_unit_amount'] ?? '') . '|' . ($impuesto['base_unit_measure'] ?? '');
                if (! isset($grupos[$codigo]['subtotals'][$claveSubtotal])) {
                    $grupos[$codigo]['subtotals'][$claveSubtotal] = [
                        'porcentaje' => $impuesto['porcentaje'],
                        'taxable_amount' => 0.0,
                        'tax_amount' => 0.0,
                        'per_unit_amount' => $impuesto['per_unit_amount'] ?? null,
                        'base_unit_measure' => $impuesto['base_unit_measure'] ?? null,
                        'base_unit_measure_unit_code' => $impuesto['base_unit_measure_unit_code'] ?? null,
                    ];
                }
                $grupos[$codigo]['subtotals'][$claveSubtotal]['taxable_amount'] = round($grupos[$codigo]['subtotals'][$claveSubtotal]['taxable_amount'] + $impuesto['base_gravable'], 2);
                $grupos[$codigo]['subtotals'][$claveSubtotal]['tax_amount'] = round($grupos[$codigo]['subtotals'][$claveSubtotal]['tax_amount'] + $impuesto['tax_amount'], 2);
            }
        }

        foreach ($grupos as &$grupo) {
            $grupo['subtotals'] = array_values($grupo['subtotals']);
        }
        unset($grupo);

        return array_values($grupos);
    }

    /**
     * Traduce los cargos/descuentos a nivel documento (opcionales, cac:AllowanceCharge) al
     * shape que espera UblDocumentBuilder -- no calcula nada, "amount"/"base_amount" vienen
     * tal cual del caller (ver DocumentJsonMapper::mapCargosDescuentos(), que ya los exige) y
     * se mandan así a la DIAN.
     *
     * @param  array  $cargosPayload  Bloque "cargos_descuentos" del payload (tipo, motivo, codigo_razon, porcentaje, amount, base_amount).
     * @return array Cargos/descuentos en el shape que espera UblDocumentBuilder.
     */
    public function buildCargosCalculados(array $cargosPayload): array
    {
        return array_map(function (array $cargo) {
            $esDescuento = ($cargo['tipo'] ?? 'descuento') !== 'cargo';

            return [
                'es_descuento' => $esDescuento,
                'motivo' => $cargo['motivo'] ?: ($esDescuento ? 'Descuento' : 'Cargo'),
                'codigo_razon' => $cargo['codigo_razon'],
                'porcentaje' => $cargo['porcentaje'],
                'base_amount' => $cargo['base_amount'],
                'amount' => $cargo['amount'],
            ];
        }, $cargosPayload);
    }

    /**
     * Calcula los totales del documento a partir de las líneas, los impuestos agrupados
     * y los cargos/descuentos a nivel documento.
     *
     * @param  float  $lineExtensionAmount  Subtotal de líneas.
     * @param  float  $taxExclusiveAmount  Base imponible del documento (ver primerTributoTaxableAmount()).
     * @param  array  $impuestos  Impuestos agrupados (ver agruparImpuestos()).
     * @param  array  $cargos  Cargos/descuentos ya calculados (ver buildCargosCalculados()).
     * @return array Totales del documento.
     */
    public function calcularTotales(float $lineExtensionAmount, float $taxExclusiveAmount, array $impuestos, array $cargos): array
    {
        $taxAmount = round(array_sum(array_column($impuestos, 'tax_amount')), 2);
        $allowanceTotalAmount = round(array_sum(array_column(array_filter($cargos, fn (array $c) => $c['es_descuento']), 'amount')), 2);
        $chargeTotalAmount = round(array_sum(array_column(array_filter($cargos, fn (array $c) => ! $c['es_descuento']), 'amount')), 2);

        // TaxInclusiveAmount = LineExtensionAmount + tributos a nivel de documento (anexo
        // técnico: no se deriva de TaxExclusiveAmount).
        $taxInclusiveAmount = round($lineExtensionAmount + $taxAmount, 2);

        // PayableAmount = TaxInclusiveAmount - descuentos + cargos a nivel de documento.
        $payableAmount = round($taxInclusiveAmount - $allowanceTotalAmount + $chargeTotalAmount, 2);

        return [
            'line_extension_amount' => $lineExtensionAmount,
            'tax_exclusive_amount' => $taxExclusiveAmount,
            'tax_inclusive_amount' => $taxInclusiveAmount,
            'payable_amount' => $payableAmount,
            'allowance_total_amount' => $allowanceTotalAmount,
            'charge_total_amount' => $chargeTotalAmount,
        ];
    }

    /**
     * Traduce un código de impuesto DIAN a su nombre.
     *
     * @param  string  $codigo  Código DIAN del impuesto (01, 03, 04).
     * @return string Nombre del impuesto.
     */
    public function nombreImpuesto(string $codigo): string
    {
        return match ($codigo) {
            '01' => 'IVA',
            '03' => 'ICA',
            '04' => 'INC',
            default => $codigo,
        };
    }
}
