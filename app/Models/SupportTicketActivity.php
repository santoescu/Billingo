<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SupportTicketActivity extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'support_ticket_activities';

    const ACTION_STATUS = 'status';
    const ACTION_ASSIGNED = 'assigned';
    const ACTION_PRIORITY = 'priority';

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'action',
        'from',
        'to',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
