<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class LowStockProducts extends PanelWidget
{
    public array $products = [];

    protected function loadData(): void
    {
        if (empty($this->visibleModules())) {
            return;
        }

        $this->products = $this->service()->lowStockProducts($this->company);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.low-stock-products');
    }
}
