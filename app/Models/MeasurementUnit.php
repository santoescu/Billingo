<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MeasurementUnit extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'measurement_units';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
