<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class WarehouseComparison extends PanelWidget
{
    public ?array $comparison = null;

    protected function loadData(): void
    {
        $modules = array_intersect($this->visibleModules(), ['invoicing', 'pos', 'cotizaciones']);
        if (empty($modules)) {
            return;
        }

        $this->comparison = $this->service()->warehouseComparison($this->company, $this->periodRange, $modules);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.warehouse-comparison');
    }
}
