<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContract;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Ventas (contratos) y comisiones del vendedor autenticado -- lo único
     * que ve es lo que él mismo trajo (referrer_user_id), sin importar si
     * es 'referrer' o superadmin; si no tiene ninguna venta asignada
     * simplemente ve la lista vacía.
     */
    public function index(Request $request)
    {
        $userId = (string) $request->user()->_id;

        $contracts = CompanyContract::where('referrer_user_id', $userId)
            ->orderByDesc('starts_at')
            ->get();

        $companyIds = $contracts->pluck('company_ids')->flatten()->unique()->all();
        $companyNames = Company::whereIn('_id', $companyIds)
            ->get()
            ->keyBy(fn (Company $company) => (string) $company->_id);

        $totalCommission = $contracts->sum('commission_amount');

        return view('referrals.index', compact('contracts', 'companyNames', 'totalCommission'));
    }
}
