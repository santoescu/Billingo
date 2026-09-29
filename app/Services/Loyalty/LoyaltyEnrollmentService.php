<?php

namespace App\Services\Loyalty;

use App\Models\Company;
use App\Models\LoyaltyCustomer;
use App\Models\ThirdParty;

/**
 * Da de alta (o recupera) un cliente de fidelización -- el mismo método
 * sirve para los 3 puntos de entrada posibles: alta rápida desde el módulo,
 * landing pública de auto-inscripción, y alta implícita al acumular desde
 * una venta/factura (ver LoyaltyAccrualService). El cliente se busca/crea
 * por identificación exactamente igual que ThirdPartyController::store()
 * (mismo ThirdParty, con el rol "cliente" agregado si hacía falta), así que
 * nunca queda un duplicado paralelo al de facturación/POS.
 */
class LoyaltyEnrollmentService
{
    public function enroll(Company $company, array $clientData, string $source = 'manual'): LoyaltyCustomer
    {
        $cliente = $this->findOrCreateThirdParty($company, $clientData);

        return $this->enrollFromThirdParty($company, $cliente, $source);
    }

    public function enrollFromThirdParty(Company $company, ThirdParty $cliente, string $source = 'manual'): LoyaltyCustomer
    {
        $customer = LoyaltyCustomer::findByClienteId($company, (string) $cliente->_id);
        if ($customer) {
            return $customer;
        }

        return LoyaltyCustomer::create([
            'company_id' => (string) $company->_id,
            'cliente_id' => (string) $cliente->_id,
            'identification_type' => $cliente->identification_type,
            'identificacion' => $cliente->identificacion,
            'name' => $cliente->name,
            'phone' => $cliente->phone,
            'email' => $cliente->email,
            'points_balance' => 0,
            'stamps_count' => 0,
            'cashback_balance' => 0,
            'lifetime_spend' => 0,
            'enrolled_at' => now(),
            'enrolled_source' => $source,
            'public_token' => LoyaltyCustomer::generatePublicToken(),
        ]);
    }

    private function findOrCreateThirdParty(Company $company, array $data): ThirdParty
    {
        $existing = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identification_type', $data['identification_type'])
            ->where('identificacion', $data['identificacion'])
            ->first();

        if ($existing) {
            $roles = collect($existing->roles ?? [])->push('cliente')->unique()->values()->all();
            $existing->update(['roles' => $roles]);

            return $existing;
        }

        $data['company_id'] = (string) $company->_id;
        $data['roles'] = ['cliente'];
        $data['dv'] = ($data['identification_type'] ?? null) === '31'
            ? Company::calculateVerificationDigit($data['identificacion'])
            : ($data['dv'] ?? null);

        if (array_key_exists('fiscal_responsibilities', $data)) {
            $data['fiscal_responsibilities'] = implode(';', $data['fiscal_responsibilities'] ?? []);
        }

        return ThirdParty::create($data);
    }
}
