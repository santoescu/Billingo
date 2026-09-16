<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Prospecto para campañas de correo en frío de Billingo mismo (no de sus clientes) -- ver
 * AdminLeadController. Se importa a mano (CSV/Excel) desde una lista conseguida por fuera (ej. un
 * export de cámara de comercio); la plataforma no descubre prospectos sola.
 */
class Lead extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'leads';

    protected $fillable = [
        'nit',
        'razon_social',
        'contact_name',
        'email',
        'city',
        'neighborhood',
        'address',
        'sector',
        'size',
        'registered_at',
        'pitch_note',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
        ];
    }

    public function emailLogs()
    {
        return $this->hasMany(LeadEmailLog::class);
    }
}
