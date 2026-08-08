<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

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
