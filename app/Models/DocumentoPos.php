<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DocumentoPos extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'documentos_pos';

    protected $fillable = [
        'company_id',
        'shift_id',
        'resolution_id',
        'prefix',
        'numeral',
        'secuencial',
        'cliente_id',
        'payload',
        'issue_date',
        'subtotal',
        'tax_total',
        'total',
        'currency',
        'payment_means_id',
        'payment_means_code',
        
        'payment_method_id',
        'payment_method_name',
        'notes',
        'documento_emitido_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'issue_date' => 'datetime',
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

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function resolution()
    {
        return $this->belongsTo(Resolution::class);
    }

    public function documentoEmitido()
    {
        return $this->belongsTo(DocumentoEmitido::class, 'documento_emitido_id');
    }

    public function getTotalFormattedAttribute()
    {
        return '$' . number_format((float) $this->total, 2, '.', ',');
    }

    public function getIsElectronicAttribute(): bool
    {
        return (bool) $this->documento_emitido_id;
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->documento_emitido_id) {
            return __('Sales invoice');
        }

        return match ($this->documentoEmitido?->status) {
            DocumentoEmitido::STATUS_ACCEPTED => __('Accepted'),
            DocumentoEmitido::STATUS_REJECTED => __('Rejected'),
            DocumentoEmitido::STATUS_ERROR => __('Error'),
            default => __('Pending'),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        if (! $this->documento_emitido_id) {
            return 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300';
        }

        return match ($this->documentoEmitido?->status) {
            DocumentoEmitido::STATUS_ACCEPTED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            DocumentoEmitido::STATUS_REJECTED, DocumentoEmitido::STATUS_ERROR => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }
}
