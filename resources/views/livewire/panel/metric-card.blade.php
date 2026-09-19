<div class="{{ $metrics ? '' : 'hidden' }}">
@if ($metrics)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="mb-3">
            @include('panel.partials.module-badge', ['module' => $module])
        </h3>
        <dl class="space-y-2">
            @if ($module === 'invoicing')
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issued today') }}</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['today_count'] }} &middot; $<x-panel.count-up :target="$metrics['today_total']" :decimals="2" /></dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issued') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">
                        {{ $metrics['period_count'] }} &middot; $<x-panel.count-up :target="$metrics['period_total']" :decimals="2" />
                        @include('panel.partials.change-badge', ['pct' => $metrics['period_change_pct']])
                    </dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Issues') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium {{ $metrics['period_issues'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white' }}">{{ $metrics['period_issues'] }}</dd>
                </div>
            @elseif ($module === 'pos')
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Open shifts') }}</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['open_shifts'] }}</dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Sales today') }}</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['today_count'] }} &middot; $<x-panel.count-up :target="$metrics['today_total']" :decimals="2" /></dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Sales') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">
                        {{ $metrics['period_count'] }} &middot; $<x-panel.count-up :target="$metrics['period_total']" :decimals="2" />
                        @include('panel.partials.change-badge', ['pct' => $metrics['period_change_pct']])
                    </dd>
                </div>
            @elseif ($module === 'cotizaciones')
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Pending') }}</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['pending_count'] }}</dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Converted') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['converted_period_count'] }}</dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Quoted') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">
                        $<x-panel.count-up :target="$metrics['period_total']" :decimals="2" />
                        @include('panel.partials.change-badge', ['pct' => $metrics['period_change_pct']])
                    </dd>
                </div>
            @elseif ($module === 'receiving')
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Received today') }}</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $metrics['today_count'] }} &middot; $<x-panel.count-up :target="$metrics['today_total']" :decimals="2" /></dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Received') }} ({{ $periodLabel }})</dt>
                    <dd class="text-sm font-medium text-gray-800 dark:text-white">
                        {{ $metrics['period_count'] }} &middot; $<x-panel.count-up :target="$metrics['period_total']" :decimals="2" />
                        @include('panel.partials.change-badge', ['pct' => $metrics['period_change_pct']])
                    </dd>
                </div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Pending review') }}</dt>
                    <dd class="text-sm font-medium {{ $metrics['pending_review_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-800 dark:text-white' }}">{{ $metrics['pending_review_count'] }}</dd>
                </div>
            @endif
        </dl>
    </div>
@endif
</div>
