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

    /**
     * Crea la misma notificación para varios usuarios de una sola vez
     * (avisos de sistema, sin 'sender_id' -- no viene de un usuario puntual).
     *
     * @param  array<int, string>  $userIds
     */
    public static function notifyUsers(array $userIds, string $title, string $body, ?string $url = null): void
    {
        foreach (array_unique($userIds) as $userId) {
            self::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);
        }
    }
}
