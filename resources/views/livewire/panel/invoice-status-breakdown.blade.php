<div class="{{ $breakdown ? '' : 'hidden' }}">
@if ($breakdown)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Invoice status') }} ({{ $periodLabel }})</h3>
        <div
            x-data
            x-init="
                new ApexCharts($refs.chart, {
                    chart: { type: 'donut', height: 260 },
                    series: @js($breakdown['values']),
                    labels: @js($breakdown['labels']),
                    legend: { position: 'bottom' },
                    colors: ['#10b981', '#ef4444', '#f59e0b', '#6b7280'],
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
