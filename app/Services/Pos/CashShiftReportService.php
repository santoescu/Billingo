<?php

namespace App\Services\Pos;

use App\Models\DocumentoPos;
use Illuminate\Support\Collection;

/**
 * Desgloses de un turno de caja a partir de sus ventas -- compartido entre
 * el modal de cierre (turno en curso) y el historial de turnos cerrados
 * (ver CashShiftController::show() y PosController::shift()), para no
 * calcular "cuánto entró por cada medio de pago" en dos lados distintos.
 */
class CashShiftReportService
{
    /**
     * Total cobrado por medio de pago -- suma "payments" (el desglose real
     * por medio de cada venta, ver DocumentoPos::$fillable), no
     * "payment_means_code" (que en una venta con varios medios trae todos
     * juntos en un solo array).
     *
     * @param  Collection<int, DocumentoPos>  $sales
     * @return array<int, array{name: string, amount: float}>
     */
    public function paymentBreakdownFor(Collection $sales): array
    {
        $totals = collect();

        foreach ($sales as $sale) {
            foreach ($sale->payments ?? [] as $payment) {
                $name = $payment['payment_method_name'] ?? __('Unknown');
                $totals[$name] = ($totals[$name] ?? 0) + (float) ($payment['amount'] ?? 0);
            }
        }

        return $totals->map(fn ($amount, $name) => ['name' => $name, 'amount' => round($amount, 2)])->values()->all();
    }

    /**
     * Cantidad y valor total vendido por producto.
     *
     * @param  Collection<int, DocumentoPos>  $sales
     * @return array<int, array{description: string, quantity: float, total: float}>
     */
    public function productsSoldFor(Collection $sales): array
    {
        $totals = [];

        foreach ($sales as $sale) {
            foreach ($sale->payload['lineas'] ?? [] as $linea) {
                $description = $linea['descripcion'] ?? __('Unknown');
                $quantity = (float) ($linea['cantidad'] ?? 0);
                $amount = $quantity * (float) ($linea['precio_unitario'] ?? 0);

                if (! isset($totals[$description])) {
                    $totals[$description] = ['quantity' => 0, 'total' => 0];
                }
                $totals[$description]['quantity'] += $quantity;
                $totals[$description]['total'] += $amount;
            }
        }

        return collect($totals)->map(fn ($data, $description) => [
            'description' => $description,
            'quantity' => $data['quantity'],
            'total' => round($data['total'], 2),
        ])->values()->all();
    }

    public function salesFor(string $shiftId): Collection
    {
        return DocumentoPos::where('shift_id', $shiftId)->get();
    }
}
