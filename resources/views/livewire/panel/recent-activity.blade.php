<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
    <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Recent activity') }} ({{ $periodLabel }})</h3>
    @if (empty($items))
        <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('No recent activity.') }}</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
            @foreach ($items as $item)
                <li>
                    <a href="{{ $item['url'] }}" class="flex justify-between items-center gap-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5 -mx-2 px-2 rounded-lg">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm text-gray-800 dark:text-white">
                                @include('panel.partials.module-badge', ['module' => $item['type']])
                                {{ $item['title'] }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ $item['created_at']->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-medium text-gray-800 dark:text-white">${{ number_format($item['total'], 2) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
