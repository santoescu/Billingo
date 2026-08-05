<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Models\ThirdParty;
use Illuminate\Http\Request;

class ThirdPartyController extends Controller
{
    /**
     * Listar los terceros de la empresa activa con el rol correspondiente
     * ('cliente' para Emisión, 'proveedor' para Recepción).
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);
        $role = $request->route('role');

        $thirdParties = $company->thirdParties()->withRole($role)->orderBy('name')->get();

        return view('third-parties.index', [
            'thirdParties' => $thirdParties,
            'role' => $role,
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
            'departments' => Department::orderBy('descripcion')->get(),
        ]);
    }

    /**
     * Crea un tercero nuevo, o si ya existe uno con la misma identificación
     * en esta empresa (registrado desde el otro módulo), simplemente le
     * agrega el rol correspondiente sin sobreescribir sus datos existentes.
     */
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);
        $role = $request->route('role');
        $data = $this->validatedData($request);

        $existing = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identification_type', $data['identification_type'])
            ->where('identificacion', $data['identificacion'])
            ->first();

        if ($existing) {
            $roles = collect($existing->roles ?? [])->push($role)->unique()->values()->all();
            $existing->update(['roles' => $roles]);

            session()->flash('toast', [
                'type' => 'success',
                'message' => __('This third party already existed for this company; the :role role was added.', ['role' => $role]),
            ]);
        } else {
            $data['company_id'] = (string) $company->_id;
            $data['roles'] = [$role];

            ThirdParty::create($data);

            session()->flash('toast', [
                'type' => 'success',
                'message' => __('Created :name', ['name' => __('Third party')]),
            ]);
        }

        return redirect()->route($this->routeName($role, 'index'));
    }

    public function update(Request $request, string $thirdParty)
    {
        $company = $this->currentCompany($request);
        $role = $request->route('role');

        $thirdParty = ThirdParty::where('company_id', (string) $company->_id)->findOrFail($thirdParty);
        $data = $this->validatedData($request);

        $thirdParty->update($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Third party')]),
        ]);

        return redirect()->route($this->routeName($role, 'index'));
    }

    public function destroy(Request $request, string $thirdParty)
    {
        $company = $this->currentCompany($request);
        $role = $request->route('role');

        $thirdParty = ThirdParty::where('company_id', (string) $company->_id)->findOrFail($thirdParty);

        // Solo quitamos el rol de este módulo; si el tercero sigue teniendo
        // otro rol (ej. también es proveedor), el registro se conserva.
        $roles = collect($thirdParty->roles ?? [])->reject(fn ($r) => $r === $role)->values()->all();

        if (empty($roles)) {
            $thirdParty->delete();
        } else {
            $thirdParty->update(['roles' => $roles]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Third party')]),
        ]);

        return redirect()->route($this->routeName($role, 'index'));
    }

    private function routeName(string $role, string $action): string
    {
        return ($role === 'cliente' ? 'clients.' : 'providers.') . $action;
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'identification_type' => 'nullable|string|in:11,12,13,21,22,31,41,42,47,48,50,91',
            'identificacion' => 'required|string|max:20',
            'dv' => 'nullable|string|max:1',
            'person_type' => 'nullable|string|in:1,2',
            'fiscal_responsibilities' => 'nullable|array',
            'fiscal_responsibilities.*' => 'string|max:20',
            'address' => 'nullable|string|max:255',
            'department_code' => 'nullable|string|max:10',
            'city_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $data['fiscal_responsibilities'] = implode(';', $data['fiscal_responsibilities'] ?? []);

        // El dígito de verificación solo aplica a NIT (tipo 31) y siempre se
        // calcula en el servidor, nunca se confía en lo que mande el cliente.
        $data['dv'] = ($data['identification_type'] ?? null) === '31'
            ? Company::calculateVerificationDigit($data['identificacion'])
            : null;

        return $data;
    }
}
