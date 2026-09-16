<x-layouts.app :title="__('Referral program')">
    @include('partials.tittle', [
        'title' => __('Referral program'),
        'subheading' => __('Who is actually referring, how many of their referrals became paying companies, and how much has been paid in commission and given in discounts.'),
    ])

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Total referred companies') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalReferred) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('With contract') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalWithContract) }}</div>
            <div class="text-xs text-neutral-400 mt-1">{{ $totalReferred > 0 ? number_format($totalWithContract / $totalReferred * 100, 1) : 0 }}% {{ __('of sent') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Total contracted') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalContracted, 2, '.', ',') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase">{{ __('Total commission paid') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalCommission, 2, '.', ',') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-4">
            <div class="text-xs font-medium text-green-600 dark:text-green-400 uppercase">{{ __('Total discount given') }}</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ number_format($totalDiscountGiven, 2, '.', ',') }}</div>
        </div>
    </div>

    <div class="-m-1.5 overflow-x-auto">
        <div class="p-1.5 min-w-full inline-block align-middle">
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Referrer') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Companies referred') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('With contract') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Total contracted') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Commission earned') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $row['referred_count'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $row['converted_count'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format($row['total_contracted'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ number_format($row['total_commission'], 2, '.', ',') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('referrals')]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
