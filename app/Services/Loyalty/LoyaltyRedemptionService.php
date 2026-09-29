<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyInstrument;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use RuntimeException;

/**
 * Canjes y ajustes manuales -- el canje lo dispara un empleado desde el
 * módulo, o el propio flujo de venta del POS (ver LoyaltyPosRedemptionService,
 * que llama a estos mismos métodos con sourceType='pos' y el id de la venta),
 * nunca de forma automática por sí solo.
 */
class LoyaltyRedemptionService
{
    public function redeemPoints(LoyaltyProgram $program, LoyaltyCustomer $customer, float $points, ?string $userId = null, string $sourceType = 'manual', ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_POINTS)) {
            throw new RuntimeException(__('This program does not have the points mechanic active.'));
        }

        if ($points <= 0 || $points > (float) $customer->points_balance) {
            throw new RuntimeException(__('Not enough points.'));
        }

        $customer->points_balance = (float) $customer->points_balance - $points;
        $customer->save();

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_POINTS, LoyaltyTransaction::DIRECTION_REDEEM, $points, $customer->points_balance, $userId, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    /**
     * Canjea la tarjeta completa (todos los sellos requeridos a la vez) --
     * no un sello individual, ya que la recompensa se entrega cuando se
     * completa la tarjeta, no antes.
     */
    public function redeemStamps(LoyaltyProgram $program, LoyaltyCustomer $customer, ?string $userId = null, string $sourceType = 'manual', ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_STAMPS)) {
            throw new RuntimeException(__('This program does not have the stamps mechanic active.'));
        }

        $required = (int) ($program->settingsFor(LoyaltyProgram::MECHANIC_STAMPS)['stamps_required'] ?? 0);
        if ($required <= 0 || (int) $customer->stamps_count < $required) {
            throw new RuntimeException(__('The stamp card is not complete yet.'));
        }

        $customer->stamps_count = (int) $customer->stamps_count - $required;
        $customer->save();

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_STAMPS, LoyaltyTransaction::DIRECTION_REDEEM, $required, $customer->stamps_count, $userId, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    public function redeemCashback(LoyaltyProgram $program, LoyaltyCustomer $customer, float $amount, ?string $userId = null, string $sourceType = 'manual', ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        if (! $program->hasMechanic(LoyaltyProgram::MECHANIC_CASHBACK)) {
            throw new RuntimeException(__('This program does not have the cashback mechanic active.'));
        }

        if ($amount <= 0 || $amount > (float) $customer->cashback_balance) {
            throw new RuntimeException(__('Not enough cashback balance.'));
        }

        $customer->cashback_balance = (float) $customer->cashback_balance - $amount;
        $customer->save();

        $this->logTransaction($customer, LoyaltyProgram::MECHANIC_CASHBACK, LoyaltyTransaction::DIRECTION_REDEEM, $amount, $customer->cashback_balance, $userId, $sourceType, $documentoPosId, $documentoEmitidoId);
    }

    /**
     * Canjea un cupón/tarjeta de regalo/multipase por su código -- una
     * membresía no se "canjea" (no tiene saldo que gastar), solo se valida
     * que siga activa. $amount es cuánto se usa de una vez (una sesión del
     * multipase, o el monto que se descuenta de la tarjeta de regalo); en
     * un cupón siempre se consume entero de una vez.
     */
    public function redeemInstrument(LoyaltyInstrument $instrument, ?float $amount = null, ?string $userId = null, string $sourceType = 'manual', ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        if (! $instrument->isRedeemable()) {
            throw new RuntimeException(__('This code is not redeemable (expired, cancelled, or already used).'));
        }

        if ($instrument->type === LoyaltyInstrument::TYPE_MEMBERSHIP) {
            return;
        }

        if ($instrument->type === LoyaltyInstrument::TYPE_COUPON) {
            $instrument->remaining_value = 0;
        } else {
            $amount = $amount ?? 1.0;
            if ($amount <= 0 || $amount > (float) $instrument->remaining_value) {
                throw new RuntimeException(__('The requested amount exceeds the remaining balance.'));
            }
            $instrument->remaining_value = (float) $instrument->remaining_value - $amount;
        }

        if ((float) $instrument->remaining_value <= 0) {
            $instrument->status = LoyaltyInstrument::STATUS_EXHAUSTED;
        }

        $instrument->save();

        if ($instrument->loyalty_customer_id) {
            $customer = LoyaltyCustomer::find($instrument->loyalty_customer_id);
            if ($customer) {
                LoyaltyTransaction::create([
                    'company_id' => $customer->company_id,
                    'loyalty_customer_id' => (string) $customer->_id,
                    'mechanic' => $instrument->type,
                    'direction' => LoyaltyTransaction::DIRECTION_REDEEM,
                    'amount' => $amount ?? 1.0,
                    'balance_after' => (float) $instrument->remaining_value,
                    'source_type' => $sourceType,
                    'documento_pos_id' => $documentoPosId,
                    'documento_emitido_id' => $documentoEmitidoId,
                    'loyalty_instrument_id' => (string) $instrument->_id,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    /**
     * Ajuste manual (suma o resta) sobre un saldo -- a diferencia de un
     * canje, no valida que haya "suficiente" (un ajuste puede corregir un
     * error hacia cualquier lado), pero exige una nota: sin motivo escrito
     * no queda claro después por qué cambió el saldo.
     */
    public function manualAdjust(LoyaltyCustomer $customer, string $mechanic, float $signedAmount, string $note, ?string $userId = null): void
    {
        if (trim($note) === '') {
            throw new RuntimeException(__('You must explain the reason for the adjustment.'));
        }

        $field = match ($mechanic) {
            LoyaltyProgram::MECHANIC_POINTS => 'points_balance',
            LoyaltyProgram::MECHANIC_STAMPS => 'stamps_count',
            LoyaltyProgram::MECHANIC_CASHBACK => 'cashback_balance',
            default => throw new RuntimeException(__('This mechanic cannot be adjusted manually.')),
        };

        $customer->{$field} = ($customer->{$field} ?? 0) + $signedAmount;
        $customer->save();

        LoyaltyTransaction::create([
            'company_id' => $customer->company_id,
            'loyalty_customer_id' => (string) $customer->_id,
            'mechanic' => $mechanic,
            'direction' => LoyaltyTransaction::DIRECTION_ADJUSTMENT,
            'amount' => $signedAmount,
            'balance_after' => (float) $customer->{$field},
            'source_type' => 'manual',
            'note' => $note,
            'user_id' => $userId,
        ]);
    }

    private function logTransaction(LoyaltyCustomer $customer, string $mechanic, string $direction, float $amount, float $balanceAfter, ?string $userId, string $sourceType = 'manual', ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
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
            'user_id' => $userId,
        ]);
    }
}
