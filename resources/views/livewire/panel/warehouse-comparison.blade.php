<div class="{{ $comparison ? '' : 'hidden' }}">
@if ($comparison)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Sales by warehouse') }} ({{ $periodLabel }})</h3>
        <div
            x-data
            x-init="
                new ApexCharts($refs.chart, {
                    chart: { type: 'bar', height: 260, toolbar: { show: false } },
                    series: [{ name: @js(__('Sales')), data: @js($comparison['values']) }],
                    xaxis: { categories: @js($comparison['labels']) },
                    yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                    dataLabels: { enabled: false },
                    colors: ['#6366f1'],
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
