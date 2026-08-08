<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CashShift extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cash_shifts';

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'user_id',
        'opening_balance',
        'opened_at',
        'closing_balance',
        'expected_balance',
        'variance',
        'closed_at',
        'status',
        'notes',
        
        'fv_resolution_id',
        'invoicing_resolution_id',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'float',
            'closing_balance' => 'float',
            'expected_balance' => 'float',
            'variance' => 'float',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    public function fvResolution()
    {
        return $this->belongsTo(Resolution::class, 'fv_resolution_id');
    }

    public function invoicingResolution()
    {
        return $this->belongsTo(Resolution::class, 'invoicing_resolution_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
