<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Department extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'departamentos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'municipios',
    ];

    protected function casts(): array
    {
        return [
            'municipios' => 'array',
        ];
    }
}
