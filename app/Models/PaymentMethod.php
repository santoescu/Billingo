<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Medio de pago propio de la empresa (p. ej. "Sistecrédito", "Nequi"), usado
 * por el POS en vez del catálogo crudo de la DIAN. "dian_payment_means_code"
 * es el código DIAN equivalente (cbc:PaymentMeansCode, ver PaymentMeansCode)
 * -- opcional: solo hace falta si la venta se va a emitir como factura
 * electrónica (ver DocumentoEmitidoController::issuePosSale()).
 */
class PaymentMethod extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'payment_methods';

    protected $fillable = [
        'company_id',
        'name',
        'dian_payment_means_code',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
