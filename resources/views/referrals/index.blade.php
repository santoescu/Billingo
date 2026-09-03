<x-layouts.app :title="__('My commissions')">
    @include('partials.tittle', [
        'title' => __('My commissions'),
        'subheading' => __('Companies you brought in, and what you earn from each contract.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Sales') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $contracts->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Total commission') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($totalCommission, 2, '.', ',') }}</p>
            </div>
        </div>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Price') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Commission %') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Commission') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($contracts as $contract)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">
                                            {{ collect($contract->company_ids ?? [])->map(fn ($id) => $companyNames->get((string) $id)?->name)->filter()->join(', ') ?: '—' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->starts_at?->format('Y-m-d') }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->price !== null ? number_format($contract->price, 2, '.', ',') : '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->commission_percentage !== null ? rtrim(rtrim(number_format($contract->commission_percentage, 2), '0'), '.') . '%' : '—' }}</td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $contract->commission_amount !== null ? number_format($contract->commission_amount, 2, '.', ',') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('sales')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
