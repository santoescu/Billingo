<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class CashierComparison extends PanelWidget
{
    public ?array $comparison = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('pos')) {
            return;
        }

        $this->comparison = $this->service()->cashierComparison($this->company, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.cashier-comparison');
    }
}
