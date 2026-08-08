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

        $cargos = $this->buildCargosCalculados($cargosPayload, $lineExtensionAmount);
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

            $descuentoAmount = 0.0;
            $descuentoPorcentaje = null;
            $descuentoMotivo = null;
            if (! empty($linea['descuento'])) {
                $esPorcentaje = ($linea['descuento']['valor_tipo'] ?? 'porcentaje') === 'porcentaje';
                $descuentoMotivo = $linea['descuento']['motivo'] ?? 'Descuento';
                
                $descuentoPorcentaje = $esPorcentaje ? min((float) $linea['descuento']['valor'], 100) : null;
                $descuentoAmount = $esPorcentaje
                    ? round($baseAmount * ($descuentoPorcentaje / 100), 2)
                    : round(min((float) $linea['descuento']['valor'], $baseAmount), 2);
            }

            $lineExtensionAmount = round($baseAmount - $descuentoAmount, 2);

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
                'descuento_amount' => $descuentoAmount,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_motivo' => $descuentoMotivo,
                'line_extension_amount' => $lineExtensionAmount,
                'impuestos' => $impuestosLinea,
            ];
        }, $lineasPayload);
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
     * Calcula los cargos/descuentos a nivel documento (opcionales, cac:AllowanceCharge),
     * resolviendo el monto de cada uno (fijo, o porcentaje sobre el subtotal de líneas).
     *
     * @param  array  $cargosPayload  Bloque "cargos_descuentos" del payload (tipo, motivo, valor_tipo, valor).
     * @param  float  $baseAmount  Subtotal de líneas (LineExtensionAmount), base para los que son porcentaje.
     * @return array Cargos/descuentos con el monto ya calculado.
     */
    public function buildCargosCalculados(array $cargosPayload, float $baseAmount): array
    {
        return array_map(function (array $cargo) use ($baseAmount) {
            $esDescuento = ($cargo['tipo'] ?? 'descuento') !== 'cargo';
            $esPorcentaje = ($cargo['valor_tipo'] ?? 'fijo') === 'porcentaje';
            $porcentaje = $esPorcentaje ? (float) $cargo['valor'] : null;
            $amount = $esPorcentaje
                ? round($baseAmount * ($porcentaje / 100), 2)
                : round((float) $cargo['valor'], 2);

            return [
                'es_descuento' => $esDescuento,
                'motivo' => $cargo['motivo'] ?: ($esDescuento ? 'Descuento' : 'Cargo'),
                'porcentaje' => $porcentaje,
                'base_amount' => $baseAmount,
                'amount' => $amount,
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

        $taxExclusiveAmount = round($lineExtensionAmount - $allowanceTotalAmount + $chargeTotalAmount, 2);
        $taxInclusiveAmount = round($taxExclusiveAmount + $taxAmount, 2);

        return [
            'line_extension_amount' => $lineExtensionAmount,
            'tax_exclusive_amount' => $taxExclusiveAmount,
            'tax_inclusive_amount' => $taxInclusiveAmount,
            'payable_amount' => $taxInclusiveAmount,
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
