<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Empresa activa (guardada en sesión al seleccionarla desde el dashboard),
     * verificando que el usuario autenticado siga perteneciendo a ella.
     * La membresía (rol + módulos) queda disponible en $company->membership.
     */
    protected function currentCompany(Request $request): Company
    {
        $companyId = session('selected_company.id');

        abort_unless($companyId, 404);

        $membership = CompanyMember::where('company_id', $companyId)
            ->where('user_id', $request->user()->_id)
            ->first();

        abort_unless($membership, 404);

        $company = Company::findOrFail($companyId);
        $company->membership = $membership;

        return $company;
    }

    /**
     * Verifica que el usuario autenticado sea 'owner' o 'administrador' de
     * algún módulo de la empresa $id (sin importar cuál sea la empresa
     * "activa" en sesión), para acciones lanzadas desde su propio modal de
     * edición -- p. ej. la habilitación ante la DIAN.
     */
    protected function authorizeCompanyAdmin(Request $request, string $id): Company
    {
        $company = Company::findOrFail($id);

        $membership = CompanyMember::where('company_id', $id)
            ->where('user_id', (string) $request->user()->_id)
            ->first();

        abort_unless($membership, 404);

        if (! User::hasCompanyAdminAccess($membership->role, $membership->modules ?? [])) {
            abort(403);
        }

        return $company;
    }
}
