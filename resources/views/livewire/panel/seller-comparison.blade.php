<div class="{{ $comparison ? '' : 'hidden' }}">
@if ($comparison)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800 mb-6">
        <h3 class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Sales by seller') }} ({{ $periodLabel }})</span>
            @include('panel.partials.module-badge', ['module' => 'pos'])
        </h3>
        {{--
            En barras horizontales, ApexCharts usa "xaxis" para el eje
            numérico (abajo) y "yaxis" para los nombres (izquierda) -- al
            revés que en una gráfica vertical. "min: 0" evita que, con un
            solo vendedor, el eje se quede sin rango para calcular la
            escala.
        --}}
        <div
            x-data
            x-init="
                new ApexCharts($refs.chart, {
                    chart: { type: 'bar', height: Math.max(260, @js($comparison['labels']).length * 40), toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true, distributed: true } },
                    legend: { show: false },
                    series: [{ name: @js(__('Sales')), data: @js($comparison['values']) }],
                    xaxis: {
                        categories: @js($comparison['labels']),
                        min: 0,
                        labels: { formatter: (value) => '$' + Number(value).toLocaleString('es-CO') },
                    },
                    dataLabels: { enabled: false },
                    colors: @js($comparison['labels']).map((_, index) => index === 0 ? '#3b82f6' : '#bfdbfe'),
                    tooltip: {
                        y: {
                            formatter: (value) => '$' + Number(value).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        },
                    },
                }).render();
            "
        >
            <div x-ref="chart" wire:ignore></div>
        </div>
    </div>
@endif
</div>
