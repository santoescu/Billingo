<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class FiscalResponsibility extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'responsabilidades_fiscales';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
