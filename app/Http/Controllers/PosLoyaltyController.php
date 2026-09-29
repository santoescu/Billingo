<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyInstrument;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyPosRedemptionService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Endpoints de fidelización que usa la pantalla de venta del POS -- viven
 * bajo el middleware de "pos" (no el de "loyalty"), para que un cajero con
 * rol de POS pueda usarlos sin necesitar además el rol de fidelización.
 */
class PosLoyaltyController extends Controller
{
    /**
     * Busca al cliente de fidelización por identificación y devuelve sus
     * saldos + lo que valdría canjear cada uno, ya calculado -- "found:
     * false" en vez de 404, igual que LoyaltyCustomerController::lookup(),
     * para que el POS pueda mostrar "este cliente no está en fidelización"
     * sin tratarlo como un error.
     */
    public function lookup(Request $request)
    {
        $company = $this->currentCompany($request);
        $program = $company->loyaltyProgram;

        if (! $company->hasModule('loyalty') || ! $program || $program->status !== LoyaltyProgram::STATUS_ACTIVE) {
            return response()->json(['found' => false]);
        }

        $identificacion = (string) $request->query('identificacion', '');
        $customer = $identificacion !== '' ? app(LoyaltyPosRedemptionService::class)->resolveCustomer($company, $identificacion) : null;

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        $stampsRequired = (int) ($program->settingsFor('stamps')['stamps_required'] ?? 0);

        return response()->json([
            'found' => true,
            'points' => [
                'active' => $program->hasMechanic(LoyaltyProgram::MECHANIC_POINTS) && $program->pointsRedeemValue(1) > 0,
                'balance' => (float) $customer->points_balance,
                'redeem_value_per_point' => $program->pointsRedeemValue(1),
            ],
            'stamps' => [
                'active' => $program->hasMechanic(LoyaltyProgram::MECHANIC_STAMPS) && $program->stampsRewardValue() > 0,
                'count' => (int) $customer->stamps_count,
                'required' => $stampsRequired,
                'ready' => $stampsRequired > 0 && (int) $customer->stamps_count >= $stampsRequired,
                'reward_value' => $program->stampsRewardValue(),
            ],
            'cashback' => [
                'active' => $program->hasMechanic(LoyaltyProgram::MECHANIC_CASHBACK),
                'balance' => (float) $customer->cashback_balance,
            ],
            'instruments' => $customer->instruments()->where('status', LoyaltyInstrument::STATUS_ACTIVE)->get()
                ->filter(fn (LoyaltyInstrument $i) => $i->isRedeemable() && in_array($i->type, [LoyaltyInstrument::TYPE_COUPON, LoyaltyInstrument::TYPE_GIFT_CARD], true))
                ->map(fn (LoyaltyInstrument $i) => [
                    'code' => $i->code,
                    'type' => LoyaltyInstrument::typeLabel($i->type),
                    'label' => $i->plan_name ?: $i->code,
                    'remaining_value' => (float) $i->remaining_value,
                ])->values(),
        ]);
    }

    /**
     * Valida un código (cupón/tarjeta de regalo) suelto, sin necesidad de
     * que esté asignado al cliente identificado -- algunos cupones son de
     * uso general, no ligados a una persona.
     */
    public function validateCode(Request $request)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate(['code' => 'required|string']);

        $instrument = LoyaltyInstrument::where('company_id', (string) $company->_id)
            ->where('code', strtoupper(trim($data['code'])))
            ->first();

        if (! $instrument || ! $instrument->isRedeemable()) {
            return response()->json(['valid' => false, 'message' => __('Code not found.')], 404);
        }

        if (! in_array($instrument->type, [LoyaltyInstrument::TYPE_COUPON, LoyaltyInstrument::TYPE_GIFT_CARD], true)) {
            return response()->json(['valid' => false, 'message' => __(':code cannot be redeemed as a discount here.', ['code' => $instrument->code])], 422);
        }

        return response()->json([
            'valid' => true,
            'code' => $instrument->code,
            'type' => LoyaltyInstrument::typeLabel($instrument->type),
            'label' => $instrument->plan_name ?: $instrument->code,
            'discount_type' => $instrument->discount_type,
            'discount_value' => (float) $instrument->discount_value,
            'remaining_value' => (float) $instrument->remaining_value,
        ]);
    }

    /**
     * Recalcula en el servidor el descuento total de lo que el cajero
     * seleccionó -- se llama en vivo mientras arma la venta, para que el
     * total mostrado (y lo que debe cuadrar con los medios de pago) ya
     * incluya el descuento real, no una estimación hecha en el navegador.
     */
    public function previewDiscount(Request $request)
    {
        $company = $this->currentCompany($request);
        $program = $company->loyaltyProgram;

        $data = $request->validate([
            'identificacion' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'points_amount' => 'nullable|numeric|min:0',
            'redeem_stamps' => 'nullable|boolean',
            'cashback_amount' => 'nullable|numeric|min:0',
            'codes' => 'nullable|array',
            'codes.*' => 'string',
        ]);

        if (! $company->hasModule('loyalty') || ! $program || $program->status !== LoyaltyProgram::STATUS_ACTIVE) {
            return response()->json(['message' => __('This service is not available right now.')], 422);
        }

        $customer = filled($data['identificacion'] ?? null)
            ? app(LoyaltyPosRedemptionService::class)->resolveCustomer($company, $data['identificacion'])
            : null;

        try {
            $result = app(LoyaltyPosRedemptionService::class)->preview($company, $program, $customer, $data, (float) $data['subtotal']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
