<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CompanyContract extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'company_contracts';

    protected $fillable = [
        'company_id',
        'price',
        'starts_at',
        'ends_at',
        'unlimited',
        'modules',
        'quota_mode',
        'renewal_type',
        'period_started_at',
        'shared_limit',
        'shared_used',
        'invoicing_limit',
        'invoicing_used',
        'pos_limit',
        'pos_used',
        'cotizaciones_limit',
        'cotizaciones_used',
    ];

    const QUOTA_MODE_PER_MODULE = 'per_module';
    const QUOTA_MODE_SHARED = 'shared';

    const RENEWAL_LIFETIME = 'lifetime';
    const RENEWAL_MONTHLY = 'monthly';

    const QUOTA_MODULES = ['invoicing', 'pos', 'cotizaciones'];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'unlimited' => 'boolean',
            'period_started_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  string  $module  Uno de: invoicing, pos, cotizaciones.
     * @return bool Si este contrato específicamente cubre ese módulo -- dos contratos pueden
     *              estar vigentes al mismo tiempo (mismas fechas o distintas) siempre que
     *              cubran módulos distintos, cada uno descuenta solo de lo suyo.
     */
    public function coversModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    /**
     * @return bool Si hoy cae dentro del rango de vigencia del contrato.
     */
    public function isWithinDateRange(): bool
    {
        $today = now()->startOfDay();

        if ($this->starts_at && $today->lt($this->starts_at->copy()->startOfDay())) {
            return false;
        }

        if ($this->ends_at && $today->gt($this->ends_at->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * @param  string  $module  Uno de: invoicing, pos, cotizaciones.
     * @return array{0: string, 1: string} Nombres de los campos [limit, used] a usar según el modo de cupo.
     */
    private function fieldsFor(string $module): array
    {
        $field = $this->quota_mode === self::QUOTA_MODE_SHARED ? 'shared' : $module;

        return ["{$field}_limit", "{$field}_used"];
    }

    /**
     * @param  string  $module  Uno de: invoicing, pos, cotizaciones.
     * @return int|null Cupo restante para ese módulo, null si no tiene límite.
     */
    public function remaining(string $module): ?int
    {
        [$limitField, $usedField] = $this->fieldsFor($module);
        $limit = $this->{$limitField};

        if ($this->unlimited || $limit === null) {
            return null;
        }

        return max(0, (int) $limit - (int) $this->{$usedField});
    }

    /**
     * Resetea los contadores de consumo si ya pasó el período contratado (renovación mensual).
     */
    public function resetPeriodIfNeeded(): void
    {
        if ($this->renewal_type !== self::RENEWAL_MONTHLY) {
            return;
        }

        $periodStart = $this->period_started_at ?? $this->created_at ?? now();

        if (now()->lt($periodStart->copy()->addMonth())) {
            return;
        }

        static::where('_id', $this->_id)->update([
            'shared_used' => 0,
            'invoicing_used' => 0,
            'pos_used' => 0,
            'cotizaciones_used' => 0,
            'period_started_at' => now(),
        ]);

        $this->refresh();
    }

    /**
     * Reclama el consumo de un documento contra el cupo del contrato, de forma atómica
     * (compare-and-swap, igual patrón que Resolution::claimNextNumber()). Notifica a los
     * administradores de la empresa al cruzar el 90% de uso y al agotarse el cupo.
     *
     * @param  string  $module  Uno de: invoicing, pos, cotizaciones.
     *
     * @throws \RuntimeException Si el contrato no está vigente o no queda cupo disponible.
     */
    public function claimUsage(string $module): void
    {
        if (! $this->isWithinDateRange()) {
            throw new \RuntimeException(__('This company\'s contract is not active right now.'));
        }

        if ($this->unlimited) {
            return;
        }

        $this->resetPeriodIfNeeded();

        [$limitField, $usedField] = $this->fieldsFor($module);
        $limit = $this->{$limitField};

        if ($limit === null) {
            return;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->refresh();
            $before = (int) $this->{$usedField};

            if ($before >= $limit) {
                throw new \RuntimeException(__('This company has no remaining document quota on its contract.'));
            }

            $after = $before + 1;
            $updated = static::where('_id', $this->_id)
                ->where($usedField, $before)
                ->update([$usedField => $after]);

            if ($updated > 0) {
                $this->{$usedField} = $after;
                $this->notifyIfQuotaCrossed((int) $limit, $before, $after);

                return;
            }
        }

        throw new \RuntimeException(__('Could not claim contract document usage (too much concurrency); try again.'));
    }

    private function notifyIfQuotaCrossed(int $limit, int $before, int $after): void
    {
        $company = $this->company;

        if (! $company) {
            return;
        }

        if ($before < $limit && $after >= $limit) {
            Notification::notifyUsers(
                $company->administratorUserIds(),
                __('Contract quota exhausted'),
                __('The document quota contracted by :company has run out.', ['company' => $company->name]),
            );

            return;
        }

        $warnAt = (int) ceil($limit * 0.9);
        if ($before < $warnAt && $after >= $warnAt) {
            Notification::notifyUsers(
                $company->administratorUserIds(),
                __('Contract quota almost exhausted'),
                __(':company is close to running out of its contracted document quota.', ['company' => $company->name]),
            );
        }
    }
}
