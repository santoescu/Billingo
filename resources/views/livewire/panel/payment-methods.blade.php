<div class="{{ $breakdown ? '' : 'hidden' }}">
@if ($breakdown)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Payment methods') }} ({{ $periodLabel }})</span>
            @include('panel.partials.module-badge', ['module' => $module])
        </h3>
        <div
            x-data
            x-init="
                const moduleColors = { invoicing: '#f59e0b', pos: '#3b82f6' };
                new ApexCharts($refs.chart, {
                    chart: { type: 'bar', height: 260, toolbar: { show: false } },
                    series: [{ name: @js(__('Total')), data: @js($breakdown['values']) }],
                    xaxis: { categories: @js($breakdown['labels']) },
                    yaxis: { labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') } },
                    dataLabels: { enabled: false },
                    colors: [moduleColors['{{ $module }}']],
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
