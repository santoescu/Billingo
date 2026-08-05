<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Representa la pertenencia de un usuario a una empresa (rol global de la
 * empresa -owner- y/o roles por módulo). Reemplaza al pivot de una relación
 * belongsToMany porque el paquete mongodb/laravel-mongodb no persiste
 * atributos extra de pivot (attach()/updateExistingPivot() son no-ops).
 */
class CompanyMember extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'company_members';

    protected $fillable = [
        'company_id',
        'user_id',
        'role',
        'modules',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
