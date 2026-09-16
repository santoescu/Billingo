<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContract;
use App\Models\User;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Link público de referido (ver User::generateReferralCode()) -- sin auth, quien lo abre
     * puede no tener cuenta todavía. Va por usuario, no por empresa (ver
     * Company::referredByUser()). Solo guarda en sesión quién refirió (para que
     * CompanyController::store() lo ate a la empresa nueva cuando se cree) y manda a
     * registrarse o a crear empresa, según si ya tiene sesión iniciada. Si el usuario dueño del
     * código ya no tiene permiso para referir (ver User::canRefer()) -- por ejemplo, si
     * superadmin le quitó el permiso después de que compartió el link -- no se guarda nada, el
     * link simplemente no atribuye nada, sin error visible para quien lo abrió.
     */
    public function visit(Request $request, string $code)
    {
        $user = User::where('referral_code', $code)->first();

        if ($user && $user->canRefer()) {
            $request->session()->put('referral_user_id', (string) $user->_id);
        }

        return $request->user()
            ? redirect()->route('companies.create')
            : redirect()->route('register');
    }

    /**
     * Ventas (contratos) y comisiones del usuario autenticado -- lo único que ve es lo que él
     * mismo trajo (referrer_user_id), sin importar si es superadmin o un cliente normal que
     * refirió a otro negocio (mismo mecanismo para los dos, sin campos aparte); si no tiene
     * ninguna venta asignada simplemente ve la lista vacía. Si todavía no tiene permiso para
     * referir (ver User::canRefer()), la vista muestra un aviso en vez del link/tabla.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (string) $user->_id;

        if (! $user->canRefer()) {
            return view('referrals.index', [
                'canRefer' => false,
                'contracts' => collect(),
                'companyNames' => collect(),
                'totalCommission' => 0,
                'referralUrl' => null,
                'referredCompanies' => collect(),
            ]);
        }

        $contracts = CompanyContract::where('referrer_user_id', $userId)
            ->orderByDesc('starts_at')
            ->get();

        $companyIds = $contracts->pluck('company_ids')->flatten()->unique()->all();
        $companyNames = Company::whereIn('_id', $companyIds)
            ->get()
            ->keyBy(fn (Company $company) => (string) $company->_id);

        $totalCommission = $contracts->sum('commission_amount');

        $referralUrl = route('referrals.visit', $user->ensureReferralCode());

        // Empresas que se registraron con el link de este usuario -- aparte de los contratos ya
        // ganados arriba, para poder ver también las que todavía no tienen contrato (referido
        // que se registró pero superadmin todavía no le arma el contrato con la comisión).
        $referredCompanies = $user->referredCompanies()->orderByDesc('created_at')->get();

        return view('referrals.index', [
            'canRefer' => true,
            'contracts' => $contracts,
            'companyNames' => $companyNames,
            'totalCommission' => $totalCommission,
            'referralUrl' => $referralUrl,
            'referredCompanies' => $referredCompanies,
        ]);
    }
}
