<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Mismo shape/propósito que EmailLog, pero para el correo en frío a prospectos (ver Lead,
 * AdminLeadController) -- separado a propósito de EmailLog (que trackea el envío de documentos
 * a clientes reales) para no mezclar una cosa de bajo riesgo/volumen (prospección) con el
 * tracking legal de entrega de facturas.
 */
class LeadEmailLog extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'lead_email_logs';

    protected $fillable = [
        'lead_id',
        'to',
        'subject',
        'variant',
        'ses_message_id',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'clicked_link',
        'bounced_at',
        'bounce_reason',
        'complained_at',
        'complaint_reason',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->complained_at => __('Spam'),
            (bool) $this->bounced_at => __('Bounced'),
            (bool) $this->clicked_at => __('Clicked'),
            (bool) $this->opened_at => __('Opened'),
            (bool) $this->delivered_at => __('Delivered'),
            default => __('Sent'),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match (true) {
            (bool) $this->complained_at, (bool) $this->bounced_at => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            (bool) $this->clicked_at => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            (bool) $this->opened_at => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            (bool) $this->delivered_at => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }
}
