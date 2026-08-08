<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

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
