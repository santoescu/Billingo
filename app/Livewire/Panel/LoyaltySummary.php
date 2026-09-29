<?php

namespace App\Livewire\Panel;

use App\Services\Loyalty\LoyaltyMetricsService;
use Livewire\Attributes\Lazy;

/**
 * Tarjeta de fidelización del Panel -- aparte del molde de
 * PanelMetricsService (ese mide ingresos/documentos, ver
 * PanelMetricsService::MODULES, que a propósito no incluye "loyalty": sus
 * métricas son otra cosa, clientes activos y movimientos, no aplica el
 * mismo cálculo de ingresos/tendencia).
 */
#[Lazy]
class LoyaltySummary extends PanelWidget
{
    public ?array $stats = null;

    protected function loadData(): void
    {
        if (! $this->showsModule('loyalty')) {
            return;
        }

        $this->stats = app(LoyaltyMetricsService::class)->summary($this->company, $this->periodRange);
    }

    public function placeholder()
    {
        return view('panel.partials.skeleton-card');
    }

    public function render()
    {
        return view('livewire.panel.loyalty-summary');
    }
}
