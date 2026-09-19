@php
    $periods = ['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'last_month' => __('Last month'), 'year' => __('This year')];
@endphp

<x-layouts.app :title="__('Panel')">
    @include('partials.tittle', [
        'title' => __('Panel'),
        'subheading' => __('Activity summary for :name', ['name' => $company->name]),
    ])

    @if (count($availableModules) > 1)
        <div id="panel-module-filters" class="mb-3 flex flex-wrap gap-1.5">
            <a href="{{ route('panel', ['period' => $period, 'module' => 'all']) }}"
                class="py-1.5 px-3 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg border {{ $moduleFilter === 'all' ? 'bg-accent border-accent text-white' : 'border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10' }}">
                {{ __('All modules') }}
            </a>
            @foreach ($availableModules as $moduleKey)
                <a href="{{ route('panel', ['period' => $period, 'module' => $moduleKey]) }}"
                    class="py-1.5 px-3 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg border {{ $moduleFilter === $moduleKey ? config("modules.$moduleKey.badge_classes") . ' border-transparent' : 'border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10' }}">
                    {{ config("modules.$moduleKey.name") }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div id="panel-period-filters" class="flex flex-wrap gap-1.5">
            @foreach ($periods as $key => $label)
                <a href="{{ route('panel', ['period' => $key, 'module' => $moduleFilter]) }}"
                    class="py-2 px-3 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg border {{ $period === $key ? 'bg-accent border-accent text-white' : 'border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <a id="panel-export-btn" href="{{ route('panel.pdf', ['period' => $period, 'module' => $moduleFilter]) }}" target="_blank">
            <flux:button variant="filled" size="sm" icon="arrow-down-tray">{{ __('Export PDF') }}</flux:button>
        </a>
    </div>

    @if (empty($availableModules))
        <section class="flex min-h-[200px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __("You don't administer any active module in this company yet.") }}</p>
        </section>
    @else
        {{--
            Cada tarjeta/gráfica de acá para abajo es su propio componente
            Livewire #[Lazy] (ver App\Livewire\Panel\PanelWidget): pinta un
            esqueleto al instante y dispara su propia consulta por separado,
            así que una tarjeta lenta no bloquea a las demás. La key de cada
            @livewire(...) incluye periodo+filtro de módulo para que cada
            combinación remonte los componentes con datos frescos al cambiar
            de filtro.
        --}}
        @php
            $ctx = ['companyId' => (string) $company->_id, 'period' => $period, 'moduleFilter' => $moduleFilter];
            $k = $period . '-' . $moduleFilter;
        @endphp

        <div id="panel-metrics-grid" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-6">
            @livewire('panel.metric-card', $ctx + ['module' => 'invoicing'], 'metric-invoicing-' . $k)
            @livewire('panel.metric-card', $ctx + ['module' => 'pos'], 'metric-pos-' . $k)
            @livewire('panel.metric-card', $ctx + ['module' => 'cotizaciones'], 'metric-cotizaciones-' . $k)
            @livewire('panel.metric-card', $ctx + ['module' => 'receiving'], 'metric-receiving-' . $k)
            @livewire('panel.utility', $ctx, 'utility-' . $k)
            @livewire('panel.low-stock-products', $ctx, 'low-stock-' . $k)
            @livewire('panel.receivables', $ctx, 'receivables-' . $k)
            @livewire('panel.payables', $ctx, 'payables-' . $k)
        </div>

        @livewire('panel.trend-chart', $ctx, 'trend-' . $k)

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.top-products', $ctx + ['module' => 'invoicing'], 'top-products-invoicing-' . $k)
            @livewire('panel.top-products', $ctx + ['module' => 'pos'], 'top-products-pos-' . $k)
        </div>

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.top-clients', $ctx + ['module' => 'invoicing'], 'top-clients-invoicing-' . $k)
            @livewire('panel.top-clients', $ctx + ['module' => 'pos'], 'top-clients-pos-' . $k)
        </div>

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.top-products', $ctx + ['module' => 'receiving'], 'top-products-receiving-' . $k)
            @livewire('panel.top-suppliers', $ctx, 'top-suppliers-' . $k)
        </div>

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.module-distribution', $ctx, 'module-distribution-' . $k)
            @livewire('panel.invoice-status-breakdown', $ctx, 'invoice-status-' . $k)
        </div>

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.warehouse-comparison', $ctx, 'warehouse-' . $k)
            @livewire('panel.cashier-comparison', $ctx, 'cashier-' . $k)
        </div>

        @livewire('panel.seller-comparison', $ctx, 'seller-' . $k)

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            @livewire('panel.payment-methods', $ctx + ['module' => 'invoicing'], 'payment-methods-invoicing-' . $k)
            @livewire('panel.payment-methods', $ctx + ['module' => 'pos'], 'payment-methods-pos-' . $k)
        </div>

        @livewire('panel.recent-activity', $ctx, 'recent-activity-' . $k)
    @endif
</x-layouts.app>
