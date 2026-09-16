<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmailLog;

class AdminEmailEngagementController extends Controller
{
    /**
     * Métricas de alcance de marca para el superadmin -- cuántos correos que Billingo mandó
     * (a nombre de las empresas) se abrieron, y cuántos tuvieron clic en el link "Enviado por
     * Billingo" del pie (ver document-issued-email.blade.php). No tiene sentido mostrarle esto a
     * cada empresa: a ellas les importa si SU cliente vio la factura, no si le dio clic a un
     * link nuestro -- esa métrica es nuestra, no de ellas.
     */
    public function index()
    {
        $totalSent = EmailLog::whereNotNull('sent_at')->count();
        $totalDelivered = EmailLog::whereNotNull('delivered_at')->count();
        $totalOpened = EmailLog::whereNotNull('opened_at')->count();
        $totalClicked = EmailLog::whereNotNull('clicked_at')->count();

        $recentClicks = EmailLog::whereNotNull('clicked_at')
            ->orderByDesc('clicked_at')
            ->limit(100)
            ->get();

        $companyNames = Company::whereIn('_id', $recentClicks->pluck('company_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn ($company) => (string) $company->_id);

        return view('admin.email-engagement', [
            'totalSent' => $totalSent,
            'totalDelivered' => $totalDelivered,
            'totalOpened' => $totalOpened,
            'totalClicked' => $totalClicked,
            'recentClicks' => $recentClicks,
            'companyNames' => $companyNames,
        ]);
    }
}
