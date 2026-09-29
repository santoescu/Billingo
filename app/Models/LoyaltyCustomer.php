<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Cliente de fidelización de una empresa -- se identifica por "cliente_id"
 * (el mismo ThirdParty con identificación/NIT que ya usan facturación y
 * POS), no por teléfono: se busca/crea exactamente igual que un cliente
 * normal (ver ThirdPartyController::store()), así que es el mismo registro
 * en toda la app, nunca un duplicado paralelo. "phone"/"name"/"email" son
 * una copia de solo lectura tomada del ThirdParty al inscribir, para poder
 * listar sin cargar la relación en cada fila de la tabla.
 */
class LoyaltyCustomer extends Model
{
    use Auditable;

    protected $connection = 'mongodb';
    protected $table = 'loyalty_customers';

    protected $fillable = [
        'company_id',
        'cliente_id',
        'identification_type',
        'identificacion',
        'phone',
        'name',
        'email',
        'points_balance',
        'stamps_count',
        'cashback_balance',
        'current_tier_name',
        'lifetime_spend',
        'enrolled_at',
        'enrolled_source',
        'wallet_pass_reference',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'points_balance' => 'float',
            'stamps_count' => 'integer',
            'cashback_balance' => 'float',
            'lifetime_spend' => 'float',
            'enrolled_at' => 'datetime',
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

    public function transactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function instruments()
    {
        return $this->hasMany(LoyaltyInstrument::class);
    }

    public static function findByClienteId(Company $company, string $clienteId): ?self
    {
        return self::where('company_id', (string) $company->_id)
            ->where('cliente_id', $clienteId)
            ->first();
    }

    /**
     * Token único para la landing pública "mi tarjeta" (mismo criterio que
     * LoyaltyProgram::generateEnrollmentToken()) -- distinto del token de
     * inscripción del programa, este identifica a UN cliente puntual.
     */
    public static function generatePublicToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (self::where('public_token', $token)->exists());

        return $token;
    }

    public static function findByPublicToken(string $token): ?self
    {
        return self::where('public_token', $token)->first();
    }
}
