<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class PaymentMethod extends Model
{
    use Auditable;

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
