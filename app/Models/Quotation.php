<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Quotation extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'quotations';

    protected $fillable = [
        'company_id',
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
        'notes',
        'documento_pos_id',
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

    public function resolution()
    {
        return $this->belongsTo(Resolution::class);
    }

    public function documentoPos()
    {
        return $this->belongsTo(DocumentoPos::class, 'documento_pos_id');
    }

    public function documentoEmitido()
    {
        return $this->belongsTo(DocumentoEmitido::class, 'documento_emitido_id');
    }

    public function getTotalFormattedAttribute()
    {
        return '$' . number_format((float) $this->total, 2, '.', ',');
    }

    /**
     * El estado de una cotización se deriva de a cuál de los dos posibles
     * documentos ya se convirtió (venta de POS o factura electrónica), sin
     * un campo "status" aparte que se pueda desincronizar de esos enlaces
     * -- mismo criterio que DocumentoPos::getStatusLabelAttribute() con
     * documento_emitido_id.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->documento_pos_id) {
            return __('Converted to POS sale');
        }

        if (! $this->documento_emitido_id) {
            return __('Pending');
        }

        return match ($this->documentoEmitido?->status) {
            DocumentoEmitido::STATUS_ACCEPTED => __('Invoiced'),
            DocumentoEmitido::STATUS_REJECTED => __('Rejected'),
            DocumentoEmitido::STATUS_ERROR => __('Error'),
            default => __('Pending'),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        if ($this->documento_pos_id) {
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        }

        if (! $this->documento_emitido_id) {
            return 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300';
        }

        return match ($this->documentoEmitido?->status) {
            DocumentoEmitido::STATUS_ACCEPTED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            DocumentoEmitido::STATUS_REJECTED, DocumentoEmitido::STATUS_ERROR => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }

    public function getIsConvertedAttribute(): bool
    {
        return (bool) ($this->documento_pos_id || $this->documento_emitido_id);
    }
}
