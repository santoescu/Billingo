<div class="{{ $items !== null ? '' : 'hidden' }}">
@if ($items !== null)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Top products') }} ({{ $periodLabel }})</span>
            @include('panel.partials.module-badge', ['module' => $module])
        </h3>
        @include('panel.partials.top-list', ['items' => $items, 'type' => 'product'])
    </div>
@endif
</div>
