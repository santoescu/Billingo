<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'products';

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
    ];

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
