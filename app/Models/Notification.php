<?php

namespace App\Models;

use Illuminate\Support\Facades\Mail;
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

    /**
     * Igual que notifyUsers(), pero además le manda un correo SINCRÓNICO (no en cola -- el
     * Mailable no implementa ShouldQueue, ver SupportTicketNotificationMail) a cada usuario que
     * tenga email -- para avisos puntuales que sí vale la pena que lleguen de inmediato aunque
     * la persona no tenga la app abierta (ticket nuevo, asignación, cambio de estado), a
     * diferencia de la campanita sola que usa notifyUsers() para todo lo demás (ej. cada mensaje
     * nuevo dentro de un ticket ya abierto -- eso sería demasiado correo).
     *
     * @param  array<int, string>  $userIds
     * @param  \Closure(): \Illuminate\Contracts\Mail\Mailable  $mailFactory  Una instancia nueva
     *         del Mailable por cada destinatario (no una sola compartida entre todos).
     */
    public static function notifyUsersWithEmail(array $userIds, string $title, string $body, ?string $url, \Closure $mailFactory): void
    {
        self::notifyUsers($userIds, $title, $body, $url);

        $users = User::whereIn('_id', array_unique($userIds))->whereNotNull('email')->get();

        foreach ($users as $user) {
            Mail::to($user->email)->send($mailFactory());
        }
    }
}
