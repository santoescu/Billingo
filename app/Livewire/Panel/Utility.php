<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class Utility extends PanelWidget
{
    public ?array $utility = null;

    protected function loadData(): void
    {
        $hasInvoicing = $this->showsModule('invoicing');
        $hasPos = $this->showsModule('pos');

        if (! $hasInvoicing && ! $hasPos) {
            return;
        }

        $this->utility = $this->service()->utility($this->company, $this->periodRange, $hasInvoicing, $hasPos);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.utility');
    }
}
