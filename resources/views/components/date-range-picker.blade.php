@props(['nameFrom', 'nameTo', 'label' => null, 'valueFrom' => null, 'valueTo' => null, 'allowOpenEnd' => false, 'floating' => false])

@php
    $id = 'daterange-' . $nameFrom;

    $months = [
        __('January'), __('February'), __('March'), __('April'), __('May'), __('June'),
        __('July'), __('August'), __('September'), __('October'), __('November'), __('December'),
    ];

    $weekDays = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];

    $currentYear = (int) now()->format('Y');
    $years = range($currentYear - 10, $currentYear + 10);

    $selectConfig = \App\Support\SelectConfig::calendar('w-36');

    $renderMonthHeader = function (string $side) use ($months, $years, $selectConfig) {
        $prevHidden = $side === 'right' ? 'opacity-0 pointer-events-none' : '';
        $nextHidden = $side === 'left' ? 'opacity-0 pointer-events-none' : '';

        return view('components.partials.date-range-month-header', [
            'side' => $side,
            'months' => $months,
            'years' => $years,
            'selectConfig' => $selectConfig,
            'prevHidden' => $prevHidden,
            'nextHidden' => $nextHidden,
        ])->render();
    };
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}-input" class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ $label }}</label>
    @endif

    <div class="relative" data-daterange {{ $allowOpenEnd ? 'data-daterange-allow-open-end' : '' }} {{ $floating ? 'data-daterange-floating' : '' }}>
        <input type="text" id="{{ $id }}-input" readonly autocomplete="off"
            class="w-full cursor-pointer bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent"
            placeholder="{{ __('Select period') }}" data-daterange-trigger>
        <input type="hidden" name="{{ $nameFrom }}" data-daterange-hidden-from value="{{ $valueFrom }}">
        <input type="hidden" name="{{ $nameTo }}" data-daterange-hidden-to value="{{ $valueTo }}">

        <div class="hidden absolute top-full mt-2 z-50 w-auto flex flex-col bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 shadow-lg rounded-xl" data-daterange-panel>
            <div class="sm:flex">
                <div class="p-3 space-y-0.5" data-daterange-calendar="left">
                    {!! $renderMonthHeader('left') !!}

                    <div class="flex pb-1.5">
                        @foreach ($weekDays as $dayLabel)
                            <span class="m-px w-10 block text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $dayLabel }}</span>
                        @endforeach
                    </div>

                    <div data-daterange-days="left" class="grid grid-cols-7 gap-y-0.5"></div>
                </div>

                <div class="p-3 space-y-0.5" data-daterange-calendar="right">
                    {!! $renderMonthHeader('right') !!}

                    <div class="flex pb-1.5">
                        @foreach ($weekDays as $dayLabel)
                            <span class="m-px w-10 block text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $dayLabel }}</span>
                        @endforeach
                    </div>

                    <div data-daterange-days="right" class="grid grid-cols-7 gap-y-0.5"></div>
                </div>
            </div>

            <div class="py-3 px-4 flex items-center justify-end gap-x-2 border-t border-zinc-200 dark:border-white/10">
                <button type="button" data-daterange-cancel
                    class="py-2 px-3 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10">
                    {{ __('Cancel') }}
                </button>
                <button type="button" data-daterange-apply
                    class="py-2 px-3 inline-flex justify-center items-center gap-x-2 text-xs font-medium rounded-lg bg-accent text-white hover:bg-accent/90 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    {{ __('Apply') }}
                </button>
            </div>
        </div>
    </div>
</div>
