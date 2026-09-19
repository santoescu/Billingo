<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

/**
 * Tarjeta de KPIs de UN módulo (emisión/POS/cotizaciones/recepción) -- el
 * módulo llega por prop, así que las 4 tarjetas del panel son 4 instancias
 * independientes de este mismo componente, cada una con su propia consulta.
 */
#[Lazy]
class MetricCard extends PanelWidget
{
    public string $module;

    public ?array $metrics = null;

    protected function loadData(): void
    {
        if (! $this->showsModule($this->module)) {
            return;
        }

        $this->metrics = $this->service()->metrics($this->company, $this->module, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.metric-card');
    }
}
