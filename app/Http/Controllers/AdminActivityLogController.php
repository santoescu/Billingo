<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    /**
     * Registro de actividad de TODAS las empresas, para el superadmin --
     * mismo dato que ve cada 'owner' en la suya (companies.activity-log),
     * pero sin filtrar por company_id y con filtros extra por empresa y
     * usuario (ver AdminSupportTicketController::index() para el mismo
     * patrón de filtros por query string).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

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

        $companyNames = Company::whereIn('_id', $logs->pluck('company_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn ($company) => (string) $company->_id);

        // Para el selector "Usuario", solo los que de verdad tienen alguna
        // entrada en el log (no todos los usuarios del sistema) -- evita
        // cargar de una toda la tabla de usuarios para un dropdown.
        $activeUserIds = ActivityLog::whereNotNull('user_id')->distinct()->pluck('user_id')->all();
        $users = User::whereIn('_id', $activeUserIds)->orderBy('name')->get();

        $userNames = User::whereIn('_id', $logs->pluck('user_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn ($user) => (string) $user->_id);

        return view('admin.activity-log', [
            'logs' => $logs,
            'companyNames' => $companyNames,
            'userNames' => $userNames,
            'companies' => Company::orderBy('name')->get(),
            'users' => $users,
            'filters' => $request->only(['company_id', 'user_id', 'model', 'action', 'from', 'to']),
        ]);
    }
}
