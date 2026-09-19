<div class="{{ $trend ? '' : 'hidden' }}">
@if ($trend)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Trend') }}</h3>
        {{--
            Cada tarjeta con gráfica arma su propio ApexCharts con Alpine
            (x-init corre una sola vez al montar este nodo, sin depender de
            "DOMContentLoaded"/"livewire:navigated" globales) -- $refs.chart
            es local a esta instancia, así que no hay colisión de ids aunque
            el panel tenga varias gráficas en la misma página.
        --}}
        <div
            x-data
            x-init="
                const seriesNames = @js(collect($trend['series'])->keys()->mapWithKeys(fn ($m) => [$m => config('modules.' . $m . '.name')])->all());
                const moduleColors = { invoicing: '#f59e0b', pos: '#3b82f6', cotizaciones: '#10b981', receiving: '#a855f7' };
                const seriesData = @js($trend['series']);
                const series = Object.entries(seriesData).map(([key, data]) => ({ name: seriesNames[key] ?? key, data }));

                new ApexCharts($refs.chart, {
                    chart: { type: 'line', height: 300, toolbar: { show: false } },
                    series,
                    xaxis: { categories: @js($trend['labels']) },
                    yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                    stroke: { curve: 'smooth', width: 2 },
                    dataLabels: { enabled: false },
                    legend: { position: 'top' },
                    colors: Object.keys(seriesData).map((key) => moduleColors[key] ?? '#6b7280'),
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
