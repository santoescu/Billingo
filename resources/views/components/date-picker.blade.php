@props(['name', 'label' => null, 'value' => null, 'readonly' => false, 'allowEmpty' => false])

@php
    $id = 'datepicker-' . $name;
    
    $value = $value ?: ($allowEmpty ? '' : now('America/Bogota')->format('Y-m-d'));

    $months = [
        __('January'), __('February'), __('March'), __('April'), __('May'), __('June'),
        __('July'), __('August'), __('September'), __('October'), __('November'), __('December'),
    ];

    $weekDays = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];

    $currentYear = (int) now()->format('Y');
    $years = range($currentYear - 10, $currentYear + 10);

    $monthSelectConfig = \App\Support\SelectConfig::calendar('w-36');
    $yearSelectConfig = \App\Support\SelectConfig::calendar('w-20');
@endphp

<div data-flux-field>
    @if ($label)
        <label data-flux-label for="{{ $id }}-input" class="text-sm font-medium text-zinc-800 dark:text-white">{{ $label }}</label>
    @endif

    @if ($readonly)
        <input type="text" id="{{ $id }}-input" readonly disabled
            class="w-full bg-zinc-50 dark:bg-white/5 border border-zinc-200 dark:border-white/10 text-zinc-500 dark:text-zinc-400 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 cursor-not-allowed"
            value="{{ $value }}">
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @else
    <div class="relative" data-datepicker data-datepicker-value="{{ $value }}">
        <input type="text" id="{{ $id }}-input" readonly autocomplete="off"
            class="w-full cursor-pointer bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent"
            placeholder="{{ __('Select date') }}" data-datepicker-trigger>
        <input type="hidden" name="{{ $name }}" data-datepicker-hidden value="{{ $value }}">

        <div class="hidden absolute top-full mt-2 z-50 w-80 flex flex-col bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 shadow-lg rounded-xl" data-datepicker-panel>
            <div class="p-3 space-y-0.5">
                <div class="grid grid-cols-5 items-center gap-x-3 mx-1.5 pb-3">
                    <div class="col-span-1">
                        <button type="button" data-datepicker-prev
                            class="size-8 flex justify-center items-center text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10"
                            aria-label="{{ __('Previous') }}">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                    </div>

                    <div class="col-span-3 flex justify-center items-center gap-x-1">
                        <div class="relative">
                            <select data-datepicker-month data-hs-select='{!! $monthSelectConfig !!}' class="hidden">
                                @foreach ($months as $index => $monthName)
                                    <option value="{{ $index }}">{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <span class="text-zinc-700 dark:text-zinc-300">/</span>

                        <div class="relative">
                            <select data-datepicker-year data-hs-select='{!! $yearSelectConfig !!}' class="hidden">
                                @foreach ($years as $yearOption)
                                    <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-span-1 flex justify-end">
                        <button type="button" data-datepicker-next
                            class="size-8 flex justify-center items-center text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10"
                            aria-label="{{ __('Next') }}">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex pb-1.5">
                    @foreach ($weekDays as $dayLabel)
                        <span class="m-px w-10 block text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $dayLabel }}</span>
                    @endforeach
                </div>

                <div data-datepicker-days class="grid grid-cols-7 gap-y-0.5"></div>
            </div>

            <div class="p-2 border-t border-zinc-200 dark:border-white/10">
                <button type="button" data-datepicker-today
                    class="w-full py-1.5 text-sm font-medium text-center text-accent hover:bg-zinc-100 dark:hover:bg-white/10 rounded-lg focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10">
                    {{ __('Today') }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
