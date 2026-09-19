<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class TopClients extends PanelWidget
{
    public string $module;

    public ?array $items = null;

    protected function loadData(): void
    {
        if (! $this->showsModule($this->module)) {
            return;
        }

        $this->items = $this->service()->topClients($this->company, $this->module, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.top-clients');
    }
}
