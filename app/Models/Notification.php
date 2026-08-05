<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Notification extends Eloquent
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'title', 'body', 'url', 'sender_id', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
