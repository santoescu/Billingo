@php
    $sideAttr = $side === 'left' ? 'left' : 'right';
@endphp

<div class="grid grid-cols-5 items-center gap-x-3 mx-1.5 pb-3">
    <div class="col-span-1">
        <button type="button" data-daterange-prev="{{ $sideAttr }}"
            class="{{ $prevHidden }} size-8 flex justify-center items-center text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10"
            aria-label="{{ __('Previous') }}">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
    </div>

    <div class="col-span-3 flex justify-center items-center gap-x-1">
        <div class="relative">
            <select data-daterange-month="{{ $sideAttr }}" data-hs-select='{!! $selectConfig !!}' class="hidden">
                @foreach ($months as $index => $monthName)
                    <option value="{{ $index }}">{{ $monthName }}</option>
                @endforeach
            </select>
        </div>

        <span class="text-zinc-700 dark:text-zinc-300">/</span>

        <div class="relative">
            <select data-daterange-year="{{ $sideAttr }}" data-hs-select='{!! $selectConfig !!}' class="hidden">
                @foreach ($years as $yearOption)
                    <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-span-1 flex justify-end">
        <button type="button" data-daterange-next="{{ $sideAttr }}"
            class="{{ $nextHidden }} size-8 flex justify-center items-center text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10"
            aria-label="{{ __('Next') }}">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>
</div>
