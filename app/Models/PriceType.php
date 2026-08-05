<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PriceType extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'price_types';

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
