@php
    $periods = ['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'last_month' => __('Last month'), 'year' => __('This year')];
@endphp

<x-layouts.app :title="__('Panel')">
    @include('partials.tittle', [
        'title' => __('Panel'),
        'subheading' => __('Activity summary for :name', ['name' => $company->name]),
    ])

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            @foreach ($periods as $key => $label)
                <a href="{{ route('panel', ['period' => $key]) }}"
                    class="py-2 px-3 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg border {{ $period === $key ? 'bg-accent border-accent text-white' : 'border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <a href="{{ route('panel.pdf', ['period' => $period]) }}" target="_blank">
            <flux:button variant="filled" size="sm" icon="arrow-down-tray">{{ __('Export PDF') }}</flux:button>
        </a>
    </div>

    @if (empty($metrics))
        <section class="flex min-h-[200px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __("You don't administer any active module in this company yet.") }}</p>
        </section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-6">
            @if (isset($metrics['invoicing']))
                @php $m = $metrics['invoicing']; @endphp
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="mb-3">
                        @include('panel.partials.module-badge', ['module' => 'invoicing'])
                    </h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issued today') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $m['today_count'] }} &middot; ${{ number_format($m['today_total'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issued') }} ({{ $periods[$period] }})</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">
                                {{ $m['period_count'] }} &middot; ${{ number_format($m['period_total'], 2) }}
                                @include('panel.partials.change-badge', ['pct' => $m['period_change_pct']])
                            </dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issues') }} ({{ $periods[$period] }})</dt>
                            <dd class="text-sm font-medium {{ $m['period_issues'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white' }}">{{ $m['period_issues'] }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if (isset($metrics['pos']))
                @php $m = $metrics['pos']; @endphp
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="mb-3">
                        @include('panel.partials.module-badge', ['module' => 'pos'])
                    </h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Open shifts') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $m['open_shifts'] }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Sales today') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $m['today_count'] }} &middot; ${{ number_format($m['today_total'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Sales') }} ({{ $periods[$period] }})</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">
                                {{ $m['period_count'] }} &middot; ${{ number_format($m['period_total'], 2) }}
                                @include('panel.partials.change-badge', ['pct' => $m['period_change_pct']])
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if (isset($metrics['cotizaciones']))
                @php $m = $metrics['cotizaciones']; @endphp
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="mb-3">
                        @include('panel.partials.module-badge', ['module' => 'cotizaciones'])
                    </h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Pending') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $m['pending_count'] }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Converted') }} ({{ $periods[$period] }})</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $m['converted_period_count'] }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Quoted') }} ({{ $periods[$period] }})</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">
                                ${{ number_format($m['period_total'], 2) }}
                                @include('panel.partials.change-badge', ['pct' => $m['period_change_pct']])
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if ($utility)
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Gross profit') }} ({{ $periods[$period] }})</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Revenue') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">${{ number_format($utility['revenue'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Cost') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">${{ number_format($utility['cogs'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Profit') }} &middot; {{ __('Margin') }} {{ $utility['margin_pct'] }}%</dt>
                            <dd class="text-sm font-medium {{ $utility['profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                ${{ number_format($utility['profit'], 2) }}
                                @include('panel.partials.change-badge', ['pct' => $utility['change_pct']])
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if (! empty($lowStockProducts))
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Low stock') }}</h3>
                    <ul class="space-y-2">
                        @foreach ($lowStockProducts as $product)
                            <li class="flex justify-between items-center gap-3">
                                <span class="text-sm text-gray-800 dark:text-white truncate">{{ $product->description }}</span>
                                <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ rtrim(rtrim(number_format($product->stock, 2), '0'), '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($receivables)
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Accounts receivable') }}</span>
                        @include('panel.partials.module-badge', ['module' => 'invoicing'])
                    </h3>
                    <dl class="space-y-2 mb-4">
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Pending collection') }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $receivables['pending_count'] }} &middot; ${{ number_format($receivables['total_pending'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Overdue') }}</dt>
                            <dd class="text-sm font-medium {{ $receivables['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white' }}">{{ $receivables['overdue_count'] }} &middot; ${{ number_format($receivables['total_overdue'], 2) }}</dd>
                        </div>
                    </dl>
                    @if (! empty($receivables['top_overdue']))
                        <flux:separator variant="subtle" />
                        <p class="text-xs font-medium text-zinc-500 dark:text-neutral-400 mt-4 mb-2">{{ __('Most overdue invoices') }}</p>
                        <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                            @foreach ($receivables['top_overdue'] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" class="flex justify-between items-center gap-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5 -mx-2 px-2 rounded-lg">
                                        <div class="min-w-0">
                                            <p class="text-sm text-gray-800 dark:text-white truncate">{{ $item['numeral'] }} &middot; {{ $item['client'] }}</p>
                                            <p class="text-xs text-red-600 dark:text-red-400">{{ __('Due') }} {{ $item['due_date']->setTimezone('America/Bogota')->format('d/m/Y') }}</p>
                                        </div>
                                        <span class="shrink-0 text-sm font-medium text-gray-800 dark:text-white">${{ number_format($item['total'], 2) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800 mb-6">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Trend') }}</h3>
            <div id="panel-trend-chart"></div>
        </div>

        @if (isset($metrics['invoicing']) || isset($metrics['pos']))
            <div class="grid gap-4 sm:grid-cols-2 mb-6">
                @if (isset($metrics['invoicing']))
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top products') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'invoicing'])
                        </h3>
                        @include('panel.partials.top-list', ['items' => $topProductsInvoicing, 'type' => 'product'])
                    </div>
                @endif

                @if (isset($metrics['pos']))
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top products') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'pos'])
                        </h3>
                        @include('panel.partials.top-list', ['items' => $topProductsPos, 'type' => 'product'])
                    </div>
                @endif
            </div>
        @endif

        @if ($topClientsInvoicing || $topClientsPos)
            <div class="grid gap-4 sm:grid-cols-2 mb-6">
                @if ($topClientsInvoicing)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top clients') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'invoicing'])
                        </h3>
                        @include('panel.partials.top-list', ['items' => $topClientsInvoicing, 'type' => 'client'])
                    </div>
                @endif

                @if ($topClientsPos)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top clients') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'pos'])
                        </h3>
                        @include('panel.partials.top-list', ['items' => $topClientsPos, 'type' => 'client'])
                    </div>
                @endif
            </div>
        @endif

        @if ($moduleDistribution || $invoiceStatusBreakdown)
            <div class="grid gap-4 sm:grid-cols-2 mb-6">
                @if ($moduleDistribution)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Revenue by module') }} ({{ $periods[$period] }})</h3>
                        <div id="panel-module-chart"></div>
                    </div>
                @endif

                @if ($invoiceStatusBreakdown)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Invoice status') }} ({{ $periods[$period] }})</h3>
                        <div id="panel-status-chart"></div>
                    </div>
                @endif
            </div>
        @endif

        @if ($warehouseComparison || $cashierComparison)
            <div class="grid gap-4 sm:grid-cols-2 mb-6">
                @if ($warehouseComparison)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Sales by warehouse') }} ({{ $periods[$period] }})</h3>
                        <div id="panel-warehouse-chart"></div>
                    </div>
                @endif

                @if ($cashierComparison)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Sales by cashier') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'pos'])
                        </h3>
                        <div id="panel-cashier-chart"></div>
                    </div>
                @endif
            </div>
        @endif

        @if ($sellerComparison)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800 mb-6">
                <h3 class="flex items-center justify-between gap-2 mb-3">
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Sales by seller') }} ({{ $periods[$period] }})</span>
                    @include('panel.partials.module-badge', ['module' => 'pos'])
                </h3>
                <div id="panel-seller-chart"></div>
            </div>
        @endif

        @if ($paymentMethodBreakdown['invoicing'] || $paymentMethodBreakdown['pos'])
            <div class="grid gap-4 sm:grid-cols-2 mb-6">
                @if ($paymentMethodBreakdown['invoicing'])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Payment methods') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'invoicing'])
                        </h3>
                        <div id="panel-payment-invoicing-chart"></div>
                    </div>
                @endif

                @if ($paymentMethodBreakdown['pos'])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h3 class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Payment methods') }} ({{ $periods[$period] }})</span>
                            @include('panel.partials.module-badge', ['module' => 'pos'])
                        </h3>
                        <div id="panel-payment-pos-chart"></div>
                    </div>
                @endif
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Recent activity') }} ({{ $periods[$period] }})</h3>
            @if (empty($recentActivity))
                <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('No recent activity.') }}</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                    @foreach ($recentActivity as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex justify-between items-center gap-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5 -mx-2 px-2 rounded-lg">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 text-sm text-gray-800 dark:text-white">
                                        @include('panel.partials.module-badge', ['module' => $item['type']])
                                        {{ $item['title'] }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ $item['created_at']->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-medium text-gray-800 dark:text-white">${{ number_format($item['total'], 2) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @push('scripts')
            <script>
                /**
                 * Arma todas las gráficas del panel -- se llama tanto en
                 * "DOMContentLoaded" (primera carga real de la página) como
                 * en "livewire:navigated" (navegación por wire:navigate
                 * desde el sidebar, que NO dispara "DOMContentLoaded" de
                 * nuevo): sin el segundo listener, entrar al panel por un
                 * link del sidebar nunca pintaba las gráficas -- solo
                 * funcionaba con un refresh manual de la página.
                 * @returns {void}
                 */
                function initPanelCharts() {
                    if (! window.ApexCharts) {
                        return;
                    }

                    const MODULE_COLORS = { invoicing: '#f59e0b', pos: '#3b82f6', cotizaciones: '#10b981' };

                    const trendEl = document.getElementById('panel-trend-chart');
                    if (trendEl && ! trendEl.dataset.rendered) {
                        trendEl.dataset.rendered = 'true';

                        const seriesNames = {
                            invoicing: @json(config('modules.invoicing.name')),
                            pos: @json(config('modules.pos.name')),
                            cotizaciones: @json(config('modules.cotizaciones.name')),
                        };
                        const seriesData = @json($trend['series']);
                        const series = Object.entries(seriesData).map(([key, data]) => ({
                            name: seriesNames[key] ?? key,
                            data,
                        }));

                        new ApexCharts(trendEl, {
                            chart: { type: 'line', height: 300, toolbar: { show: false } },
                            series,
                            xaxis: { categories: @json($trend['labels']) },
                            yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                            stroke: { curve: 'smooth', width: 2 },
                            dataLabels: { enabled: false },
                            legend: { position: 'top' },
                            colors: Object.keys(seriesData).map((key) => MODULE_COLORS[key] ?? '#6b7280'),
                        }).render();
                    }

                    @if ($moduleDistribution)
                        const moduleEl = document.getElementById('panel-module-chart');
                        if (moduleEl && ! moduleEl.dataset.rendered) {
                            moduleEl.dataset.rendered = 'true';
                            new ApexCharts(moduleEl, {
                                chart: { type: 'donut', height: 260 },
                                series: @json($moduleDistribution['values']),
                                labels: @json($moduleDistribution['labels']),
                                legend: { position: 'bottom' },
                                colors: @json($moduleDistribution['modules']).map((key) => MODULE_COLORS[key] ?? '#6b7280'),
                                dataLabels: { formatter: (value) => Number(value).toFixed(1) + '%' },
                                tooltip: { y: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                            }).render();
                        }
                    @endif

                    @if ($invoiceStatusBreakdown)
                        const statusEl = document.getElementById('panel-status-chart');
                        if (statusEl && ! statusEl.dataset.rendered) {
                            statusEl.dataset.rendered = 'true';
                            new ApexCharts(statusEl, {
                                chart: { type: 'donut', height: 260 },
                                series: @json($invoiceStatusBreakdown['values']),
                                labels: @json($invoiceStatusBreakdown['labels']),
                                legend: { position: 'bottom' },
                                colors: ['#10b981', '#ef4444', '#f59e0b', '#6b7280'],
                            }).render();
                        }
                    @endif

                    @if ($warehouseComparison)
                        const warehouseEl = document.getElementById('panel-warehouse-chart');
                        if (warehouseEl && ! warehouseEl.dataset.rendered) {
                            warehouseEl.dataset.rendered = 'true';
                            new ApexCharts(warehouseEl, {
                                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                                series: [{ name: @json(__('Sales')), data: @json($warehouseComparison['values']) }],
                                xaxis: { categories: @json($warehouseComparison['labels']) },
                                yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                                dataLabels: { enabled: false },
                                colors: ['#6366f1'],
                            }).render();
                        }
                    @endif

                    @if ($cashierComparison)
                        const cashierEl = document.getElementById('panel-cashier-chart');
                        if (cashierEl && ! cashierEl.dataset.rendered) {
                            cashierEl.dataset.rendered = 'true';
                            new ApexCharts(cashierEl, {
                                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                                series: [{ name: @json(__('Sales')), data: @json($cashierComparison['values']) }],
                                xaxis: { categories: @json($cashierComparison['labels']) },
                                yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                                dataLabels: { enabled: false },
                                colors: [MODULE_COLORS.pos],
                            }).render();
                        }
                    @endif

                    @if ($sellerComparison)
                        const sellerEl = document.getElementById('panel-seller-chart');
                        if (sellerEl && ! sellerEl.dataset.rendered) {
                            sellerEl.dataset.rendered = 'true';
                            new ApexCharts(sellerEl, {
                                chart: { type: 'bar', height: Math.max(260, {{ count($sellerComparison['labels']) }} * 40), toolbar: { show: false } },
                                plotOptions: { bar: { horizontal: true, distributed: true } },
                                legend: { show: false },
                                series: [{ name: @json(__('Sales')), data: @json($sellerComparison['values']) }],
                                // En barras horizontales, ApexCharts usa
                                // "xaxis" para el eje numérico (abajo) y
                                // "yaxis" para los nombres (izquierda) --
                                // al revés que en una gráfica vertical. El
                                // formateador de dólares va en xaxis, no en
                                // yaxis (si no, se aplica sobre los nombres
                                // de los vendedores). "min: 0" evita que,
                                // con un solo vendedor, el eje se quede sin
                                // rango para calcular la escala.
                                xaxis: {
                                    categories: @json($sellerComparison['labels']),
                                    min: 0,
                                    labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') },
                                },
                                dataLabels: { enabled: false },
                                colors: @json($sellerComparison['labels']).map((_, index) => index === 0 ? MODULE_COLORS.pos : '#bfdbfe'),
                                // tooltip.y (no tooltip.x) formatea el VALOR
                                // que se muestra en el tooltip -- "x"/"y" acá
                                // son roles de dato (categoría/valor), no
                                // posición visual, así que no se invierten
                                // con horizontal:true como sí pasa con los
                                // ejes.
                                tooltip: {
                                    y: {
                                        formatter: (value) => '$' + Number(value).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                                    },
                                },
                            }).render();
                        }
                    @endif

                    @if ($paymentMethodBreakdown['invoicing'])
                        const paymentInvoicingEl = document.getElementById('panel-payment-invoicing-chart');
                        if (paymentInvoicingEl && ! paymentInvoicingEl.dataset.rendered) {
                            paymentInvoicingEl.dataset.rendered = 'true';
                            new ApexCharts(paymentInvoicingEl, {
                                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                                series: [{ name: @json(__('Total')), data: @json($paymentMethodBreakdown['invoicing']['values']) }],
                                xaxis: { categories: @json($paymentMethodBreakdown['invoicing']['labels']) },
                                yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                                dataLabels: { enabled: false },
                                colors: [MODULE_COLORS.invoicing],
                            }).render();
                        }
                    @endif

                    @if ($paymentMethodBreakdown['pos'])
                        const paymentPosEl = document.getElementById('panel-payment-pos-chart');
                        if (paymentPosEl && ! paymentPosEl.dataset.rendered) {
                            paymentPosEl.dataset.rendered = 'true';
                            new ApexCharts(paymentPosEl, {
                                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                                series: [{ name: @json(__('Total')), data: @json($paymentMethodBreakdown['pos']['values']) }],
                                xaxis: { categories: @json($paymentMethodBreakdown['pos']['labels']) },
                                yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                                dataLabels: { enabled: false },
                                colors: [MODULE_COLORS.pos],
                            }).render();
                        }
                    @endif
                }

                document.addEventListener('DOMContentLoaded', initPanelCharts);
                document.addEventListener('livewire:navigated', initPanelCharts);
            </script>
        @endpush
    @endif
</x-layouts.app>
