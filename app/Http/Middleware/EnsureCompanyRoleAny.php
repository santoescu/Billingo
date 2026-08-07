<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Como company.role (ver EnsureCompanyRole), pero para rutas que más de un
 * módulo necesita usar (p. ej. "documents" lo usan tanto facturación como
 * POS -- el POS reusa las mismas búsquedas de cliente/producto y el mismo
 * recibo/lista de documentos): basta con que el usuario tenga acceso a
 * CUALQUIERA de los módulos/roles indicados, no a todos.
 *
 * Uso: ->middleware('company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor')
 * (una "," separa cada grupo módulo:roles, un "|" separa los roles dentro de un grupo).
 */
class EnsureCompanyRoleAny
{
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $companyId = session('selected_company.id');

        if (! $companyId) {
            return redirect()->route('dashboard');
        }

        $company = Company::find($companyId);

        if (! $company) {
            abort(404);
        }

        $membership = CompanyMember::where('company_id', $companyId)
            ->where('user_id', (string) $request->user()->_id)
            ->first();

        if (! $membership) {
            abort(404);
        }

        $hasAnyModuleActive = false;

        foreach ($groups as $group) {
            [$module, $rolesRaw] = array_pad(explode(':', $group, 2), 2, '');

            if (! $company->hasModule($module)) {
                continue;
            }

            $hasAnyModuleActive = true;

            if ($membership->role === 'owner') {
                return $next($request);
            }

            $roles = array_filter(explode('|', $rolesRaw));
            $assignment = collect($membership->modules ?? [])->firstWhere('module', $module);

            if ($assignment && in_array($assignment['role'] ?? null, $roles, true)) {
                return $next($request);
            }
        }

        abort($hasAnyModuleActive ? 403 : 404);
    }
}
