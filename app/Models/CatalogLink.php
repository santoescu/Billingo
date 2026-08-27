<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CatalogLink extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'catalog_links';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'label',
        'token',
        'primary_price_type_id',
        'visible_price_type_ids',
        'contract_warning_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'contract_warning_notified_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function primaryPriceType()
    {
        return $this->belongsTo(PriceType::class, 'primary_price_type_id');
    }

    /**
     * Genera un token nuevo, único a nivel global (no solo por empresa) --
     * es lo que identifica el link en la URL pública, sin necesitar el id
     * de la empresa ahí.
     */
    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Resuelve el link (y de paso la empresa, vía la relación) a partir del
     * token de la URL pública.
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }
}
