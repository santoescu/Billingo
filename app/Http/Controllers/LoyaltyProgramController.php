<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyProgram;
use Illuminate\Http\Request;

class LoyaltyProgramController extends Controller
{
    /**
     * Configuración del programa -- se crea vacío (sin mecánicas activas)
     * la primera vez que se visita, para no obligar a un paso previo de
     * "crear programa" separado del de configurarlo.
     */
    public function edit(Request $request)
    {
        $company = $this->currentCompany($request);

        $program = $company->loyaltyProgram ?: LoyaltyProgram::create([
            'company_id' => (string) $company->_id,
            'name' => $company->name,
            'active_mechanics' => [],
            'status' => LoyaltyProgram::STATUS_PAUSED,
            'branding' => [],
            'settings' => [],
            'enrollment_token' => LoyaltyProgram::generateEnrollmentToken(),
        ]);

        return view('loyalty.program.edit', [
            'company' => $company,
            'program' => $program,
            'mechanics' => LoyaltyProgram::MECHANICS,
            'enrollUrl' => route('public.loyalty.enroll.show', $program->enrollment_token),
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->currentCompany($request);
        $program = $company->loyaltyProgram;

        abort_unless($program, 404);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'status' => 'required|in:' . LoyaltyProgram::STATUS_ACTIVE . ',' . LoyaltyProgram::STATUS_PAUSED,
            'active_mechanics' => 'nullable|array',
            'active_mechanics.*' => 'string|in:' . implode(',', LoyaltyProgram::MECHANICS),
            'branding.primary_color' => 'nullable|string|max:20',
            'branding.logo_url' => 'nullable|string|max:500',
            'settings' => 'nullable|array',
        ]);

        $program->update([
            'name' => $data['name'],
            'status' => $data['status'],
            'active_mechanics' => array_values($data['active_mechanics'] ?? []),
            'branding' => $data['branding'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => __('Updated :name', ['name' => __('Loyalty program')])]);

        return redirect()->route('loyalty.program.edit');
    }
}
