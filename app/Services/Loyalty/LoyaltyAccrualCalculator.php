<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;

/**
 * Calcula y aplica lo que un cliente gana por una compra, para cada
 * mecánica de acumulación que el programa tenga activa (puntos, sellos,
 * cashback, niveles) -- una compra puede acumular en varias mecánicas a la
 * vez si el programa las tiene todas activas.
 */
class LoyaltyAccrualCalculator
{
    public function apply(LoyaltyProgram $program, LoyaltyCustomer $customer, float $total, string $sourceType, ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        $customer->lifetime_spend = (float) $customer->lifetime_spend + $total;

        if ($program->hasMechanic(LoyaltyProgram::MECHANIC_POINTS)) {
            $this->accruePoints($program, $customer, $total, $sourceType, $documentoPosId, $documentoEmitidoId);
        }

        if ($program->hasMechanic(LoyaltyProgram::MECHANIC_STAMPS)) {
            $this->accrueStamps($program, $customer, $sourceType, $documentoPosId, $documentoEmitidoId);
        }

        if ($program->hasMechanic(LoyaltyProgram::MECHANIC_CASHBACK)) {
            $this->accrueCashback($program, $customer, $total, $sourceType, $documentoPosId, $documentoEmitidoId);
        }

        if ($program->hasMechanic(LoyaltyProgram::MECHANIC_TIERS)) {
            $this->recalculateTier($program, $customer);
        }

        $customer->save();
    }

    private function accruePoints(LoyaltyProgram $program, LoyaltyCustomer $customer, float $total, string $sourceType, ?string $documentoPosId, ?string $documentoEmitidoId): void
    {
        $settings = $program->settingsFor(LoyaltyProgram::MECHANIC_POINTS);
        $currencyUnit = (float) ($settings['currency_unit'] ?? 1000);
        $earnRate = (float) ($settings['earn_rate_per_currency'] ?? 1);

        if ($currencyUnit <= 0) {
            return;
        }

        $pointsEarned = floor($total / $currencyUnit) * $earnRate;
        if ($pointsEarned <= 0) {
            return;
        }

        $customer->points_balance = (float) $customer->points_balance + $pointsEarned;

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_POINTS, LoyaltyTransaction::DIRECTION_EARN, $pointsEarned, $customer->points_balance, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    /**
     * Un sello por compra, sin importar el monto -- así funciona la tarjeta
     * de sellos de toda la vida ("compra y te sellamos"), no una fracción
     * de sello por cada tanto gastado.
     */
    private function accrueStamps(LoyaltyProgram $program, LoyaltyCustomer $customer, string $sourceType, ?string $documentoPosId, ?string $documentoEmitidoId): void
    {
        $customer->stamps_count = (int) $customer->stamps_count + 1;

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_STAMPS, LoyaltyTransaction::DIRECTION_EARN, 1, $customer->stamps_count, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    private function accrueCashback(LoyaltyProgram $program, LoyaltyCustomer $customer, float $total, string $sourceType, ?string $documentoPosId, ?string $documentoEmitidoId): void
    {
        $percentage = (float) ($program->settingsFor(LoyaltyProgram::MECHANIC_CASHBACK)['percentage'] ?? 0);
        if ($percentage <= 0) {
            return;
        }

        $cashbackEarned = round($total * $percentage / 100, 2);
        if ($cashbackEarned <= 0) {
            return;
        }

        $customer->cashback_balance = (float) $customer->cashback_balance + $cashbackEarned;

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_CASHBACK, LoyaltyTransaction::DIRECTION_EARN, $cashbackEarned, $customer->cashback_balance, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    /**
     * El nivel se recalcula por gasto histórico total, no por la compra
     * puntual -- se toma el nivel de mayor "min_spend" que el cliente ya
     * alcanza, asumiendo que "levels" viene ordenado de menor a mayor.
     */
    private function recalculateTier(LoyaltyProgram $program, LoyaltyCustomer $customer): void
    {
        $levels = $program->settingsFor(LoyaltyProgram::MECHANIC_TIERS)['levels'] ?? [];
        if (empty($levels)) {
            return;
        }

        $currentLevel = null;
        foreach ($levels as $level) {
            if ((float) $customer->lifetime_spend >= (float) ($level['min_spend'] ?? 0)) {
                $currentLevel = $level;
            }
        }

        if ($currentLevel && $currentLevel['name'] !== $customer->current_tier_name) {
            $customer->current_tier_name = $currentLevel['name'];
        }
    }

    private function logTransaction(LoyaltyCustomer $customer, string $mechanic, string $direction, float $amount, float $balanceAfter, string $sourceType, ?string $documentoPosId, ?string $documentoEmitidoId): void
    {
        LoyaltyTransaction::create([
            'company_id' => $customer->company_id,
            'loyalty_customer_id' => (string) $customer->_id,
            'mechanic' => $mechanic,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'source_type' => $sourceType,
            'documento_pos_id' => $documentoPosId,
            'documento_emitido_id' => $documentoEmitidoId,
        ]);
    }
}
