<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Services\Panel\PanelMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Pantalla de aterrizaje (selector/editor de empresas, sin métricas) y
 * Panel (resumen de actividad por módulo de la empresa activa) -- separados
 * en dos pantallas/rutas para no mezclar "elegir con qué empresa trabajar"
 * con "ver cómo le está yendo". El Panel usa ventanas fijas con botones
 * rápidos (hoy/semana/mes/año) en vez de un selector de rango libre --
 * siguiendo el patrón que ya usan sistemas comparables (POS/facturación):
 * rango libre queda para una futura sección de "Reportes", el panel
 * principal se mantiene a ventanas fijas para lectura rápida.
 */
class DashboardController extends Controller
{
    public function __construct(private PanelMetricsService $panelMetrics)
    {
    }

    public function index(Request $request)
    {
        $companies = $request->user()->companiesWithMembership();
        $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();
        $departments = Department::orderBy('descripcion')->get();

        return view('dashboard', compact('companies', 'fiscalResponsibilities', 'departments'));
    }

    /**
     * El Panel ya no arma la data acá -- cada tarjeta/gráfica es su propio
     * componente Livewire perezoso (ver App\Livewire\Panel\PanelWidget) que
     * consulta por su cuenta. Esta acción solo resuelve lo que la vista
     * necesita ANTES de pintar esas tarjetas: qué empresa está activa, qué
     * módulos administra el usuario, y el periodo/filtro elegidos -- todo
     * barato, sin tocar documentos_emitidos/pos/etc.
     */
    public function panel(Request $request)
    {
        $context = $this->resolvePanelContext($request);
        if ($context instanceof \Illuminate\Http\RedirectResponse) {
            return $context;
        }

        return view('panel', $context);
    }

    /**
     * El PDF sí necesita toda la data de una sola pasada (DomPDF no puede
     * esperar a componentes async), así que acá se llaman los mismos
     * métodos del servicio que usa cada widget de Livewire, uno tras otro.
     */
    public function panelPdf(Request $request)
    {
        $context = $this->resolvePanelContext($request);
        if ($context instanceof \Illuminate\Http\RedirectResponse) {
            return $context;
        }

        $panelData = $this->buildFullPanelData($context);

        $pdf = Pdf::loadView('panel-pdf', $panelData)->setPaper('letter', 'portrait');

        return $pdf->stream('panel-' . $context['period'] . '.pdf');
    }

    /**
     * @return array{company: Company, period: string, moduleFilter: string, availableModules: array<int, string>}|\Illuminate\Http\RedirectResponse
     */
    private function resolvePanelContext(Request $request)
    {
        $companyId = session('selected_company.id');
        if (! $companyId) {
            return redirect()->route('dashboard');
        }

        $company = Company::find($companyId);
        if (! $company) {
            return redirect()->route('dashboard');
        }

        $myModuleRoles = session('selected_company.modules', []);
        $isModuleAdmin = fn (string $module) => in_array($myModuleRoles[$module] ?? null, ['owner', 'administrador'], true);

        $moduleFilter = $request->query('module');
        if (! in_array($moduleFilter, PanelMetricsService::MODULES, true)) {
            $moduleFilter = 'all';
        }

        return [
            'company' => $company,
            'period' => $this->panelMetrics->resolvePeriod($request->query('period'))['key'],
            'moduleFilter' => $moduleFilter,
            'availableModules' => array_values(array_filter(PanelMetricsService::MODULES, $isModuleAdmin)),
        ];
    }

    /**
     * Arma el mismo arreglo que antes recibía panel-pdf.blade.php --
     * ninguna vista se tocó, solo el origen de los datos (ahora viene de
     * PanelMetricsService en vez de las consultas que vivían en este
     * controlador).
     *
     * @param  array{company: Company, period: string, moduleFilter: string, availableModules: array<int, string>}  $context
     * @return array<string, mixed>
     */
    private function buildFullPanelData(array $context): array
    {
        $company = $context['company'];
        $period = $this->panelMetrics->resolvePeriod($context['period']);
        $myModuleRoles = session('selected_company.modules', []);
        $isModuleAdmin = fn (string $module) => in_array($myModuleRoles[$module] ?? null, ['owner', 'administrador'], true);
        $moduleFilter = $context['moduleFilter'];
        $showsModule = fn (string $module) => $isModuleAdmin($module) && ($moduleFilter === 'all' || $moduleFilter === $module);
        $modules = array_values(array_filter(PanelMetricsService::MODULES, $showsModule));

        $metrics = [];
        foreach ($modules as $module) {
            $metrics[$module] = $this->panelMetrics->metrics($company, $module, $period);
        }

        $paymentMethodBreakdown = [
            'invoicing' => $showsModule('invoicing') ? $this->panelMetrics->paymentMethodsInvoicing($company, $period) : null,
            'pos' => $showsModule('pos') ? $this->panelMetrics->paymentMethodsPos($company, $period) : null,
        ];

        $recentActivity = collect();
        foreach ($modules as $module) {
            $recentActivity = $recentActivity->merge($this->panelMetrics->recentActivityForModule($company, $module, $period));
        }

        return [
            'company' => $company,
            'period' => $period['key'],
            'moduleFilter' => $moduleFilter,
            'availableModules' => $context['availableModules'],
            'metrics' => $metrics,
            'utility' => $this->panelMetrics->utility($company, $period, $showsModule('invoicing'), $showsModule('pos')),
            'trend' => $this->panelMetrics->trend($company, $period, $modules),
            'moduleDistribution' => $this->panelMetrics->moduleDistribution($company, $period, $modules),
            'invoiceStatusBreakdown' => $showsModule('invoicing') ? $this->panelMetrics->invoiceStatusBreakdown($company, $period) : null,
            'warehouseComparison' => $this->panelMetrics->warehouseComparison($company, $period, array_intersect($modules, ['invoicing', 'pos', 'cotizaciones'])),
            'cashierComparison' => $showsModule('pos') ? $this->panelMetrics->cashierComparison($company, $period) : null,
            'sellerComparison' => $showsModule('pos') ? $this->panelMetrics->sellerComparison($company, $period) : null,
            'paymentMethodBreakdown' => $paymentMethodBreakdown,
            'topProductsInvoicing' => $showsModule('invoicing') ? $this->panelMetrics->topProducts($company, 'invoicing', $period) : [],
            'topProductsPos' => $showsModule('pos') ? $this->panelMetrics->topProducts($company, 'pos', $period) : [],
            'topClientsInvoicing' => $showsModule('invoicing') ? $this->panelMetrics->topClients($company, 'invoicing', $period) : null,
            'topClientsPos' => $showsModule('pos') ? $this->panelMetrics->topClients($company, 'pos', $period) : null,
            'recentActivity' => $recentActivity->sortByDesc('created_at')->take(8)->values()->all(),
            'lowStockProducts' => empty($modules) ? [] : $this->panelMetrics->lowStockProducts($company),
            'receivables' => $showsModule('invoicing') ? $this->panelMetrics->receivables($company) : null,
            'payables' => $showsModule('receiving') ? $this->panelMetrics->payables($company) : null,
        ];
    }
}
