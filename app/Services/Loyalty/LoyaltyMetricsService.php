<?php

namespace App\Services\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyTransaction;

/**
 * Métricas resumidas de fidelización para la tarjeta del Panel -- separado
 * de PanelMetricsService a propósito: esas métricas son de ingresos/
 * documentos (ver PanelMetricsService::MODULES), mientras que fidelización
 * mide otra cosa (clientes activos, movimientos), no tiene sentido meterlo
 * en el mismo molde.
 */
class LoyaltyMetricsService
{
    /**
     * @return array{active_customers: int, earned_count: int, redeemed_count: int}
     */
    public function summary(Company $company, array $period): array
    {
        $companyId = (string) $company->_id;

        $periodTransactions = LoyaltyTransaction::where('company_id', $companyId)
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        return [
            'active_customers' => $company->loyaltyCustomers()->count(),
            'earned_count' => $periodTransactions->where('direction', LoyaltyTransaction::DIRECTION_EARN)->count(),
            'redeemed_count' => $periodTransactions->where('direction', LoyaltyTransaction::DIRECTION_REDEEM)->count(),
        ];
    }
}
