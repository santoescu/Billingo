<x-layouts.app :title="__('Sales')">
    @include('partials.tittle', [
        'title' => __('Sales'),
        'subheading' => __('Sales made through the point of sale, electronic or not.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <div class="flex gap-2">
                            <button type="button" id="pos-sales-refresh-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Refresh') }}" title="{{ __('Refresh') }}" onclick="loadPosSalesTable()">
                                <svg id="pos-sales-refresh-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                            </button>

                            <a href="{{ route('pos.create') }}">
                                <flux:button type="button" variant="primary" icon="plus">{{ __('New sale') }}</flux:button>
                            </a>
                        </div>
                    </div>

                    <div class="overflow-hidden">
                        <table id="posSalesTable" class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Numeral') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Customer') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Total') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @include('pos.sales.partials.rows')
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            /**
             * La tabla de ventas ya no viene lista en el HTML inicial (ver
             * PosController::sales()) -- se pide por AJAX apenas carga la
             * página para no bloquear el primer render con la consulta
             * completa del historial, igual que hace loadDocumentsTable()
             * en documents/index.blade.php.
             * @returns {void}
             */
            function loadPosSalesTable() {
                const tbody = document.querySelector('#posSalesTable tbody');
                if (! tbody) return;

                const refreshBtn = document.getElementById('pos-sales-refresh-btn');
                const refreshIcon = document.getElementById('pos-sales-refresh-icon');
                if (refreshBtn) refreshBtn.disabled = true;
                if (refreshIcon) refreshIcon.classList.add('animate-spin');

                fetch('{{ route('pos.sales.data') }}', { headers: { Accept: 'application/json' } })
                    .then((response) => response.json())
                    .then((data) => {
                        tbody.innerHTML = data.rows_html;

                        const table = initWorkflowDataTable('#posSalesTable', '#hs-table-with-pagination-search', {
                            emptyTable: "{{ __('There are no registered :name.', ['name' => __('Sales')]) }}",
                        });
                        table.order([]).draw();
                    })
                    .finally(() => {
                        if (refreshBtn) refreshBtn.disabled = false;
                        if (refreshIcon) refreshIcon.classList.remove('animate-spin');
                    });
            }

            window.loadPosSalesTable = loadPosSalesTable;

            document.addEventListener('DOMContentLoaded', loadPosSalesTable);
            document.addEventListener('livewire:navigated', loadPosSalesTable);
        </script>
    @endpush
</x-layouts.app>
