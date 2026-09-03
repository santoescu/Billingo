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

    /**
     * Línea de texto para mostrar el cambio dentro del hilo del chat (a
     * diferencia del historial del admin, que además muestra los badges
     * de antes/después) -- la usan tanto support/show.blade.php como
     * admin/tickets/show.blade.php a través de support/partials/messages.
     *
     * @param  \Illuminate\Support\Collection  $userNames  Usuarios ya cargados (User), indexados por su _id en string -- para resolver nombres sin hacer una consulta por actividad.
     */
    public function describe($userNames): string
    {
        $actorName = $userNames->get((string) $this->user_id)?->name ?? __('Someone');

        if ($this->action === self::ACTION_STATUS) {
            return __(':actor changed the status:', ['actor' => $actorName]);
        }

        $toName = $this->to ? ($userNames->get((string) $this->to)?->name ?? __('Someone')) : null;
        $fromName = $this->from ? ($userNames->get((string) $this->from)?->name ?? __('Someone')) : null;

        return $toName
            ? __(':actor assigned the ticket to :to', ['actor' => $actorName, 'to' => $toName])
            : __(':actor removed the assignment (was :from)', ['actor' => $actorName, 'from' => $fromName ?? __('nobody')]);
    }
}
