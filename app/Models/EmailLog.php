<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EmailLog extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'email_logs';

    protected $fillable = [
        'company_id',
        'documento_id',
        'to',
        'subject',
        'ses_message_id',
        'sent_at',
        'delivered_at',
        'opened_at',
        'bounced_at',
        'bounce_reason',
        'complained_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documento()
    {
        return $this->belongsTo(DocumentoEmitido::class, 'documento_id');
    }

    /**
     * El estado "más avanzado" que alcanzó este envío, para pintar un solo badge en vez de una
     * fila por cada evento -- el orden importa: si ya hubo un rebote o una queja de spam, eso
     * pesa más que un simple "entregado" aunque también haya llegado ese evento antes.
     */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->complained_at => __('Spam'),
            (bool) $this->bounced_at => __('Bounced'),
            (bool) $this->opened_at => __('Opened'),
            (bool) $this->delivered_at => __('Delivered'),
            default => __('Sent'),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match (true) {
            (bool) $this->complained_at, (bool) $this->bounced_at => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            (bool) $this->opened_at => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            (bool) $this->delivered_at => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }
}
