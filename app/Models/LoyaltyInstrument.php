<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Cupón, tarjeta de regalo, multipase o membresía emitida -- las 4 mecánicas
 * "de instancia" comparten la misma forma (un código, un saldo/usos
 * restantes, una vigencia, un estado), a diferencia de puntos/sellos/
 * cashback/niveles que son solo saldos acumulados en LoyaltyCustomer. Se
 * modelan en una sola colección con "type" en vez de 4 tablas casi idénticas.
 */
class LoyaltyInstrument extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'loyalty_instruments';

    public const TYPE_COUPON = 'coupon';
    public const TYPE_GIFT_CARD = 'gift_card';
    public const TYPE_MULTIPASS = 'multipass';
    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPES = [self::TYPE_COUPON, self::TYPE_GIFT_CARD, self::TYPE_MULTIPASS, self::TYPE_MEMBERSHIP];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'loyalty_customer_id',
        'type',
        'code',
        'plan_name',
        'initial_value',
        'remaining_value',
        'discount_type',
        'discount_value',
        'status',
        'issued_at',
        'expires_at',
        'renews_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_value' => 'float',
            'remaining_value' => 'float',
            'discount_value' => 'float',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'renews_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(LoyaltyCustomer::class, 'loyalty_customer_id');
    }

    public function isRedeemable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->type === self::TYPE_MEMBERSHIP || (float) $this->remaining_value > 0);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_COUPON => __('Coupon'),
            self::TYPE_GIFT_CARD => __('Gift card'),
            self::TYPE_MULTIPASS => __('Multipass'),
            self::TYPE_MEMBERSHIP => __('Membership'),
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => __('Active'),
            self::STATUS_EXHAUSTED => __('Exhausted'),
            self::STATUS_CANCELLED => __('Cancelled'),
            default => $status,
        };
    }

    /**
     * Código corto legible (8 caracteres, sin ambigüedad visual -- sin 0/O
     * ni 1/I) para que un cajero lo pueda transcribir a mano si el cliente
     * lo lee en voz alta, único por empresa.
     */
    public static function generateCode(Company $company): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('company_id', (string) $company->_id)->where('code', $code)->exists());

        return $code;
    }
}
