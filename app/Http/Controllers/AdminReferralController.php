<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContract;
use App\Models\User;

class AdminReferralController extends Controller
{
    /**
     * Vista agregada del programa de referidos para el superadmin -- cada usuario ve su propio
     * detalle en /referrals (ver ReferralController), esto es el resumen de todos juntos: quién
     * refiere de verdad, cuántos de sus referidos terminaron con contrato, y cuánto se ha pagado
     * en comisión y dado en descuento por esta vía.
     *
     * "Empresa referida por alguien" tiene DOS señales independientes, hay que juntar las dos:
     * (a) Company.referred_by_user_id -- se registró de verdad por el link público (ver
     *     ReferralController::visit(), Company::referredByUser()).
     * (b) CompanyContract.referrer_user_id -- el superadmin le atribuyó la venta a mano al crear
     *     el contrato, sin que la empresa haya pasado nunca por el link (así funcionaba el
     *     "vendedor" interno antes de unificarse con esto). Una empresa puede tener (b) sin
     *     tener (a) -- por eso "empresas referidas" salía en 0 aunque ya hubiera comisión
     *     pagada: solo se estaba contando (a).
     */
    public function index()
    {
        $companiesLinkedByUser = Company::whereNotNull('referred_by_user_id')->get()
            ->groupBy(fn (Company $company) => (string) $company->referred_by_user_id);

        $referrerContracts = CompanyContract::whereNotNull('referrer_user_id')->get();
        $contractsByUser = $referrerContracts->groupBy(fn (CompanyContract $contract) => (string) $contract->referrer_user_id);

        $companyIdsInContractsByUser = $contractsByUser->map(
            fn ($contracts) => $contracts->pluck('company_ids')->flatten()->unique()->all()
        );

        $userIds = collect($companiesLinkedByUser->keys())->merge($contractsByUser->keys())->unique()->values();
        $users = User::whereIn('_id', $userIds->all())->get()->keyBy(fn (User $user) => (string) $user->_id);

        $rows = $userIds->map(function (string $userId) use ($companiesLinkedByUser, $companyIdsInContractsByUser, $contractsByUser, $users) {
            $linkedCompanyIds = $companiesLinkedByUser->get($userId, collect())->map(fn (Company $c) => (string) $c->_id)->all();
            $contractCompanyIds = $companyIdsInContractsByUser->get($userId, []);
            $allReferredIds = array_unique(array_merge($linkedCompanyIds, $contractCompanyIds));
            $contracts = $contractsByUser->get($userId, collect());

            return [
                'name' => $users->get($userId)?->name ?? __('Unknown'),
                'referred_count' => count($allReferredIds),
                'converted_count' => count(array_intersect($allReferredIds, $contractCompanyIds)),
                'total_commission' => $contracts->sum('commission_amount'),
                'total_contracted' => $contracts->sum('net_price'),
            ];
        })->sortByDesc('total_commission')->values();

        return view('admin.referrals', [
            'totalReferred' => $rows->sum('referred_count'),
            'totalWithContract' => $rows->sum('converted_count'),
            'totalCommission' => $referrerContracts->sum('commission_amount'),
            'totalDiscountGiven' => $referrerContracts->sum('referral_discount_amount'),
            // "net_price" (ver CompanyContract::getNetPriceAttribute()) = lo que la empresa
            // realmente pagó, ya con el descuento de referido restado -- no "price" a secas, que
            // es el valor de lista antes del descuento.
            'totalContracted' => $referrerContracts->sum('net_price'),
            'rows' => $rows,
        ]);
    }
}
