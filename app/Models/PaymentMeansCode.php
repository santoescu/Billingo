<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PaymentMeansCode extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'payment_means_code';
    public $timestamps = false;
}
