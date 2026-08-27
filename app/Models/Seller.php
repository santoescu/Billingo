<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Seller extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'sellers';

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
