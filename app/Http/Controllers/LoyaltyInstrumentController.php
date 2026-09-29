<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyInstrument;
use App\Models\ThirdParty;
use App\Services\Loyalty\LoyaltyInstrumentService;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Http\Request;
use RuntimeException;

class LoyaltyInstrumentController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $instruments = $company->loyaltyInstruments()->with('customer')->orderByDesc('created_at')->get();

        return view('loyalty.instruments.index', compact('company', 'instruments'));
    }

    public function store(Request $request, LoyaltyInstrumentService $instrumentService)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', LoyaltyInstrument::TYPES),
            'identificacion' => 'nullable|string|max:20',
            'plan_name' => 'nullable|string|max:150',
            'initial_value' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'expires_after_days' => 'nullable|integer|min:1',
        ]);

        $customer = null;
        if (filled($data['identificacion'] ?? null)) {
            $cliente = ThirdParty::where('company_id', (string) $company->_id)->where('identificacion', $data['identificacion'])->first();
            $customer = $cliente ? LoyaltyCustomer::findByClienteId($company, (string) $cliente->_id) : null;
        }

        $instrument = $instrumentService->issue($company, $data['type'], $data, $customer);

        return response()->json(['id' => (string) $instrument->_id, 'code' => $instrument->code]);
    }

    public function cancel(Request $request, string $loyaltyInstrument, LoyaltyInstrumentService $instrumentService)
    {
        $company = $this->currentCompany($request);
        $instrument = $company->loyaltyInstruments()->where('_id', $loyaltyInstrument)->first();

        abort_unless($instrument, 404);

        $instrumentService->cancel($instrument);

        return response()->json(['success' => true]);
    }

    /**
     * Canjea por código -- el cajero teclea/escanea lo que el cliente le
     * muestra, no necesita saber a quién está asignado ni entrar primero a
     * su ficha.
     */
    public function redeem(Request $request, LoyaltyInstrumentService $instrumentService, LoyaltyRedemptionService $redemptionService)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'code' => 'required|string',
            'amount' => 'nullable|numeric',
        ]);

        $instrument = $instrumentService->findByCode($company, $data['code']);

        if (! $instrument) {
            return response()->json(['message' => __('Code not found.')], 404);
        }

        try {
            $redemptionService->redeemInstrument($instrument, $data['amount'] ?? null, (string) $request->user()->_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'remaining_value' => (float) $instrument->remaining_value, 'status' => $instrument->status]);
    }
}
