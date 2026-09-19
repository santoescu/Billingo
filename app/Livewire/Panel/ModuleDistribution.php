<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class ModuleDistribution extends PanelWidget
{
    public ?array $distribution = null;

    protected function loadData(): void
    {
        $modules = $this->visibleModules();
        if (empty($modules)) {
            return;
        }

        $this->distribution = $this->service()->moduleDistribution($this->company, $this->periodRange, $modules);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.module-distribution');
    }
}
