<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class CompanyMember extends Model
{
    use Auditable;

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

    protected function auditLabel(): string
    {
        return User::find($this->user_id)?->name ?? (string) $this->user_id;
    }
}
