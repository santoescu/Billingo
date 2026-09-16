<x-layouts.app :title="__('Email engagement')">
    @include('partials.tittle', [
        'title' => __('Email engagement'),
        'subheading' => __('How many people open the emails we send on behalf of companies, and click the Billingo link in the footer.'),
    ])

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Sent') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalSent) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Delivered') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalDelivered) }}</div>
            <div class="text-xs text-neutral-400 mt-1">{{ $totalSent > 0 ? number_format($totalDelivered / $totalSent * 100, 1) : 0 }}% {{ __('of sent') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Opened') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalOpened) }}</div>
            <div class="text-xs text-neutral-400 mt-1">{{ $totalDelivered > 0 ? number_format($totalOpened / $totalDelivered * 100, 1) : 0 }}% {{ __('of delivered') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-purple-600 dark:text-purple-400 uppercase">{{ __('Clicked') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalClicked) }}</div>
            <div class="text-xs text-neutral-400 mt-1">{{ $totalOpened > 0 ? number_format($totalClicked / $totalOpened * 100, 1) : 0 }}% {{ __('of opened') }}</div>
        </div>
    </div>

    <div class="-m-1.5 overflow-x-auto">
        <div class="p-1.5 min-w-full inline-block align-middle">
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Recipient email') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Link') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($recentClicks as $log)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">{{ $log->clicked_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200">{{ $companyNames->get((string) $log->company_id)?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 truncate">{{ $log->to }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 truncate">{{ $log->clicked_link ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('clicks')]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
