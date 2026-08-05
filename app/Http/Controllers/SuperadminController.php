<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SuperadminController extends Controller
{
    /**
     * Listar todas las empresas del sistema, sin importar quién sea el dueño.
     */
    public function companies()
    {
        $companies = Company::orderBy('name')->get();

        $owners = CompanyMember::whereIn('company_id', $companies->pluck('_id')->map(fn ($id) => (string) $id)->all())
            ->where('role', 'owner')
            ->get();

        $ownerNames = User::whereIn('_id', $owners->pluck('user_id')->all())
            ->get()
            ->keyBy(fn ($user) => (string) $user->_id);

        $companies = $companies->map(function ($company) use ($owners, $ownerNames) {
            $ownerMembership = $owners->first(fn ($m) => (string) $m->company_id === (string) $company->_id);
            $company->owner_name = $ownerMembership ? $ownerNames->get((string) $ownerMembership->user_id)?->name : null;

            return $company;
        });

        return view('admin.companies', compact('companies'));
    }

    /**
     * Editar una empresa como superadmin: módulos activos y sus miembros
     * con sus roles por módulo (sin tocar los datos propios de la empresa).
     */
    public function editCompany(string $companyId)
    {
        $company = Company::findOrFail($companyId);

        $memberships = CompanyMember::where('company_id', $companyId)->get();
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

        return view('admin.company-edit', compact('company', 'members'));
    }

    /**
     * Actualizar los módulos activos de una empresa.
     */
    public function updateModules(Request $request, string $companyId)
    {
        $company = Company::findOrFail($companyId);

        $data = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'string|in:' . implode(',', array_keys(config('modules'))),
        ]);

        $company->update(['modules' => array_values($data['modules'] ?? [])]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Company')]),
        ]);

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Agregar un usuario ya registrado (por email) a una empresa, con roles por módulo.
     */
    public function storeMember(Request $request, string $companyId)
    {
        $company = Company::findOrFail($companyId);

        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => __('There is no registered user with that email.')])->withInput();
        }

        $exists = CompanyMember::where('company_id', $companyId)
            ->where('user_id', (string) $user->_id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => __('This user already belongs to the company.')])->withInput();
        }

        $modules = $this->validatedModulesForCompany($request, $company);

        CompanyMember::create([
            'company_id' => $companyId,
            'user_id' => (string) $user->_id,
            'role' => null,
            'modules' => $modules,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Member')]),
        ]);

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Actualizar los roles por módulo de un miembro de la empresa.
     */
    public function updateMember(Request $request, string $companyId, string $userId)
    {
        $company = Company::findOrFail($companyId);

        $membership = CompanyMember::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($membership->role === 'owner') {
            abort(403, __('The owner role cannot be changed.'));
        }

        $modules = $this->validatedModulesForCompany($request, $company);

        $membership->update(['modules' => $modules]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Member')]),
        ]);

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Quitar a un miembro de la empresa (el propietario no se puede eliminar).
     */
    public function destroyMember(string $companyId, string $userId)
    {
        $membership = CompanyMember::where('company_id', $companyId)
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

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Valida el arreglo `modules[modulo] = rol` contra los módulos activos
     * de la compañía y los roles permitidos en cada uno.
     */
    private function validatedModulesForCompany(Request $request, Company $company): array
    {
        $input = (array) $request->input('modules', []);
        $activeModules = $company->modules ?? [];
        $catalog = config('modules');

        return collect($input)
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
            ->values()
            ->all();
    }

    /**
     * Listar todos los usuarios del sistema, con las empresas a las que pertenece cada uno.
     */
    public function users()
    {
        $users = User::orderBy('name')->get();

        $memberships = CompanyMember::whereIn('user_id', $users->pluck('_id')->map(fn ($id) => (string) $id)->all())->get();
        $companyNames = Company::whereIn('_id', $memberships->pluck('company_id')->unique()->all())
            ->get()
            ->keyBy(fn ($company) => (string) $company->_id);

        $users = $users->map(function ($user) use ($memberships, $companyNames) {
            $user->company_names = $memberships
                ->filter(fn ($m) => (string) $m->user_id === (string) $user->_id)
                ->map(fn ($m) => $companyNames->get((string) $m->company_id)?->name)
                ->filter()
                ->values();

            return $user;
        });

        return view('admin.users', compact('users'));
    }

    /**
     * Formulario para enviar una notificación a todos los usuarios o a
     * algunos en específico.
     */
    public function notificationsCreate()
    {
        $users = User::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        return view('admin.notifications-create', compact('users', 'companies'));
    }

    /**
     * Envía la notificación: crea un documento por cada destinatario para
     * poder llevar el estado de leído/no leído de forma individual.
     */
    public function notificationsStore(Request $request)
    {
        $data = $request->validate([
            'recipients' => 'required|in:all,specific,companies',
            'user_ids' => 'required_if:recipients,specific|array',
            'user_ids.*' => 'string',
            'company_ids' => 'required_if:recipients,companies|array',
            'company_ids.*' => 'string',
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:1000',
            'url' => 'nullable|url',
        ]);

        $recipientIds = match ($data['recipients']) {
            'all' => User::pluck('id')->all(),
            'specific' => $data['user_ids'],
            'companies' => CompanyMember::whereIn('company_id', $data['company_ids'])
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all(),
        };

        foreach ($recipientIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'body' => $data['body'],
                'url' => $data['url'] ?? null,
                'sender_id' => (string) $request->user()->_id,
            ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Notification sent to :count user(s).', ['count' => count($recipientIds)]),
        ]);

        return redirect()->route('admin.notifications.create');
    }

    /**
     * Otorgar o quitar el rol de superadmin a otro usuario.
     * Un superadmin no puede quitarse el rol a sí mismo (evita quedar bloqueado).
     */
    public function toggleSuperadmin(Request $request, string $userId)
    {
        if ($userId === (string) $request->user()->_id) {
            abort(403, __('You cannot change your own superadmin status.'));
        }

        $user = User::findOrFail($userId);
        $user->update(['role' => $user->isGlobalAdmin() ? null : 'superadmin']);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('User')]),
        ]);

        return redirect()->route('admin.users');
    }
}
