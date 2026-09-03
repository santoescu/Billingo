<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CompanyMember;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyActivityLogController extends Controller
{
    /**
     * Registro de actividad de la empresa activa: qué creó/editó/borró
     * cada usuario dentro de ella (catálogo, resoluciones, certificados,
     * miembros, etc. -- ver App\Models\Concerns\Auditable). Solo lo puede
     * ver el 'owner' -- ni siquiera un administrador de módulo, a
     * diferencia de otras pantallas de empresa (ver EnsureCompanyOwner,
     * que sí los deja pasar).
     */
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        abort_unless(session('selected_company.role') === 'owner', 403);

        $query = ActivityLog::where('company_id', (string) $company->_id);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('model')) {
            $query->where('model', $request->input('model'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($request->input('to'))->endOfDay());
        }

        $logs = $query->orderByDesc('created_at')->limit(500)->get();

        $memberships = CompanyMember::where('company_id', (string) $company->_id)->get();
        $members = User::whereIn('_id', $memberships->pluck('user_id')->all())->orderBy('name')->get();

        // Los logs solo guardan "user_id" (los usuarios nunca se borran, así
        // que no hace falta guardar también el nombre en cada entrada) --
        // este mapa resuelve el nombre a mostrar sin una consulta por fila.
        $userNames = User::whereIn('_id', $logs->pluck('user_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn ($user) => (string) $user->_id);

        return view('companies.activity-log', [
            'company' => $company,
            'logs' => $logs,
            'members' => $members,
            'userNames' => $userNames,
            'filters' => $request->only(['user_id', 'model', 'action', 'from', 'to']),
        ]);
    }
}
