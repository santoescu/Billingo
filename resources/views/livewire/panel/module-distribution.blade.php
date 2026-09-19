<div class="{{ $distribution ? '' : 'hidden' }}">
@if ($distribution)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Revenue by module') }} ({{ $periodLabel }})</h3>
        <div
            x-data
            x-init="
                const moduleColors = { invoicing: '#f59e0b', pos: '#3b82f6', cotizaciones: '#10b981', receiving: '#a855f7' };
                new ApexCharts($refs.chart, {
                    chart: { type: 'donut', height: 260 },
                    series: @js($distribution['values']),
                    labels: @js($distribution['labels']),
                    legend: { position: 'bottom' },
                    colors: @js($distribution['modules']).map((key) => moduleColors[key] ?? '#6b7280'),
                    dataLabels: { formatter: (value) => Number(value).toFixed(1) + '%' },
                    tooltip: { y: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
