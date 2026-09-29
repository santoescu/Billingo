<x-layouts.app :title="__('Loyalty customers')">
    @include('partials.tittle', [
        'title' => __('Loyalty customers'),
        'subheading' => __('Everyone enrolled in your loyalty program.'),
    ])

    <div class="mb-4 flex justify-end">
        <flux:button type="button" variant="filled" icon="plus" data-hs-overlay="#loyalty-customer-add-modal">
            {{ __('Add customer') }}
        </flux:button>
    </div>

    <div class="-m-1.5 overflow-x-auto">
        <div class="p-1.5 min-w-full inline-block align-middle">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="overflow-hidden">
                    <table id="loyaltyCustomersTable" class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Points') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Stamps') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Cashback') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Tier') }}</th>
                                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Enrolled') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    <div id="loyalty-customer-add-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Add customer') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#loyalty-customer-add-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('The client must already exist in your client list.') }}</p>
                    <div class="relative">
                        <flux:input id="loyalty-client-search" :label="__('Search existing client')" placeholder="{{ __('Name or identification') }}" autocomplete="off" />
                        <div id="loyalty-client-search-results" class="hidden absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"></div>
                    </div>
                </div>
                <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                    <flux:button type="button" variant="ghost" data-hs-overlay="#loyalty-customer-add-modal">{{ __('Cancel') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                let table = null;

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function cell(value, className = 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400') {
                    return (data, type, row) => `<a href="${row.urls.show}" class="${className} block">${value(row)}</a>`;
                }

                function initTable() {
                    table = initWorkflowDataTable('#loyaltyCustomersTable', '#loyalty-customers-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('customers')]) }}",
                        columns: [
                            { data: null, className: 'p-0', render: cell((row) => escapeHtml(row.identificacion), 'px-4 py-3 text-sm text-accent hover:underline') },
                            { data: null, className: 'p-0', render: cell((row) => escapeHtml(row.name ?? '—')) },
                            { data: null, className: 'p-0', render: cell((row) => row.points_balance) },
                            { data: null, className: 'p-0', render: cell((row) => row.stamps_count) },
                            { data: null, className: 'p-0', render: cell((row) => row.cashback_balance) },
                            { data: null, className: 'p-0', render: cell((row) => escapeHtml(row.current_tier_name ?? '—')) },
                            { data: null, className: 'p-0', render: cell((row) => row.enrolled_at) },
                        ],
                    });

                    fetch('{{ route('loyalty.customers.data') }}', { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            table.clear();
                            table.rows.add(data.rows);
                            table.draw();
                        });
                }

                let clientSearchTimeout = null;
                const clientSearchInput = document.getElementById('loyalty-client-search');
                const clientSearchResults = document.getElementById('loyalty-client-search-results');

                function renderClientResults(clients) {
                    if (! clients.length) {
                        clientSearchResults.innerHTML = `
                            <p class="p-3 text-sm text-zinc-500 dark:text-neutral-400">${"{{ __('No matches found.') }}"}</p>
                            <a href="{{ route('clients.index') }}" class="block px-3 pb-3 text-sm text-accent hover:underline">${"{{ __('Create the client first from Clients.') }}"}</a>
                        `;
                        clientSearchResults.classList.remove('hidden');
                        return;
                    }

                    clientSearchResults.innerHTML = clients.map((client) => `
                        <button type="button" class="loyalty-client-result w-full text-start px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700 flex items-center justify-between gap-2" data-identificacion="${escapeHtml(client.identificacion)}" data-enrolled="${client.enrolled ? '1' : ''}" data-url="${client.url ?? ''}">
                            <span>
                                <span class="block text-gray-800 dark:text-white">${escapeHtml(client.name)}</span>
                                <span class="block text-xs text-zinc-500 dark:text-neutral-400">${escapeHtml(client.identificacion)}</span>
                            </span>
                            ${client.enrolled ? `<span class="shrink-0 text-xs text-emerald-600 dark:text-emerald-400">${"{{ __('Already enrolled') }}"}</span>` : ''}
                        </button>
                    `).join('');
                    clientSearchResults.classList.remove('hidden');
                }

                clientSearchInput?.addEventListener('input', function () {
                    clearTimeout(clientSearchTimeout);
                    const query = this.value.trim();

                    if (query.length < 2) {
                        clientSearchResults.classList.add('hidden');
                        return;
                    }

                    clientSearchTimeout = setTimeout(async () => {
                        const response = await fetch(`{{ route('loyalty.customers.client-search') }}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        renderClientResults(data.clients ?? []);
                    }, 350);
                });

                clientSearchResults?.addEventListener('click', async function (event) {
                    const button = event.target.closest('.loyalty-client-result');
                    if (! button) return;

                    if (button.dataset.enrolled) {
                        window.location.href = button.dataset.url;
                        return;
                    }

                    const response = await fetch('{{ route('loyalty.customers.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ identificacion: button.dataset.identificacion }),
                    });
                    const data = await response.json();

                    if (! response.ok) {
                        window.appConfirmDialog?.notify(data.message || '{{ __('Could not add the customer.') }}', '{{ __('Add customer') }}');
                        return;
                    }

                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                });

                document.addEventListener('click', function (event) {
                    if (clientSearchResults && ! event.target.closest('#loyalty-client-search-results') && event.target !== clientSearchInput) {
                        clientSearchResults.classList.add('hidden');
                    }
                });

                document.addEventListener('DOMContentLoaded', initTable);
                document.addEventListener('livewire:navigated', initTable);
            })();
        </script>
    @endpush
</x-layouts.app>
