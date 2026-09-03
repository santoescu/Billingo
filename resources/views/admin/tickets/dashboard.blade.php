@php
    $formatMinutes = function (?int $minutes) {
        if ($minutes === null) {
            return __('No data yet');
        }

        if ($minutes < 60) {
            return __(':minutes min', ['minutes' => $minutes]);
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? __(':hours h :minutes min', ['hours' => $hours, 'minutes' => $rest]) : __(':hours h', ['hours' => $hours]);
    };

    $now = \Carbon\Carbon::now();

    $presets = [
        'this_month' => [
            'label' => __('This month'),
            'from' => $now->copy()->startOfMonth()->format('Y-m-d'),
            'to' => $now->copy()->endOfMonth()->format('Y-m-d'),
        ],
        'last_month' => [
            'label' => __('Last month'),
            'from' => $now->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
            'to' => $now->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
        ],
        'last_3_months' => [
            'label' => __('Last 3 months'),
            'from' => $now->copy()->subMonthsNoOverflow(2)->startOfMonth()->format('Y-m-d'),
            'to' => $now->copy()->endOfMonth()->format('Y-m-d'),
        ],
    ];

    $activePreset = collect($presets)->search(fn ($preset) => $preset['from'] === ($dateFrom ?? null) && $preset['to'] === ($dateTo ?? null));
    $activePreset = $activePreset === false ? (($dateFrom || $dateTo) ? null : 'all') : $activePreset;
@endphp

<x-layouts.app :title="__('Support dashboard')">
    @include('partials.tittle', [
        'title' => __('Support dashboard'),
        'subheading' => __('An overview of support activity across all companies.'),
    ])

    @include('admin.tickets.partials.tabs', ['activeTab' => 'dashboard'])

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tickets.dashboard') }}" wire:navigate
                    class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $activePreset === 'all' ? 'bg-accent text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600' }}">
                    {{ __('All time') }}
                </a>
                @foreach ($presets as $key => $preset)
                    <a href="{{ route('admin.tickets.dashboard', ['from' => $preset['from'], 'to' => $preset['to']]) }}" wire:navigate
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $activePreset === $key ? 'bg-accent text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600' }}">
                        {{ $preset['label'] }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.tickets.dashboard') }}" class="flex items-end gap-2">
                <div class="w-64">
                    <x-date-range-picker name-from="from" name-to="to" :label="__('Custom range')" :value-from="$dateFrom" :value-to="$dateTo" :allow-open-end="true" :floating="true" />
                </div>
                <flux:button type="submit" variant="filled">{{ __('Apply') }}</flux:button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Open') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $totalOpen }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Assigned') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $totalAssigned }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Closed') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $totalClosed }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Average response time') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $formatMinutes($avgResponseMinutes) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Average satisfaction') }}</p>
                @if ($avgSatisfaction !== null)
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-2xl font-semibold text-gray-800 dark:text-white">{{ $avgSatisfaction }}</span>
                        <span class="flex items-center gap-0.5 text-amber-400">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg class="size-4 shrink-0 {{ $i <= round($avgSatisfaction) ? 'fill-current' : 'fill-none text-zinc-300 dark:text-neutral-600' }}" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>
                            @endfor
                        </span>
                    </div>
                    <p class="text-xs text-zinc-400 dark:text-neutral-500">{{ trans_choice(':count rating|:count ratings', $ratedCount, ['count' => $ratedCount]) }}</p>
                @else
                    <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ __('No data yet') }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('By module') }}</h3>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-neutral-700">
                @forelse ($byModule as $entry)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $entry['badge_classes'] }}">{{ $entry['label'] }}</span>
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">{{ $entry['count'] }}</span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('requests')]) }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <x-date-range-picker-script />
</x-layouts.app>
