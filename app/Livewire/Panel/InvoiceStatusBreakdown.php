<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

#[Lazy]
class InvoiceStatusBreakdown extends PanelWidget
{
    public ?array $breakdown = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('invoicing')) {
            return;
        }

        $this->breakdown = $this->service()->invoiceStatusBreakdown($this->company, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.invoice-status-breakdown');
    }
}
