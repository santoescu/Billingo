<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyProgram;
use App\Models\ThirdParty;
use App\Services\Loyalty\LoyaltyEnrollmentService;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Http\Request;
use RuntimeException;

class LoyaltyCustomerController extends Controller
{
    /**
     * $customers vacío a propósito: la tabla se llena por AJAX (ver data())
     * -- mismo patrón que QuotationController::index()/data().
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);
        $program = $company->loyaltyProgram;

        return view('loyalty.customers.index', [
            'company' => $company,
            'program' => $program,
        ]);
    }

    public function data(Request $request)
    {
        $company = $this->currentCompany($request);

        $customers = $company->loyaltyCustomers()->orderByDesc('created_at')->get();

        $rows = $customers->map(fn (LoyaltyCustomer $customer) => [
            'id' => (string) $customer->_id,
            'identificacion' => $customer->identificacion,
            'phone' => $customer->phone,
            'name' => $customer->name,
            'points_balance' => (float) $customer->points_balance,
            'stamps_count' => (int) $customer->stamps_count,
            'cashback_balance' => (float) $customer->cashback_balance,
            'current_tier_name' => $customer->current_tier_name,
            'enrolled_at' => $customer->enrolled_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'urls' => ['show' => route('loyalty.customers.show', $customer->_id)],
        ]);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Alta desde el módulo -- a propósito NO crea un ThirdParty nuevo: el
     * cliente ya debe existir en la lista de Clientes de la empresa (se
     * busca por identificación, ver clientSearch()); si no existe, se le
     * pide crearlo primero ahí, con todos sus datos completos (dirección,
     * responsabilidades fiscales, etc.), no una versión "pobre" solo para
     * fidelización.
     */
    public function store(Request $request, LoyaltyEnrollmentService $enrollmentService)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'identificacion' => 'required|string|max:20',
        ]);

        $cliente = $company->clients()->where('identificacion', $data['identificacion'])->first();

        if (! $cliente) {
            return response()->json(['message' => __('This client does not exist yet. Create it first from Clients.')], 422);
        }

        $customer = $enrollmentService->enrollFromThirdParty($company, $cliente, 'manual');

        return response()->json(['id' => (string) $customer->_id, 'redirect' => route('loyalty.customers.show', $customer->_id)]);
    }

    /**
     * Búsqueda por identificación para el cajero en el mostrador -- devuelve
     * "found: false" en vez de 404 para que el frontend pueda ofrecer
     * "inscribir con esta identificación" sin tratarlo como un error.
     */
    public function lookup(Request $request)
    {
        $company = $this->currentCompany($request);
        $identificacion = (string) $request->query('identificacion', '');

        $cliente = $identificacion !== ''
            ? ThirdParty::where('company_id', (string) $company->_id)->where('identificacion', $identificacion)->first()
            : null;

        $customer = $cliente ? LoyaltyCustomer::findByClienteId($company, (string) $cliente->_id) : null;

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'id' => (string) $customer->_id, 'url' => route('loyalty.customers.show', $customer->_id)]);
    }

    /**
     * Buscar entre los clientes ya registrados de la empresa (mismo
     * DocumentoEmitidoController::clientSearch()) -- para "Agregar cliente"
     * primero se busca acá; solo si no aparece nadie se ofrece crear uno
     * nuevo con los campos de abajo.
     */
    public function clientSearch(Request $request)
    {
        $company = $this->currentCompany($request);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['clients' => []]);
        }

        $clients = $company->clients()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%')
                    ->orWhere('identificacion', 'like', '%' . $query . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'clients' => $clients->map(function (ThirdParty $cliente) use ($company) {
                $enrolled = LoyaltyCustomer::findByClienteId($company, (string) $cliente->_id);

                return [
                    'id' => (string) $cliente->_id,
                    'identification_type' => $cliente->identification_type,
                    'identificacion' => $cliente->identificacion,
                    'name' => $cliente->name,
                    'phone' => $cliente->phone,
                    'email' => $cliente->email,
                    'enrolled' => (bool) $enrolled,
                    'url' => $enrolled ? route('loyalty.customers.show', $enrolled->_id) : null,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, string $loyaltyCustomer)
    {
        $company = $this->currentCompany($request);
        $customer = $company->loyaltyCustomers()->where('_id', $loyaltyCustomer)->first();

        abort_unless($customer, 404);

        $program = $company->loyaltyProgram;
        $transactions = $customer->transactions()->orderByDesc('created_at')->limit(20)->get();
        $instruments = $customer->instruments()->orderByDesc('created_at')->get();

        return view('loyalty.customers.show', compact('company', 'customer', 'program', 'transactions', 'instruments'));
    }

    public function adjust(Request $request, string $loyaltyCustomer, LoyaltyRedemptionService $redemptionService)
    {
        $company = $this->currentCompany($request);
        $customer = $company->loyaltyCustomers()->where('_id', $loyaltyCustomer)->first();

        abort_unless($customer, 404);

        $data = $request->validate([
            'mechanic' => 'required|in:' . LoyaltyProgram::MECHANIC_POINTS . ',' . LoyaltyProgram::MECHANIC_STAMPS . ',' . LoyaltyProgram::MECHANIC_CASHBACK,
            'amount' => 'required|numeric',
            'note' => 'required|string|max:300',
        ]);

        try {
            $redemptionService->manualAdjust($customer, $data['mechanic'], (float) $data['amount'], $data['note'], (string) $request->user()->_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function redeem(Request $request, string $loyaltyCustomer, LoyaltyRedemptionService $redemptionService)
    {
        $company = $this->currentCompany($request);
        $customer = $company->loyaltyCustomers()->where('_id', $loyaltyCustomer)->first();

        abort_unless($customer, 404);

        $program = $company->loyaltyProgram;
        abort_unless($program, 404);

        $data = $request->validate([
            'mechanic' => 'required|in:' . LoyaltyProgram::MECHANIC_POINTS . ',' . LoyaltyProgram::MECHANIC_STAMPS . ',' . LoyaltyProgram::MECHANIC_CASHBACK,
            'amount' => 'nullable|numeric',
        ]);

        try {
            match ($data['mechanic']) {
                LoyaltyProgram::MECHANIC_POINTS => $redemptionService->redeemPoints($program, $customer, (float) $data['amount'], (string) $request->user()->_id),
                LoyaltyProgram::MECHANIC_STAMPS => $redemptionService->redeemStamps($program, $customer, (string) $request->user()->_id),
                LoyaltyProgram::MECHANIC_CASHBACK => $redemptionService->redeemCashback($program, $customer, (float) $data['amount'], (string) $request->user()->_id),
            };
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $customer->refresh();

        return response()->json([
            'success' => true,
            'points_balance' => (float) $customer->points_balance,
            'stamps_count' => (int) $customer->stamps_count,
            'cashback_balance' => (float) $customer->cashback_balance,
        ]);
    }
}
