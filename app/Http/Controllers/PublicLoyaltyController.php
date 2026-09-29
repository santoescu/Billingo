<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Models\LoyaltyCustomer;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyEnrollmentService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

/**
 * Rutas públicas sin autenticación (ver PublicCatalogController como mismo
 * criterio): el link/token de la URL resuelve la empresa y/o el cliente,
 * nunca la sesión -- un cliente final de fidelización nunca tiene cuenta en
 * Billingo.
 */
class PublicLoyaltyController extends Controller
{
    private function resolveProgram(string $token): LoyaltyProgram
    {
        $program = LoyaltyProgram::findByEnrollmentToken($token);

        abort_unless($program, 404);

        return $program;
    }

    public function showEnrollForm(string $token)
    {
        $program = $this->resolveProgram($token);

        if ($program->status !== LoyaltyProgram::STATUS_ACTIVE) {
            return view('public.unavailable');
        }

        return view('public.loyalty.enroll', [
            'token' => $token,
            'program' => $program,
            'departments' => Department::orderBy('descripcion')->get(),
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
        ]);
    }

    /**
     * Busca por identificación antes de pedir todo el formulario -- mismo
     * criterio que PublicCatalogController::findClient(): si el cliente ya
     * está inscrito, se le manda directo a su tarjeta sin volver a llenar
     * nada; solo si no existe se muestran los campos para crearlo.
     */
    public function lookupByIdentificacion(Request $request, string $token)
    {
        $program = $this->resolveProgram($token);

        if ($program->status !== LoyaltyProgram::STATUS_ACTIVE) {
            return response()->json(['found' => false]);
        }

        $identificacion = trim((string) $request->query('identificacion', ''));
        if ($identificacion === '') {
            return response()->json(['found' => false]);
        }

        $cliente = $program->company->clients()->where('identificacion', $identificacion)->first();
        $customer = $cliente ? LoyaltyCustomer::findByClienteId($program->company, (string) $cliente->_id) : null;

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'url' => route('public.loyalty.card.show', $customer->public_token)]);
    }

    /**
     * Mismos campos que ThirdPartyController::store() (el cliente queda
     * completo, no una versión "pobre" solo para fidelización) -- igual que
     * PublicCatalogController::storeClient(), sin el lookup automático
     * contra la DIAN porque esa consulta requiere sesión de empresa
     * autenticada y acá no hay una.
     */
    public function enroll(Request $request, string $token, LoyaltyEnrollmentService $enrollmentService)
    {
        $program = $this->resolveProgram($token);

        if ($program->status !== LoyaltyProgram::STATUS_ACTIVE) {
            return view('public.unavailable');
        }

        $data = $request->validate([
            'identification_type' => 'required|string|in:11,12,13,21,22,31,41,42,47,48,50,91',
            'identificacion' => 'required|string|max:20',
            'name' => 'required|string|max:150',
            'person_type' => 'nullable|string|in:1,2',
            'fiscal_responsibilities' => 'nullable|array',
            'fiscal_responsibilities.*' => 'string|max:20',
            'address' => 'nullable|string|max:255',
            'department_code' => 'nullable|string|max:10',
            'city_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $customer = $enrollmentService->enroll($program->company, $data, 'public');

        return redirect()->route('public.loyalty.card.show', $customer->public_token);
    }

    public function showCard(string $publicToken)
    {
        $customer = LoyaltyCustomer::findByPublicToken($publicToken);

        abort_unless($customer, 404);

        $program = $customer->company->loyaltyProgram;
        $instruments = $customer->instruments()->where('status', 'active')->get();

        $qrCode = new QrCode(route('public.loyalty.card.show', $publicToken));
        $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();

        return view('public.loyalty.card', compact('customer', 'program', 'instruments', 'qrDataUri'));
    }
}
