<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Configuración del programa de fidelización de una empresa -- uno solo por
 * empresa (no una lista de programas), con las mecánicas activas y su
 * configuración propia guardadas en "settings" (mismo criterio que "payload"
 * en DocumentoEmitido/DocumentoPos: un array flexible en vez de una columna
 * por cada dato posible, ya que cada mecánica necesita campos distintos).
 */
class LoyaltyProgram extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'loyalty_programs';

    public const MECHANIC_STAMPS = 'stamps';
    public const MECHANIC_POINTS = 'points';
    public const MECHANIC_CASHBACK = 'cashback';
    public const MECHANIC_TIERS = 'tiers';
    public const MECHANIC_MEMBERSHIPS = 'memberships';
    public const MECHANIC_MULTIPASS = 'multipass';
    public const MECHANIC_GIFT_CARDS = 'gift_cards';
    public const MECHANIC_COUPONS = 'coupons';

    public const MECHANICS = [
        self::MECHANIC_STAMPS,
        self::MECHANIC_POINTS,
        self::MECHANIC_CASHBACK,
        self::MECHANIC_TIERS,
        self::MECHANIC_MEMBERSHIPS,
        self::MECHANIC_MULTIPASS,
        self::MECHANIC_GIFT_CARDS,
        self::MECHANIC_COUPONS,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'company_id',
        'name',
        'active_mechanics',
        'status',
        'branding',
        'settings',
        'enrollment_token',
    ];

    protected function casts(): array
    {
        return [
            'active_mechanics' => 'array',
            'branding' => 'array',
            'settings' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hasMechanic(string $mechanic): bool
    {
        return $this->status === self::STATUS_ACTIVE && in_array($mechanic, $this->active_mechanics ?? [], true);
    }

    public function settingsFor(string $mechanic): array
    {
        return $this->settings[$mechanic] ?? [];
    }

    /**
     * Valor en dinero de canjear cierta cantidad de puntos -- tasa aparte de
     * "earn_rate_per_currency" (esa es para acumular, no para canjear),
     * configurada en settings.points.redeem_value ("cuánto vale 1 punto al
     * canjearlo"). Sin esa tasa configurada, los puntos no se pueden canjear
     * como descuento en una venta (devuelve 0).
     */
    public function pointsRedeemValue(float $points): float
    {
        $rate = (float) ($this->settingsFor('points')['redeem_value'] ?? 0);

        return round($points * $rate, 2);
    }

    /**
     * Valor en dinero de canjear la tarjeta de sellos completa, configurado
     * en settings.stamps.reward_value -- separado de "reward_description"
     * (ese es solo el texto que ve el cliente, ej. "café gratis").
     */
    public function stampsRewardValue(): float
    {
        return (float) ($this->settingsFor('stamps')['reward_value'] ?? 0);
    }

    public static function mechanicLabel(string $mechanic): string
    {
        return match ($mechanic) {
            self::MECHANIC_STAMPS => __('Stamps'),
            self::MECHANIC_POINTS => __('Points'),
            self::MECHANIC_CASHBACK => __('Cashback'),
            self::MECHANIC_TIERS => __('Tiers'),
            self::MECHANIC_MEMBERSHIPS => __('Memberships'),
            self::MECHANIC_MULTIPASS => __('Multipass'),
            self::MECHANIC_GIFT_CARDS => __('Gift cards'),
            self::MECHANIC_COUPONS => __('Coupons'),
            default => $mechanic,
        };
    }

    /**
     * Token único para la landing pública de auto-inscripción (mismo
     * criterio que CatalogLink::generateToken()) -- global, no solo por
     * empresa, ya que identifica el programa en la URL sin necesitar el id
     * de la empresa ahí.
     */
    public static function generateEnrollmentToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (self::where('enrollment_token', $token)->exists());

        return $token;
    }

    public static function findByEnrollmentToken(string $token): ?self
    {
        return self::where('enrollment_token', $token)->first();
    }
}
