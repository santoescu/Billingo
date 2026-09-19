<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class Payables extends PanelWidget
{
    public ?array $payables = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('receiving')) {
            return;
        }

        $this->payables = $this->service()->payables($this->company);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.payables');
    }
}
