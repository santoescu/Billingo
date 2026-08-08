<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DocumentoEmitido extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'documentos_emitidos';

    public const STATUS_PENDING = 0;
    public const STATUS_REJECTED = 1;
    public const STATUS_ACCEPTED = 2;
    public const STATUS_ERROR = 3;

    protected $fillable = [
        'company_id',
        'tipo_documento',
        'resolution_id',
        'prefix',
        'numeral',
        'secuencial',
        'cliente_id',
        'payload',
        'xml',
        'uuid',
        'status',
        'status_message',
        'response',
        'ambiente',
        'issue_date',
        'fecha_expedicion',
        'due_date',
        'subtotal',
        'tax_total',
        'total',
        'currency',
        'payment_means_id',
        'payment_means_code',
        'referencias',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'referencias' => 'array',
            'status_message' => 'array',
            'issue_date' => 'datetime',
            'fecha_expedicion' => 'datetime',
            'due_date' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cliente()
    {
        return $this->belongsTo(ThirdParty::class, 'cliente_id');
    }

    public function resolution()
    {
        return $this->belongsTo(Resolution::class);
    }

    public function scopeStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAmbiente($query, string $ambiente)
    {
        return $query->where('ambiente', $ambiente);
    }

    public function getTotalFormattedAttribute()
    {
        return '$' . number_format((float) $this->total, 2, '.', ',');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACCEPTED => __('Accepted'),
            self::STATUS_REJECTED => __('Rejected'),
            self::STATUS_ERROR => __('Error'),
            default => __('Pending'),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACCEPTED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::STATUS_REJECTED, self::STATUS_ERROR => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }
}
