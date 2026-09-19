<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class TrendChart extends PanelWidget
{
    public ?array $trend = null;

    protected function loadData(): void
    {
        $modules = $this->visibleModules();
        if (empty($modules)) {
            return;
        }

        $this->trend = $this->service()->trend($this->company, $this->periodRange, $modules);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.trend-chart');
    }
}
