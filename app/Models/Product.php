<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    protected $connection = 'mongodb';
    protected $table = 'products';

    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'company_id',
        'code',
        'description',
        'barcode',
        'unit_price',
        'extra_prices',
        'unit_code',
        'tracks_inventory',
        'stock',
        'warehouse_stocks',
        'average_cost',
        'status',
        'image_data',
        'image_mime',
    ];

    protected $appends = ['image_url'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'active')
                ->orWhereNull('status')
                ->orWhere('status', '');
        });
    }

    public function getUnitPriceFormattedAttribute()
    {
        return '$' . number_format((float) $this->unit_price, 2, ',', '.');
    }

    public function getAverageCostFormattedAttribute()
    {
        return '$' . number_format((float) ($this->average_cost ?? 0), 2, ',', '.');
    }

    /**
     * "URL" (en realidad un data URI) de la foto del producto, null si no
     * tiene -- guardada en base64 directo en el documento de Mongo, no en
     * disco (los despliegues de este proyecto no persisten storage/ entre
     * versiones, igual que el certificado de firma digital de la empresa).
     * Tanto la tabla de Productos como el grid del POS la usan; si viene
     * null, cada pantalla se encarga de mostrar su propio ícono de respaldo.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_data || ! $this->image_mime) {
            return null;
        }

        return 'data:' . $this->image_mime . ';base64,' . $this->image_data;
    }

    /**
     * Parte del stock total que todavía no se ha asignado a ninguna bodega
     * (stock - suma de lo asignado en "warehouse_stocks").
     */
    public function getUnassignedStockAttribute(): float
    {
        $assigned = round(array_sum(array_column($this->warehouse_stocks ?? [], 'stock')), 2);

        return round((float) ($this->stock ?? 0) - $assigned, 2);
    }

    public function stockForWarehouse(string $warehouseId): float
    {
        foreach ($this->warehouse_stocks ?? [] as $entry) {
            if (($entry['warehouse_id'] ?? null) === $warehouseId) {
                return (float) ($entry['stock'] ?? 0);
            }
        }

        return 0.0;
    }
}
