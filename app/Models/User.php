<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, Notifiable, CanResetPassword;

    protected $table = 'users'; 
    protected $fillable = ['name', 'email', 'password', 'locale', 'appearance', 'role', 'referral_code', 'can_refer'];
    protected $hidden = ['password', 'remember_token'];

    public const GLOBAL_ADMIN_ROLES = ['superadmin'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_refer' => 'boolean',
        ];
    }

    public function isGlobalAdmin(): bool
    {
        return in_array($this->role, self::GLOBAL_ADMIN_ROLES, true);
    }

    /**
     * Si puede compartir su link de referido y ganar comisión (ver ReferralController) -- por
     * aprobación de superadmin (ver SuperadminController::toggleCanRefer()), no abierto a
     * cualquiera: superadmin ya puede por su rol; cualquier otro usuario (antes había un rol
     * "vendedor" aparte para esto, pero es el mismo concepto que referir, así que se unificó)
     * necesita que se le habilite "can_refer" a mano.
     */
    public function canRefer(): bool
    {
        return $this->isGlobalAdmin() || (bool) $this->can_refer;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Mismo patrón que CatalogLink::generateToken() -- único a nivel global. El código de
     * referido es del USUARIO, no de una empresa puntual (ver Company::referredByUser()): un
     * usuario puede administrar varias empresas y una empresa puede tener varios usuarios, así
     * que la ganancia por referir tiene que quedar atada a la persona que compartió el link, no
     * a "una" de sus empresas.
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = bin2hex(random_bytes(6));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Cuentas creadas antes de este cambio no traen referral_code -- se genera y guarda la
     * primera vez que hace falta (ver ReferralController::index()), en vez de correr una
     * migración de datos aparte.
     */
    public function ensureReferralCode(): string
    {
        if (empty($this->referral_code)) {
            $this->update(['referral_code' => self::generateReferralCode()]);
        }

        return $this->referral_code;
    }

    /**
     * Empresas que se registraron a través del link de referido de este usuario.
     */
    public function referredCompanies()
    {
        return $this->hasMany(Company::class, 'referred_by_user_id');
    }

    public function memberships()
    {
        return $this->hasMany(CompanyMember::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class)->orderByDesc('created_at');
    }

    public function unreadNotificationsCount(): int
    {
        return Notification::forUser((string) $this->_id)->unread()->count();
    }

    /**
     * Empresas a las que pertenece el usuario, cada una con su membresía
     * (rol + módulos) adjunta en $company->membership.
     */
    public function companiesWithMembership(bool $onlyActive = true)
    {
        $memberships = $this->memberships()->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $query = Company::whereIn('_id', $memberships->pluck('company_id')->all());

        if ($onlyActive) {
            $query->active();
        }

        return $query->orderBy('name')->get()->map(function ($company) use ($memberships) {
            $company->membership = $memberships->first(fn ($m) => (string) $m->company_id === (string) $company->_id);

            return $company;
        });
    }

    /**
     * ¿Tiene el usuario acceso administrativo a la empresa dada (editarla,
     * gestionar sus miembros)? Es así si es 'owner', o si tiene el rol
     * 'administrador' asignado en al menos uno de los módulos de la empresa.
     */
    public static function hasCompanyAdminAccess(?string $role, array $modules): bool
    {
        if ($role === 'owner') {
            return true;
        }

        return collect($modules)->contains(fn ($assignment) => ($assignment['role'] ?? null) === 'administrador');
    }
}
