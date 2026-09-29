<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CashMovement extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cash_movements';

    const TYPE_APERTURA = 'apertura';
    const TYPE_VENTA = 'venta';
    const TYPE_INGRESO = 'ingreso';
    const TYPE_RETIRO = 'retiro';
    const TYPE_CIERRE = 'cierre';

    const CASH_PAYMENT_MEANS_CODE = '10';

    protected $fillable = [
        'company_id',
        'shift_id',
        'type',
        'amount',
        'cash_amount',
        'reason',
        'document_id',
        'payment_means_code',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'cash_amount' => 'float',
        ];
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function document()
    {
        return $this->belongsTo(DocumentoPos::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Solo estos movimientos afectan el efectivo físico de la caja: la
     * porción en efectivo de una venta (una venta puede pagarse mitad
     * efectivo, mitad tarjeta -- "amount" sigue siendo el total completo de
     * la venta para las métricas de ingresos, "cash_amount" es solo lo que
     * de verdad entró en efectivo), y los ingresos/retiros manuales.
     */
    public function affectsCashBalance(): bool
    {
        if ($this->type === self::TYPE_VENTA) {
            return (float) $this->cash_amount > 0;
        }

        return in_array($this->type, [self::TYPE_INGRESO, self::TYPE_RETIRO], true);
    }

    /**
     * Signo del movimiento para el cálculo del saldo esperado: ventas en
     * efectivo e ingresos suman, retiros restan.
     */
    public function signedAmount(): float
    {
        if (! $this->affectsCashBalance()) {
            return 0.0;
        }

        if ($this->type === self::TYPE_VENTA) {
            return abs((float) $this->cash_amount);
        }

        return $this->type === self::TYPE_RETIRO ? -abs((float) $this->amount) : abs((float) $this->amount);
    }
}
