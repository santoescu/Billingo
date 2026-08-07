<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CashMovement extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cash_movements';

    // 'apertura'/'cierre' registran el saldo inicial/contado del turno;
    // 'venta' se crea por cada documento emitido durante el turno (solo las
    // ventas en efectivo suman al saldo esperado, ver CashShift); 'ingreso'
    // y 'retiro' son movimientos manuales de efectivo (p. ej. un retiro
    // parcial a mitad de turno).
    const TYPE_APERTURA = 'apertura';
    const TYPE_VENTA = 'venta';
    const TYPE_INGRESO = 'ingreso';
    const TYPE_RETIRO = 'retiro';
    const TYPE_CIERRE = 'cierre';

    // Código DIAN de "Efectivo" en PaymentMeansCode -- el único medio de
    // pago que efectivamente mueve el dinero físico de la caja.
    const CASH_PAYMENT_MEANS_CODE = '10';

    protected $fillable = [
        'company_id',
        'shift_id',
        'type',
        'amount',
        'reason',
        'document_id',
        'payment_means_code',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
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
     * Solo estos movimientos afectan el efectivo físico de la caja: las
     * ventas en efectivo, y los ingresos/retiros manuales. Ventas con otro
     * medio de pago (tarjeta, transferencia) quedan registradas para el
     * historial del turno, pero no suman ni restan del saldo esperado.
     */
    public function affectsCashBalance(): bool
    {
        if ($this->type === self::TYPE_VENTA) {
            return $this->payment_means_code === self::CASH_PAYMENT_MEANS_CODE;
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

        return $this->type === self::TYPE_RETIRO ? -abs((float) $this->amount) : abs((float) $this->amount);
    }
}
