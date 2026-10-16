<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class InventoryValue extends PanelWidget
{
    public ?array $inventory = null;

    protected function loadData(): void
    {
        if (empty($this->visibleModules())) {
            return;
        }

        $this->inventory = $this->service()->inventoryValue($this->company);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.inventory-value');
    }
}
