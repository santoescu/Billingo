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

        if (empty($impuestos) && $lineExtensionAmount > 0) {
            $impuestos = [[
                'codigo' => '01',
                'nombre' => $this->nombreImpuesto('01'),
                'porcentaje' => 0.0,
                'taxable_amount' => $lineExtensionAmount,
                'tax_amount' => 0.0,
            ]];
        }

        $cargos = $this->buildCargosCalculados($cargosPayload);
        $totales = $this->calcularTotales($lineExtensionAmount, $impuestos, $cargos);

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
            $baseAmount = round($cantidad * $precioUnitario, 2);

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
                
                $baseGravable = min((float) ($impuesto['base_gravable'] ?? $lineExtensionAmount), $lineExtensionAmount);
                $impuestosLinea[] = [
                    'codigo' => $impuesto['tipo'],
                    'nombre' => $impuesto['nombre'] ?? $this->nombreImpuesto($impuesto['tipo']),
                    'porcentaje' => $porcentaje,
                    'base_gravable' => $baseGravable,
                    'tax_amount' => round($baseGravable * ($porcentaje / 100), 2),
                ];
            }

            if (empty($impuestosLinea) && $lineExtensionAmount > 0) {
                $impuestosLinea[] = [
                    'codigo' => '01',
                    'nombre' => $this->nombreImpuesto('01'),
                    'porcentaje' => 0.0,
                    'base_gravable' => $lineExtensionAmount,
                    'tax_amount' => 0.0,
                ];
            }

            return [
                'codigo' => $linea['codigo'] ?? null,
                'codigo_barras' => $linea['codigo_barras'] ?? null,
                'descripcion' => $linea['descripcion'] ?? '',
                'cantidad' => $cantidad,
                'unidad_medida' => $linea['unidad_medida'] ?? 'EA',
                'precio_unitario' => $precioUnitario,
                'base_amount' => $baseAmount,
                'cargos_descuentos' => $cargosDescuentos,
                'line_extension_amount' => $lineExtensionAmount,
                'impuestos' => $impuestosLinea,
            ];
        }, $lineasPayload);
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
     * Agrupa los impuestos de todas las líneas por código (01=IVA, 03=ICA, 04=INC)
     * para armar los cac:TaxTotal a nivel de documento.
     *
     * @param  array  $lineas  Líneas ya calculadas (ver buildLineasCalculadas()).
     * @return array Impuestos agrupados por código.
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
                    'porcentaje' => $impuesto['porcentaje'],
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                ];
                $grupos[$codigo]['taxable_amount'] = round($grupos[$codigo]['taxable_amount'] + $impuesto['base_gravable'], 2);
                $grupos[$codigo]['tax_amount'] = round($grupos[$codigo]['tax_amount'] + $impuesto['tax_amount'], 2);
            }
        }

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
     * @param  array  $impuestos  Impuestos agrupados (ver agruparImpuestos()).
     * @param  array  $cargos  Cargos/descuentos ya calculados (ver buildCargosCalculados()).
     * @return array Totales del documento.
     */
    public function calcularTotales(float $lineExtensionAmount, array $impuestos, array $cargos): array
    {
        $taxAmount = round(array_sum(array_column($impuestos, 'tax_amount')), 2);
        $allowanceTotalAmount = round(array_sum(array_column(array_filter($cargos, fn (array $c) => $c['es_descuento']), 'amount')), 2);
        $chargeTotalAmount = round(array_sum(array_column(array_filter($cargos, fn (array $c) => ! $c['es_descuento']), 'amount')), 2);

        // TaxExclusiveAmount = suma de las bases gravables de las líneas (anexo técnico, regla
        // CAU04/FAJ..: no resta descuentos ni suma cargos a nivel de documento -- esos no
        // afectan bases gravables, solo el PayableAmount final).
        $taxExclusiveAmount = round(array_sum(array_column($impuestos, 'taxable_amount')), 2);

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
