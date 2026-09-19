<div class="{{ $items ? '' : 'hidden' }}">
@if ($items)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top suppliers') }} ({{ $periodLabel }})</span>
            @include('panel.partials.module-badge', ['module' => 'receiving'])
        </h3>
        @include('panel.partials.top-list', ['items' => $items, 'type' => 'client'])
    </div>
@endif
</div>
