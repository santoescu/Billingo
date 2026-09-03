<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ActivityLog extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'activity_logs';

    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'model',
        'model_id',
        'label',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_CREATED => __('Created'),
            self::ACTION_DELETED => __('Deleted'),
            default => __('Updated'),
        };
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabel($this->action);
    }

    public static function actionBadgeClasses(string $action): string
    {
        return match ($action) {
            self::ACTION_CREATED => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::ACTION_DELETED => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            default => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        };
    }

    public function getActionBadgeClassesAttribute(): string
    {
        return self::actionBadgeClasses($this->action);
    }

    /**
     * Nombre legible del tipo de registro afectado (Producto, Cliente, etc.)
     * a partir del nombre corto de la clase guardado en "model" -- ver
     * Auditable::recordAudit(). Si el modelo ya no está en este mapa (uno
     * nuevo al que no se le agregó todavía el trait Auditable), muestra el
     * nombre de la clase tal cual.
     */
    public function getModelLabelAttribute(): string
    {
        $labels = [
            'Product' => __('Product'),
            'ThirdParty' => __('Client/Provider'),
            'Warehouse' => __('Warehouse'),
            'PriceType' => __('Price type'),
            'PaymentMethod' => __('Payment method'),
            'Seller' => __('Seller'),
            'Resolution' => __('Resolution'),
            'DianCertificate' => __('Digital certificate'),
            'CatalogLink' => __('Catalog link'),
            'CompanyMember' => __('Member'),
        ];

        return $labels[$this->model] ?? $this->model;
    }
}
