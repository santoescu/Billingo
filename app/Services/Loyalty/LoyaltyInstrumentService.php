<?php

namespace App\Services\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyInstrument;
use RuntimeException;

/**
 * Emite y cancela cupones, tarjetas de regalo, multipases y membresías --
 * a diferencia de puntos/sellos/cashback (que se acumulan solos con cada
 * venta), estas 4 mecánicas siempre se emiten a mano desde el módulo (una
 * decisión puntual del negocio: "le doy este cupón a este cliente"), nunca
 * automáticamente.
 *
 * @param array{plan_name?: string, initial_value?: float, discount_type?: string, discount_value?: float, expires_after_days?: int} $data
 */
class LoyaltyInstrumentService
{
    public function issue(Company $company, string $type, array $data, ?LoyaltyCustomer $customer = null): LoyaltyInstrument
    {
        if (! in_array($type, LoyaltyInstrument::TYPES, true)) {
            throw new RuntimeException(__('Invalid instrument type.'));
        }

        $expiresAt = isset($data['expires_after_days']) && $data['expires_after_days'] > 0
            ? now()->addDays((int) $data['expires_after_days'])
            : null;

        $initialValue = match ($type) {
            LoyaltyInstrument::TYPE_COUPON => 1,
            default => (float) ($data['initial_value'] ?? 0),
        };

        return LoyaltyInstrument::create([
            'company_id' => (string) $company->_id,
            'loyalty_customer_id' => $customer ? (string) $customer->_id : null,
            'type' => $type,
            'code' => LoyaltyInstrument::generateCode($company),
            'plan_name' => $data['plan_name'] ?? null,
            'initial_value' => $initialValue,
            'remaining_value' => $initialValue,
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'status' => LoyaltyInstrument::STATUS_ACTIVE,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
            'renews_at' => $type === LoyaltyInstrument::TYPE_MEMBERSHIP ? $expiresAt : null,
        ]);
    }

    public function cancel(LoyaltyInstrument $instrument): void
    {
        $instrument->update(['status' => LoyaltyInstrument::STATUS_CANCELLED]);
    }

    public function findByCode(Company $company, string $code): ?LoyaltyInstrument
    {
        return LoyaltyInstrument::where('company_id', (string) $company->_id)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }
}
