<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Catálogo oficial de la DIAN de códigos de medio de pago (cbc:PaymentMeansCode).
 * Colección de solo lectura, ya poblada con los nombres en español ("medio"),
 * no necesita traducción.
 */
class PaymentMeansCode extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'payment_means_code';
    public $timestamps = false;
}
