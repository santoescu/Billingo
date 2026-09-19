<?php

namespace App\Livewire\Panel;

use Livewire\Attributes\Lazy;

/**
 * Métodos de pago de UN módulo (facturación o POS) -- llega por prop, así
 * que las 2 tarjetas del panel son 2 instancias independientes.
 */
#[Lazy]
class PaymentMethods extends PanelWidget
{
    public string $module;

    public ?array $breakdown = null;

    protected function loadData(): void
    {
        if (! $this->showsModule($this->module)) {
            return;
        }

        $this->breakdown = $this->module === 'invoicing'
            ? $this->service()->paymentMethodsInvoicing($this->company, $this->periodRange)
            : $this->service()->paymentMethodsPos($this->company, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-chart');
    }

    public function render()
    {
        return view('livewire.panel.payment-methods');
    }
}
