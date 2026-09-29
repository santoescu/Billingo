<x-layouts.app :title="__('Loyalty history')">
    @include('partials.tittle', [
        'title' => __('Loyalty history'),
        'subheading' => __('Every point/stamp/cashback movement, earned or redeemed.'),
    ])

    <div class="-m-1.5 overflow-x-auto">
        <div class="p-1.5 min-w-full inline-block align-middle">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="overflow-hidden">
                    <table id="loyaltyTransactionsTable" class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Customer') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Mechanic') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Direction') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Amount') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Source') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            (function () {
                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function initTable() {
                    const table = initWorkflowDataTable('#loyaltyTransactionsTable', '#loyalty-transactions-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('movements')]) }}",
                        columns: [
                            { data: 'created_at', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: null, className: 'px-4 py-3 text-sm text-gray-800 dark:text-neutral-200', render: (data, type, row) => escapeHtml(row.customer_name ?? '—') },
                            { data: 'mechanic', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: 'direction', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: 'amount', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: 'source_type', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                        ],
                    });

                    fetch('{{ route('loyalty.transactions.data') }}', { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            table.clear();
                            table.rows.add(data.rows);
                            table.draw();
                        });
                }

                document.addEventListener('DOMContentLoaded', initTable);
                document.addEventListener('livewire:navigated', initTable);
            })();
        </script>
    @endpush
</x-layouts.app>
