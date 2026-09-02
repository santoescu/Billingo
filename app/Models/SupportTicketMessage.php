<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'support_ticket_messages';

    const AUTHOR_COMPANY = 'company';
    const AUTHOR_STAFF = 'staff';

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'author_role',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsStaffAttribute(): bool
    {
        return $this->author_role === self::AUTHOR_STAFF;
    }
}
