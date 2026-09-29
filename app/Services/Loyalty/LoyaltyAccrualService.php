<?php

namespace App\Services\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyProgram;
use App\Models\ThirdParty;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Punto de enganche opcional entre POS/facturación y fidelización -- se
 * llama desde IssueDocumentService después de que la venta/factura ya quedó
 * guardada, nunca antes. Si la empresa no tiene el módulo de fidelización,
 * o el cliente de la venta es el "Consumidor final" genérico (ver
 * Controller::defaultClient(), no identifica a una persona real), no hace
 * nada -- la venta sigue su curso igual. Cualquier error de acá adentro se
 * atrapa y se registra, pero NUNCA se deja propagar: fidelización jamás
 * debe poder tumbar una venta o una factura.
 */
class LoyaltyAccrualService
{
    public function accrueForSale(Company $company, ?ThirdParty $cliente, float $total, string $sourceType, ?string $documentoPosId = null, ?string $documentoEmitidoId = null): void
    {
        if (! $company->hasModule('loyalty')) {
            return;
        }

        try {
            $program = $company->loyaltyProgram;
            if (! $program || $program->status !== LoyaltyProgram::STATUS_ACTIVE) {
                return;
            }

            if (! $cliente || $this->isGenericConsumer($cliente)) {
                return;
            }

            $customer = app(LoyaltyEnrollmentService::class)->enrollFromThirdParty($company, $cliente, $sourceType);

            app(LoyaltyAccrualCalculator::class)->apply($program, $customer, $total, $sourceType, $documentoPosId, $documentoEmitidoId);
        } catch (Throwable $e) {
            Log::warning('Fidelización: no se pudo acumular para esta venta/factura.', [
                'company_id' => (string) $company->_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mismo criterio que Controller::defaultClient() -- ese identificación
     * genérica es el "Consumidor final" compartido por todas las ventas
     * anónimas de POS, no una persona real a la que se le pueda acumular.
     */
    private function isGenericConsumer(ThirdParty $cliente): bool
    {
        return $cliente->identificacion === '222222222222';
    }
}
