<div class="{{ $payables ? '' : 'hidden' }}">
@if ($payables)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('Accounts payable') }}</span>
            @include('panel.partials.module-badge', ['module' => 'receiving'])
        </h3>
        <dl class="space-y-2 mb-4">
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Pending payment') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $payables['pending_count'] }} &middot; $<x-panel.count-up :target="$payables['total_pending']" :decimals="2" /></dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Overdue') }}</dt>
                <dd class="text-sm font-medium {{ $payables['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white' }}">{{ $payables['overdue_count'] }} &middot; $<x-panel.count-up :target="$payables['total_overdue']" :decimals="2" /></dd>
            </div>
        </dl>
        @if (! empty($payables['top_overdue']))
            <flux:separator variant="subtle" />
            <p class="text-xs font-medium text-zinc-500 dark:text-neutral-400 mt-4 mb-2">{{ __('Most overdue bills') }}</p>
            <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach ($payables['top_overdue'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="flex justify-between items-center gap-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5 -mx-2 px-2 rounded-lg">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 dark:text-white truncate">{{ $item['numeral'] }} &middot; {{ $item['supplier'] }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400">{{ __('Due') }} {{ $item['due_date']->setTimezone('America/Bogota')->format('d/m/Y') }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-medium text-gray-800 dark:text-white">${{ number_format($item['total'], 2) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
</div>
