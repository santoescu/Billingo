<div class="{{ $utility ? '' : 'hidden' }}">
@if ($utility)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Gross profit') }} ({{ $periodLabel }})</h3>
        <dl class="space-y-2">
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Revenue') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">$<x-panel.count-up :target="$utility['revenue']" :decimals="2" /></dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Cost') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">$<x-panel.count-up :target="$utility['cogs']" :decimals="2" /></dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Profit') }} &middot; {{ __('Margin') }} {{ $utility['margin_pct'] }}%</dt>
                <dd class="text-sm font-medium {{ $utility['profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    $<x-panel.count-up :target="$utility['profit']" :decimals="2" />
                    @include('panel.partials.change-badge', ['pct' => $utility['change_pct']])
                </dd>
            </div>
        </dl>
    </div>
@endif
</div>
