<?php

namespace App\Http\Controllers;

use App\Models\CompanyMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyMemberController extends Controller
{
    /**
     * Listar los miembros de la empresa activa y sus roles por módulo.
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $memberships = CompanyMember::where('company_id', (string) $company->_id)->get();
        $users = User::whereIn('_id', $memberships->pluck('user_id')->all())
            ->get()
            ->keyBy(fn ($user) => (string) $user->_id);

        $members = $memberships
            ->map(function ($membership) use ($users) {
                $user = $users->get((string) $membership->user_id);

                if (! $user) {
                    return null;
                }

                $user->membership = $membership;

                return $user;
            })
            ->filter()
            ->sortBy('name')
            ->values();

        return view('companies.members', compact('company', 'members'));
    }

    /**
     * Agregar un usuario ya registrado (por email) con roles por módulo.
     */
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);

        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => __('There is no registered user with that email.')])->withInput();
        }

        $exists = CompanyMember::where('company_id', (string) $company->_id)
            ->where('user_id', (string) $user->_id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => __('This user already belongs to the company.')])->withInput();
        }

        $modules = $this->validatedModules($request, $company);

        CompanyMember::create([
            'company_id' => (string) $company->_id,
            'user_id' => (string) $user->_id,
            'role' => null,
            'modules' => $modules,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Member')]),
        ]);

        return redirect()->route('companies.members.index');
    }

    /**
     * Actualizar los roles por módulo de un miembro (el propietario no se modifica aquí).
     */
    public function update(Request $request, string $userId)
    {
        $company = $this->currentCompany($request);

        $membership = CompanyMember::where('company_id', (string) $company->_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($membership->role === 'owner') {
            abort(403, __('The owner role cannot be changed.'));
        }

        $modules = $this->validatedModules($request, $company);

        $membership->update(['modules' => $modules]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Member')]),
        ]);

        return redirect()->route('companies.members.index');
    }

    /**
     * Quitar a un miembro de la empresa (el propietario no se puede eliminar).
     */
    public function destroy(Request $request, string $userId)
    {
        $company = $this->currentCompany($request);

        $membership = CompanyMember::where('company_id', (string) $company->_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($membership->role === 'owner') {
            abort(403, __('The owner cannot be removed from the company.'));
        }

        $membership->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Member')]),
        ]);

        return redirect()->route('companies.members.index');
    }

    /**
     * Valida el arreglo `modules[modulo] = rol` contra los módulos activos
     * de la compañía y los roles permitidos en cada uno, descartando los
     * módulos sin rol seleccionado. Un miembro puede quedar sin ningún
     * módulo asignado (por ejemplo, si la empresa todavía no tiene módulos
     * activos) y simplemente no tendrá acceso a nada hasta que se le asigne uno.
     */
    private function validatedModules(Request $request, $company): array
    {
        $input = (array) $request->input('modules', []);
        $activeModules = $company->modules ?? [];
        $catalog = config('modules');

        $modules = collect($input)
            ->only($activeModules)
            ->filter(fn ($role) => filled($role))
            ->map(function ($role, $moduleKey) use ($catalog) {
                if (! in_array($role, $catalog[$moduleKey]['roles'] ?? [], true)) {
                    throw ValidationException::withMessages([
                        'modules' => __('Invalid role for module :module.', ['module' => $catalog[$moduleKey]['name'] ?? $moduleKey]),
                    ]);
                }

                return ['module' => $moduleKey, 'role' => $role];
            })
            ->values();

        return $modules->all();
    }
}
