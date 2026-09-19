<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class Receivables extends PanelWidget
{
    public ?array $receivables = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('invoicing')) {
            return;
        }

        $this->receivables = $this->service()->receivables($this->company);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.receivables');
    }
}
