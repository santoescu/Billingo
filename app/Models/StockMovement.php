<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StockMovement extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'stock_movements';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'reason',
        'user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
