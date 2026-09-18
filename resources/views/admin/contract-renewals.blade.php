<x-layouts.app :title="__('Expiring contracts')">
    @include('partials.tittle', [
        'title' => __('Active contracts'),
        'subheading' => __('All contracts active right now. Those ending in the next :days days are highlighted so you can reach out before the company leaves or loses access.', ['days' => $lookaheadDays]),
    ])

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Active contracts') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($rows->count()) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-red-600 dark:text-red-400 uppercase">{{ __('Without renewal yet') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($countAtRisk) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-red-600 dark:text-red-400 uppercase">{{ __('Revenue at risk') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalAtRisk, 2, '.', ',') }}</div>
        </div>
    </div>

    <div class="-m-1.5 overflow-x-auto">
        <div class="p-1.5 min-w-full inline-block align-middle">
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Companies') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Modules') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Ends on') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Days left') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Value') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $row['companies'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                        <div class="flex flex-col gap-1">
                                            @foreach ($row['modules'] as $module)
                                                <div class="flex items-center gap-2">
                                                    @include('panel.partials.module-badge', ['module' => $module])
                                                    @if ($row['unlimited'])
                                                        <span class="text-xs text-neutral-400">{{ __('Unlimited') }}</span>
                                                    @elseif (isset($row['quota_by_module'][$module]))
                                                        <span class="text-xs {{ $row['quota_by_module'][$module]['warning'] ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-neutral-400' }}">
                                                            {{ $row['quota_by_module'][$module]['text'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $row['ends_at'] ?? __('No expiration') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $row['days_left'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format($row['price'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm break-words">
                                        @if (! $row['needs_attention'])
                                            <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-neutral-500/10 dark:text-neutral-400">{{ __('Active') }}</span>
                                        @elseif ($row['has_replacement'])
                                            <span class="inline-flex items-center gap-1 rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-500/10 dark:text-green-400">{{ __('Already renewed') }}</span>
                                        @else
                                            @if ($row['contacted_at'])
                                                <span class="inline-flex items-center gap-1 rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-500/10 dark:text-blue-400">{{ __('Contacted') }}</span>
                                                <div class="mt-1 text-xs text-neutral-400">{{ __('On :date', ['date' => $row['contacted_at']]) }}</div>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-400">{{ __('Needs outreach') }}</span>
                                            @endif

                                            @if ($row['usage_drop_warning'])
                                                <flux:tooltip :content="$row['usage_drop_warning']" position="top">
                                                    <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300 cursor-help">
                                                        {{ __('Usage drop') }}
                                                    </span>
                                                </flux:tooltip>
                                            @endif

                                            <form method="POST" action="{{ route('admin.contract-renewals.toggle-contacted', $row['id']) }}" class="mt-1">
                                                @csrf
                                                <button type="submit" class="text-xs text-accent hover:underline">
                                                    {{ $row['contacted_at'] ? __('Remove mark') : __('Mark as contacted') }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no active contracts.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
