<?php

namespace App\Livewire\Panel;

use App\Models\Company;
use App\Services\Panel\PanelMetricsService;
use Livewire\Component;

/**
 * Base de todas las tarjetas/gráficas del Panel (ver resources/views/panel.blade.php)
 * -- cada una es un componente Livewire perezoso (#[Lazy] en la clase hija) que pinta
 * un esqueleto al instante y dispara su propia consulta por separado en una segunda
 * petición, para que una tarjeta lenta no bloquee a las demás. mount() resuelve lo que
 * todas necesitan (empresa, rango de fechas, rol del usuario por módulo) antes de que
 * la clase hija llame al único método del servicio que le corresponde.
 */
abstract class PanelWidget extends Component
{
    public string $companyId;

    public string $period;

    public string $moduleFilter;

    public string $periodLabel = '';

    protected ?Company $company = null;

    protected array $periodRange = [];

    private const PERIOD_LABELS = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'last_month' => 'Last month',
        'year' => 'This year',
    ];

    public function mount(string $companyId, string $period, string $moduleFilter): void
    {
        $this->companyId = $companyId;
        $this->period = $period;
        $this->moduleFilter = $moduleFilter;
        $this->periodLabel = __(self::PERIOD_LABELS[$period] ?? self::PERIOD_LABELS['month']);
        $this->company = Company::find($companyId);
        $this->periodRange = $this->service()->resolvePeriod($period);

        if ($this->company) {
            $this->loadData();
        }
    }

    abstract protected function loadData(): void;

    protected function service(): PanelMetricsService
    {
        return app(PanelMetricsService::class);
    }

    protected function isModuleAdmin(string $module): bool
    {
        $roles = session('selected_company.modules', []);

        return in_array($roles[$module] ?? null, ['owner', 'administrador'], true);
    }

    protected function showsModule(string $module): bool
    {
        return $this->isModuleAdmin($module) && ($this->moduleFilter === 'all' || $this->moduleFilter === $module);
    }

    /**
     * @return array<int, string>
     */
    protected function visibleModules(): array
    {
        return array_values(array_filter(PanelMetricsService::MODULES, fn ($m) => $this->showsModule($m)));
    }
}
