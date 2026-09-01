<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContract;
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
        $companies = Company::all();

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

        // Los vigentes primero (para no tener que buscarlos entre muchos histórios), y dentro
        // de cada grupo (vigentes / no vigentes) el más reciente primero.
        $contracts = CompanyContract::where('company_ids', $companyId)
            ->orderByDesc('starts_at')
            ->get()
            ->sortByDesc(fn (CompanyContract $contract) => $contract->isWithinDateRange())
            ->values();

        // Para el selector de "empresas asociadas" al crear/editar un contrato (un mismo
        // contrato puede cubrir varias empresas del mismo cliente, ver CompanyContract).
        $otherCompanies = Company::where('_id', '!=', $companyId)->orderBy('name')->get();

        return view('admin.company-edit', compact('company', 'members', 'contracts', 'otherCompanies'));
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
     * Crear un nuevo contrato para la empresa (no reemplaza los anteriores: quedan como
     * histórico). El vigente lo decide Company::activeContract() según las fechas.
     */
    public function storeContract(Request $request, string $companyId)
    {
        $company = Company::findOrFail($companyId);

        $data = $this->validatedContractData($request, $company);

        CompanyContract::create([
            'company_ids' => $this->resolveContractCompanyIds($companyId, $data),
            'price' => $data['price'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'unlimited' => (bool) ($data['unlimited'] ?? false),
            'modules' => array_values($data['modules']),
            'quota_mode' => $data['quota_mode'],
            'renewal_type' => $data['renewal_type'],
            'period_started_at' => now(),
            'shared_limit' => $data['shared_limit'] ?? null,
            'shared_used' => 0,
            'invoicing_limit' => $data['invoicing_limit'] ?? null,
            'invoicing_used' => 0,
            'pos_limit' => $data['pos_limit'] ?? null,
            'pos_used' => 0,
            'cotizaciones_limit' => $data['cotizaciones_limit'] ?? null,
            'cotizaciones_used' => 0,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Contract')]),
        ]);

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Editar un contrato existente. No toca los contadores de consumo (*_used) -- solo los
     * límites/fechas/modo; si el superadmin cambia el modo de cupo, el consumo ya acumulado
     * queda tal cual quedó bajo el modo anterior.
     */
    public function updateContract(Request $request, string $companyId, string $contractId)
    {
        $contract = CompanyContract::where('company_ids', $companyId)->where('_id', $contractId)->firstOrFail();
        $company = Company::findOrFail($companyId);

        $data = $this->validatedContractData($request, $company);

        $contract->update([
            'company_ids' => $this->resolveContractCompanyIds($companyId, $data),
            'price' => $data['price'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'unlimited' => (bool) ($data['unlimited'] ?? false),
            'modules' => array_values($data['modules']),
            'quota_mode' => $data['quota_mode'],
            'renewal_type' => $data['renewal_type'],
            'shared_limit' => $data['shared_limit'] ?? null,
            'invoicing_limit' => $data['invoicing_limit'] ?? null,
            'pos_limit' => $data['pos_limit'] ?? null,
            'cotizaciones_limit' => $data['cotizaciones_limit'] ?? null,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Contract')]),
        ]);

        return redirect()->route('admin.companies.edit', $companyId);
    }

    /**
     * Valida el formulario de crear/editar contrato (mismas reglas en ambos casos). "modules"
     * son los módulos que este contrato específicamente cubre -- solo puede cubrir módulos que
     * la empresa tenga activos, y determina contra cuál contrato se descuenta cada documento
     * (Company::activeContractFor()), así dos contratos vigentes al mismo tiempo no chocan
     * mientras cubran módulos distintos.
     */
    private function validatedContractData(Request $request, Company $company): array
    {
        // Los campos opcionales llegan como string vacío (no ausentes) cuando el usuario los
        // deja en blanco -- 'nullable' no los convierte a null por sí solo, solo se salta el
        // resto de reglas, así que sin este paso "date"/"integer" fallarían o, peor, se
        // guardaría '' tal cual (Carbon la interpreta como "ahora").
        $request->merge(collect($request->only(['ends_at', 'shared_limit', 'invoicing_limit', 'pos_limit', 'cotizaciones_limit']))
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all());

        $companyQuotaModules = array_values(array_intersect($company->modules ?? [], CompanyContract::QUOTA_MODULES));

        return $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'unlimited' => ['nullable', 'boolean'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'in:' . implode(',', $companyQuotaModules)],
            'quota_mode' => ['required', 'in:' . CompanyContract::QUOTA_MODE_PER_MODULE . ',' . CompanyContract::QUOTA_MODE_SHARED],
            'renewal_type' => ['required', 'in:' . CompanyContract::RENEWAL_LIFETIME . ',' . CompanyContract::RENEWAL_MONTHLY],
            'shared_limit' => ['nullable', 'integer', 'min:1'],
            'invoicing_limit' => ['nullable', 'integer', 'min:1'],
            'pos_limit' => ['nullable', 'integer', 'min:1'],
            'cotizaciones_limit' => ['nullable', 'integer', 'min:1'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['string'],
        ]);
    }

    /**
     * @param  string  $companyId  Empresa desde cuya pantalla se creó/editó el contrato -- siempre queda incluida, sin importar el checkbox.
     * @param  array  $data  Datos ya validados de validatedContractData(), incluye "company_ids" si el superadmin marcó otras empresas para compartir el contrato.
     * @return array<int, string> IDs únicos y reales de todas las empresas que van a compartir este contrato -- cualquier id que no exista de verdad (manipulado a mano en el request) se descarta en silencio.
     */
    private function resolveContractCompanyIds(string $companyId, array $data): array
    {
        $candidateIds = collect($data['company_ids'] ?? [])
            ->push($companyId)
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        // "->pluck('_id')" directo en el query builder de Mongo devuelve null (no resuelve el
        // campo "_id" sin pasar por el modelo) -- por eso se trae primero la colección de
        // modelos con get() y se le hace pluck() encima, no al query builder.
        return Company::whereIn('_id', $candidateIds->all())
            ->get()
            ->pluck('_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Borrar un contrato del histórico de la empresa.
     */
    public function destroyContract(string $companyId, string $contractId)
    {
        CompanyContract::where('company_ids', $companyId)->where('_id', $contractId)->firstOrFail()->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Contract')]),
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
            ->filter(fn ($role) => filled($role) && $role !== 'none')
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
        $users = User::all();

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
