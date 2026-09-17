<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContract;

class AdminContractRenewalController extends Controller
{
    private const LOOKAHEAD_DAYS = 30;

    private const QUOTA_WARNING_THRESHOLD = 0.9;

    /**
     * Todos los contratos vigentes ahora mismo, para que el superadmin tenga la foto completa de
     * quién tiene contrato activo -- se resaltan arriba de la tabla los que están por vencer de
     * CUALQUIERA de las dos formas en que un contrato "se acaba":
     * (a) por fecha: "ends_at" cae dentro de LOOKAHEAD_DAYS días.
     * (b) por cupo: ya consumió QUOTA_WARNING_THRESHOLD (90%) o más de los documentos contratados
     *     en algún módulo (o del cupo compartido, si el contrato usa quota_mode "shared") -- un
     *     contrato de renovación mensual también puede quedarse sin cupo antes de que termine el
     *     mes, así que esto aplica sin importar el renewal_type.
     * Un contrato sin fecha de fin y sin límite de documentos (unlimited, o límites en null) nunca
     * entra en ninguna de las dos, así que nunca se resalta.
     *
     * Un contrato en riesgo se marca como "ya con renovación" si existe OTRO contrato para alguna
     * de las mismas empresas, con algún módulo en común, que siga vigente después de que este
     * termine -- ese nuevo contrato trae su propio cupo y su propia fecha, así que cubre ambos
     * casos.
     */
    public function index()
    {
        $windowEnd = now()->addDays(self::LOOKAHEAD_DAYS)->endOfDay();

        $active = CompanyContract::all()
            ->filter(fn (CompanyContract $contract) => $contract->isWithinDateRange());

        $allCompanyIds = $active->pluck('company_ids')->flatten()->unique()->all();
        $companies = Company::whereIn('_id', $allCompanyIds)->get()->keyBy(fn (Company $company) => (string) $company->_id);

        $rows = $active->map(function (CompanyContract $contract) use ($companies, $windowEnd) {
            $companyNames = collect($contract->company_ids ?? [])
                ->map(fn ($id) => $companies->get((string) $id)?->name)
                ->filter()
                ->join(', ');

            $expiringByDate = ! $contract->unlimited && $contract->ends_at && $contract->ends_at->lte($windowEnd);
            $quotaByModule = $this->quotaByModule($contract);
            $needsAttention = $expiringByDate || collect($quotaByModule)->contains('warning', true);

            return [
                'id' => (string) $contract->_id,
                'companies' => $companyNames ?: __('Unknown'),
                'modules' => $contract->modules ?? [],
                'unlimited' => $contract->unlimited,
                'quota_by_module' => $quotaByModule,
                'ends_at' => $contract->ends_at?->format('Y-m-d'),
                'days_left' => $contract->ends_at ? now()->startOfDay()->diffInDays($contract->ends_at->copy()->startOfDay(), false) : null,
                'price' => $contract->net_price,
                'expiring_by_date' => $expiringByDate,
                'needs_attention' => $needsAttention,
                'has_replacement' => $needsAttention && $this->hasFutureContract($contract),
                'contacted_at' => $contract->renewal_contacted_at?->setTimezone('America/Bogota')->format('Y-m-d'),
            ];
        })->sortBy([
            function ($row) {
                if (! $row['needs_attention'] || $row['has_replacement']) {
                    return 2;
                }

                return $row['contacted_at'] ? 1 : 0;
            },
            fn ($row) => $row['days_left'] ?? PHP_INT_MAX,
        ])->values();

        $atRisk = $rows->filter(fn ($row) => $row['needs_attention'] && ! $row['has_replacement']);

        return view('admin.contract-renewals', [
            'rows' => $rows,
            'lookaheadDays' => self::LOOKAHEAD_DAYS,
            'totalAtRisk' => $atRisk->sum('price'),
            'countAtRisk' => $atRisk->count(),
        ]);
    }

    /**
     * Marca/desmarca a mano que ya se contactó a la empresa sobre la renovación de este contrato
     * -- puramente informativo (ver comentario en CompanyContract::$fillable), no cambia cupo ni
     * vigencia. Solo un toggle: si ya estaba marcado, lo quita.
     */
    public function toggleContacted(string $contractId)
    {
        $contract = CompanyContract::findOrFail($contractId);

        $contract->update([
            'renewal_contacted_at' => $contract->renewal_contacted_at ? null : now(),
        ]);

        return redirect()->route('admin.contract-renewals.index');
    }

    /**
     * @return array<string, array{text: string, warning: bool}> Cupo por cada módulo que este
     *         contrato cubre, para mostrarlo pegado al badge de ese módulo en la tabla en vez de
     *         repetir el nombre del módulo en una columna aparte. Si quota_mode es "shared" el
     *         mismo cupo (compartido entre todos los módulos) se repite bajo cada uno. Un módulo
     *         sin límite configurado (o contrato ilimitado) no aparece en el arreglo -- la vista
     *         se encarga de mostrar "Ilimitado" o nada en ese caso.
     */
    private function quotaByModule(CompanyContract $contract): array
    {
        if ($contract->unlimited) {
            return [];
        }

        $isShared = $contract->quota_mode === CompanyContract::QUOTA_MODE_SHARED;
        $limit = $isShared ? $contract->shared_limit : null;
        $used = $isShared ? (int) $contract->shared_used : null;
        $result = [];

        foreach ($contract->modules ?? [] as $module) {
            if (! $isShared) {
                $limit = $contract->{"{$module}_limit"};
                $used = (int) $contract->{"{$module}_used"};
            }

            if ($limit === null || $limit <= 0) {
                continue;
            }

            $pct = round($used / $limit * 100);

            $result[$module] = [
                'text' => __(':used of :limit (:pct%)', ['used' => $used, 'limit' => $limit, 'pct' => $pct]),
                'warning' => $pct >= self::QUOTA_WARNING_THRESHOLD * 100,
            ];
        }

        return $result;
    }

    /**
     * Si el contrato tiene "ends_at", busca otro contrato que siga vigente después de esa fecha
     * (o sin fecha de fin) -- eso cubre el caso de vencimiento por fecha. Si no tiene "ends_at"
     * (solo está por agotar cupo), busca uno que ya esté programado para arrancar después de hoy
     * -- así se detecta que ya se dejó lista la renovación aunque el contrato actual no venza por
     * fecha.
     */
    private function hasFutureContract(CompanyContract $contract): bool
    {
        $query = CompanyContract::whereIn('company_ids', $contract->company_ids ?? [])
            ->where('_id', '!=', $contract->_id);

        if ($contract->ends_at) {
            $query->where(function ($q) use ($contract) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $contract->ends_at);
            });
        } else {
            $query->where('starts_at', '>', now());
        }

        return $query->get()
            ->contains(fn (CompanyContract $other) => ! empty(array_intersect($contract->modules ?? [], $other->modules ?? [])));
    }
}
