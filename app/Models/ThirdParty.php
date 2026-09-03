<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class ThirdParty extends Model
{
    use Auditable;

    protected $connection = 'mongodb';
    protected $table = 'third_parties';

    const DEFAULT_FISCAL_RESPONSIBILITY = 'R-99-PN';

    protected $fillable = [
        'company_id',
        'identification_type',
        'identificacion',
        'dv',
        'person_type',
        'name',
        'fiscal_responsibilities',
        'address',
        'city_code',
        'department_code',
        'phone',
        'email',
        'roles',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documentosEmitidos()
    {
        return $this->hasMany(DocumentoEmitido::class, 'cliente_id');
    }

    public function getFiscalResponsibilitiesAttribute($value)
    {
        return filled($value) ? $value : self::DEFAULT_FISCAL_RESPONSIBILITY;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'active')
                ->orWhereNull('status')
                ->orWhere('status', '');
        });
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('roles', $role);
    }
}
