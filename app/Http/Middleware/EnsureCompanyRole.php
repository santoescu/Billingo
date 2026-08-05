<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyRole
{
    /**
     * Exige que el usuario tenga uno de los roles indicados dentro de un
     * módulo (invoicing, receiving, payroll...) de la empresa activa.
     * El rol 'owner' de la compañía siempre tiene acceso total a sus
     * módulos activos, sin necesidad de asignación explícita por módulo.
     *
     * Uso: ->middleware('company.role:invoicing,administrador,vendedor')
     */
    public function handle(Request $request, Closure $next, string $module, ...$roles): Response
    {
        $companyId = session('selected_company.id');

        if (! $companyId) {
            return redirect()->route('dashboard');
        }

        $company = Company::find($companyId);

        if (! $company || ! $company->hasModule($module)) {
            abort(404);
        }

        $membership = CompanyMember::where('company_id', $companyId)
            ->where('user_id', (string) $request->user()->_id)
            ->first();

        if (! $membership) {
            abort(404);
        }

        if ($membership->role === 'owner') {
            return $next($request);
        }

        $assignment = collect($membership->modules ?? [])
            ->firstWhere('module', $module);

        if (! $assignment || ! in_array($assignment['role'] ?? null, array_filter($roles), true)) {
            abort(403);
        }

        return $next($request);
    }
}
