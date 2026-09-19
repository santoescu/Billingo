<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class TopSuppliers extends PanelWidget
{
    public ?array $items = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('receiving')) {
            return;
        }

        $this->items = $this->service()->topSuppliers($this->company, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.top-suppliers');
    }
}
