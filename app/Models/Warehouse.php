<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class Warehouse extends Model
{
    use Auditable;

    protected $connection = 'mongodb';
    protected $table = 'warehouses';

    protected $fillable = [
        'company_id',
        'name',
        'address',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Productos que tienen stock (aunque sea cero) registrado en esta bodega.
     * No es una FK: "warehouse_stocks" es un arreglo embebido en cada producto
     * (ver Product::stockForWarehouse()), así que se consulta por dot-notation.
     */
    public function products()
    {
        return Product::where('company_id', $this->company_id)
            ->where('warehouse_stocks.warehouse_id', (string) $this->_id)
            ->get();
    }
}
