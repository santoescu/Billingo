<div class="{{ $stats ? '' : 'hidden' }}">
@if ($stats)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="mb-3">
            @include('panel.partials.module-badge', ['module' => 'loyalty'])
        </h3>
        <dl class="space-y-2">
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Active customers') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $stats['active_customers'] }}</dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Earned') }} ({{ $periodLabel }})</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $stats['earned_count'] }}</dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Redeemed') }} ({{ $periodLabel }})</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $stats['redeemed_count'] }}</dd>
            </div>
        </dl>
    </div>
@endif
</div>
