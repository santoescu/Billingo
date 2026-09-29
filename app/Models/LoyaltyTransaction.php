<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Bitácora de cada movimiento de fidelización (acumulación, canje o ajuste
 * manual) -- nunca se edita ni se borra, solo se agregan filas nuevas, para
 * que el saldo de un cliente siempre se pueda auditar/reconstruir.
 */
class LoyaltyTransaction extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'loyalty_transactions';

    public const DIRECTION_EARN = 'earn';
    public const DIRECTION_REDEEM = 'redeem';
    public const DIRECTION_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'company_id',
        'loyalty_customer_id',
        'mechanic',
        'direction',
        'amount',
        'balance_after',
        'source_type',
        'documento_pos_id',
        'documento_emitido_id',
        'loyalty_instrument_id',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(LoyaltyCustomer::class, 'loyalty_customer_id');
    }

    public function documentoPos()
    {
        return $this->belongsTo(DocumentoPos::class);
    }

    public function documentoEmitido()
    {
        return $this->belongsTo(DocumentoEmitido::class);
    }

    public function instrument()
    {
        return $this->belongsTo(LoyaltyInstrument::class, 'loyalty_instrument_id');
    }

    public static function directionLabel(string $direction): string
    {
        return match ($direction) {
            self::DIRECTION_EARN => __('Earn'),
            self::DIRECTION_REDEEM => __('Redeem'),
            self::DIRECTION_ADJUSTMENT => __('Adjustment'),
            default => $direction,
        };
    }

    public static function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'pos' => __('Pos'),
            'invoicing' => __('Invoicing'),
            'manual' => __('Manual'),
            'public' => __('Public'),
            default => $sourceType,
        };
    }
}
