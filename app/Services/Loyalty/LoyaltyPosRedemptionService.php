<?php

namespace App\Services\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyInstrument;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Traduce lo que el cajero eligió canjear en el POS (puntos, sellos,
 * cashback, cupón/tarjeta de regalo por código) a un descuento en dinero --
 * separado en dos pasos porque el descuento debe quedar fijo ANTES de crear
 * la venta (para que el total y los medios de pago cuadren), pero el saldo
 * real solo se descuenta DESPUÉS de que la venta ya quedó guardada (mismo
 * criterio que el descuento de inventario en IssueDocumentService: se acepta
 * el mismo riesgo pequeño de que el paso de después falle, se registra en el
 * log, pero la venta ya no se puede tumbar por eso).
 */
class LoyaltyPosRedemptionService
{
    public function resolveCustomer(Company $company, string $identificacion): ?LoyaltyCustomer
    {
        $cliente = $company->clients()->where('identificacion', $identificacion)->first();

        return $cliente ? LoyaltyCustomer::findByClienteId($company, (string) $cliente->_id) : null;
    }

    /**
     * Calcula (sin escribir nada) el descuento total y el detalle línea por
     * línea de lo solicitado -- lanza RuntimeException con un mensaje ya
     * traducido si algo no es válido (saldo insuficiente, código no existe,
     * mecánica sin tasa de conversión configurada, etc.).
     *
     * @param  array{points_amount?: float, redeem_stamps?: bool, cashback_amount?: float, codes?: string[]}  $request
     * @return array{discounts: array<int, array{motivo: string, amount: float}>, total: float, plan: array}
     */
    public function preview(Company $company, LoyaltyProgram $program, ?LoyaltyCustomer $customer, array $request, float $saleSubtotal): array
    {
        $discounts = [];
        $plan = [];
        $runningTotal = 0.0;

        $pointsAmount = (float) ($request['points_amount'] ?? 0);
        if ($pointsAmount > 0) {
            if (! $customer) {
                throw new RuntimeException(__('This client is not enrolled in the loyalty program.'));
            }
            if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_POINTS)) {
                throw new RuntimeException(__('This program does not have the points mechanic active.'));
            }
            if ($pointsAmount > (float) $customer->points_balance) {
                throw new RuntimeException(__('Not enough points.'));
            }
            $value = $program->pointsRedeemValue($pointsAmount);
            if ($value <= 0) {
                throw new RuntimeException(__('This program has no redeem value configured for points.'));
            }
            $discounts[] = ['motivo' => __('Loyalty: :points points', ['points' => $pointsAmount]), 'amount' => $value];
            $plan[] = ['mechanic' => LoyaltyProgram::MECHANIC_POINTS, 'amount' => $pointsAmount];
            $runningTotal += $value;
        }

        if (! empty($request['redeem_stamps'])) {
            if (! $customer) {
                throw new RuntimeException(__('This client is not enrolled in the loyalty program.'));
            }
            if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_STAMPS)) {
                throw new RuntimeException(__('This program does not have the stamps mechanic active.'));
            }
            $required = (int) ($program->settingsFor('stamps')['stamps_required'] ?? 0);
            if ($required <= 0 || (int) $customer->stamps_count < $required) {
                throw new RuntimeException(__('The stamp card is not complete yet.'));
            }
            $value = $program->stampsRewardValue();
            if ($value <= 0) {
                throw new RuntimeException(__('This program has no reward value configured for stamps.'));
            }
            $discounts[] = ['motivo' => __('Loyalty: completed stamp card'), 'amount' => $value];
            $plan[] = ['mechanic' => LoyaltyProgram::MECHANIC_STAMPS, 'amount' => $required];
            $runningTotal += $value;
        }

        $cashbackAmount = (float) ($request['cashback_amount'] ?? 0);
        if ($cashbackAmount > 0) {
            if (! $customer) {
                throw new RuntimeException(__('This client is not enrolled in the loyalty program.'));
            }
            if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_CASHBACK)) {
                throw new RuntimeException(__('This program does not have the cashback mechanic active.'));
            }
            if ($cashbackAmount > (float) $customer->cashback_balance) {
                throw new RuntimeException(__('Not enough cashback balance.'));
            }
            $discounts[] = ['motivo' => __('Loyalty: cashback'), 'amount' => $cashbackAmount];
            $plan[] = ['mechanic' => LoyaltyProgram::MECHANIC_CASHBACK, 'amount' => $cashbackAmount];
            $runningTotal += $cashbackAmount;
        }

        foreach (array_filter($request['codes'] ?? []) as $code) {
            $instrument = LoyaltyInstrument::where('company_id', (string) $company->_id)
                ->where('code', strtoupper(trim($code)))
                ->first();

            if (! $instrument || ! $instrument->isRedeemable()) {
                throw new RuntimeException(__(':code is not a valid or redeemable code.', ['code' => $code]));
            }

            if (! in_array($instrument->type, [LoyaltyInstrument::TYPE_COUPON, LoyaltyInstrument::TYPE_GIFT_CARD], true)) {
                throw new RuntimeException(__(':code cannot be redeemed as a discount here.', ['code' => $code]));
            }

            $remainingSubtotal = max($saleSubtotal - $runningTotal, 0);

            if ($instrument->type === LoyaltyInstrument::TYPE_COUPON) {
                if (empty($instrument->discount_type) || (float) $instrument->discount_value <= 0) {
                    throw new RuntimeException(__(':code has no discount configured.', ['code' => $code]));
                }
                $value = $instrument->discount_type === 'percentage'
                    ? round($remainingSubtotal * ((float) $instrument->discount_value / 100), 2)
                    : (float) $instrument->discount_value;
                $value = min($value, $remainingSubtotal);
                $discounts[] = ['motivo' => __('Coupon :code', ['code' => $instrument->code]), 'amount' => $value];
                $plan[] = ['mechanic' => 'coupon', 'instrument_id' => (string) $instrument->_id, 'amount' => null];
            } else {
                $value = min((float) $instrument->remaining_value, $remainingSubtotal);
                $discounts[] = ['motivo' => __('Gift card :code', ['code' => $instrument->code]), 'amount' => $value];
                $plan[] = ['mechanic' => 'gift_card', 'instrument_id' => (string) $instrument->_id, 'amount' => $value];
            }

            $runningTotal += $value;
        }

        if ($runningTotal > $saleSubtotal + 0.01) {
            throw new RuntimeException(__('The total redeemed exceeds the sale amount.'));
        }

        return ['discounts' => $discounts, 'total' => round($runningTotal, 2), 'plan' => $plan];
    }

    /**
     * Ejecuta de verdad el descuento de saldo/instrumento -- se llama
     * después de que la venta ya quedó guardada, con el mismo $plan que
     * devolvió preview(). Cualquier error acá se registra pero NUNCA se deja
     * propagar: la venta ya se cobró con ese descuento, no se puede
     * revertir solo porque el saldo cambió mientras tanto.
     */
    public function apply(Company $company, ?LoyaltyCustomer $customer, array $plan, ?string $userId, string $sourceType, ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        $program = $company->loyaltyProgram;
        if (! $program) {
            return;
        }

        $redemptionService = app(LoyaltyRedemptionService::class);

        foreach ($plan as $entry) {
            try {
                match ($entry['mechanic']) {
                    LoyaltyProgram::MECHANIC_POINTS => $redemptionService->redeemPoints($program, $customer, (float) $entry['amount'], $userId, $sourceType, $documentoPosId, $documentoEmitidoId),
                    LoyaltyProgram::MECHANIC_STAMPS => $redemptionService->redeemStamps($program, $customer, $userId, $sourceType, $documentoPosId, $documentoEmitidoId),
                    LoyaltyProgram::MECHANIC_CASHBACK => $redemptionService->redeemCashback($program, $customer, (float) $entry['amount'], $userId, $sourceType, $documentoPosId, $documentoEmitidoId),
                    'coupon', 'gift_card' => $redemptionService->redeemInstrument(
                        LoyaltyInstrument::find($entry['instrument_id']),
                        $entry['amount'] ?? null,
                        $userId,
                        $sourceType,
                        $documentoPosId,
                        $documentoEmitidoId,
                    ),
                    default => null,
                };
            } catch (Throwable $e) {
                Log::warning('Fidelización: no se pudo aplicar un canje de la venta (el descuento ya se cobró).', [
                    'company_id' => (string) $company->_id,
                    'documento_pos_id' => $documentoPosId,
                    'documento_emitido_id' => $documentoEmitidoId,
                    'entry' => $entry,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
