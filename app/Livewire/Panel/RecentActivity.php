<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class RecentActivity extends PanelWidget
{
    public array $items = [];

    protected function loadData(): void
    {
        $modules = $this->visibleModules();
        if (empty($modules)) {
            return;
        }

        $items = collect();
        foreach ($modules as $module) {
            $items = $items->merge($this->service()->recentActivityForModule($this->company, $module, $this->periodRange));
        }

        $this->items = $items->sortByDesc('created_at')->take(8)->values()->all();
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.recent-activity');
    }
}
