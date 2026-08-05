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
        'status',
    ];

    // OJO: NO castear estos campos como 'array' -- en mongodb/laravel-mongodb
    // ese cast serializa el valor a un STRING JSON al guardar (no es el cast
    // nativo de Mongo), lo que rompe las consultas por dot-notation como
    // "warehouse_stocks.warehouse_id" (ver Warehouse::products()) porque Mongo
    // ve un string en vez de un arreglo de subdocumentos. Sin cast, el driver
    // ya devuelve arrays de PHP planos por su propio typeMap (document=>array),
    // así que no hace falta castear nada para leerlos con array_column()/collect().

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
