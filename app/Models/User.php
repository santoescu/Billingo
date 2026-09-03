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
    protected $fillable = ['name', 'email', 'password', 'locale', 'appearance', 'role'];
    protected $hidden = ['password', 'remember_token'];

    public const GLOBAL_ADMIN_ROLES = ['superadmin'];
    public const ROLE_REFERRER = 'referrer';

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
        ];
    }

    public function isGlobalAdmin(): bool
    {
        return in_array($this->role, self::GLOBAL_ADMIN_ROLES, true);
    }

    /**
     * "Vendedor" de Billingo: alguien del equipo que puede quedar asignado
     * como referido en un CompanyContract (ver commission_percentage) y
     * entrar a ver sus propias ventas/comisiones -- no es lo mismo que
     * superadmin, no ve el resto del panel.
     */
    public function isReferrer(): bool
    {
        return $this->role === self::ROLE_REFERRER;
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
