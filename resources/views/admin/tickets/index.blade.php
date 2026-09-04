<x-layouts.app :title="__('Support tickets')">
    @include('partials.tittle', [
        'title' => __('Support tickets'),
        'subheading' => __('Requests, complaints and claims filed by companies, across all of them.'),
    ])

    @include('admin.tickets.partials.tabs', ['activeTab' => 'tickets'])

    <div class="flex flex-col gap-4">
        <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Status') }}</label>
                <select name="status" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    <option value="abierto" @selected(($filters['status'] ?? '') === 'abierto')>{{ __('Open') }}</option>
                    <option value="asignado" @selected(($filters['status'] ?? '') === 'asignado')>{{ __('Assigned') }}</option>
                    <option value="cerrado" @selected(($filters['status'] ?? '') === 'cerrado')>{{ __('Closed') }}</option>
                </select>
            </div>

            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Module') }}</label>
                <select name="module" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    <option value="general" @selected(($filters['module'] ?? '') === 'general')>{{ __('General') }}</option>
                    @foreach ($modules as $key => $module)
                        <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $module['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-56">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Company') }}</label>
                <select name="company_id" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::searchable(__('All'), __('Search...')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($companies as $filterCompany)
                        <option value="{{ $filterCompany->_id }}" @selected(($filters['company_id'] ?? '') === (string) $filterCompany->_id)>{{ $filterCompany->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Assigned to') }}</label>
                <select name="assigned_to" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($staffUsers as $staffUser)
                        <option value="{{ $staffUser->_id }}" @selected(($filters['assigned_to'] ?? '') === (string) $staffUser->_id)>{{ $staffUser->name }}</option>
                    @endforeach
                </select>
            </div>

            @if (array_filter($filters))
                <a href="{{ route('admin.tickets.index') }}" class="text-sm text-zinc-500 hover:text-accent dark:text-neutral-400">{{ __('Clear filters') }}</a>
            @endif
        </form>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <a href="{{ route('admin.tickets.create') }}">
                            <flux:button type="button" variant="primary" icon="plus">{{ __('New ticket') }}</flux:button>
                        </a>
                    </div>

                    <div class="overflow-hidden">
                        <table class="w-full min-w-[960px] table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="ticketsTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Module') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subject') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Priority') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Assigned to') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @include('admin.tickets.partials.rows')
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
             * La tabla de tickets ya no viene lista en el HTML inicial (ver
             * AdminSupportTicketController::index()) -- se pide por AJAX
             * apenas carga la página para no bloquear el primer render con
             * la consulta de todos los tickets del sistema, igual que hace
             * loadDocumentsTable() en documents/index.blade.php.
             * "window.location.search" reenvía los mismos filtros que ya
             * están en la URL (los selects siguen recargando la página vía
             * GET normal), así data() aplica exactamente el mismo filtro
             * que el formulario está mostrando.
             * @returns {void}
             */
            function loadAdminTicketsTable() {
                const tbody = document.querySelector('#ticketsTable tbody');
                if (! tbody) return;

                fetch('{{ route('admin.tickets.data') }}' + window.location.search, { headers: { Accept: 'application/json' } })
                    .then((response) => response.json())
                    .then((data) => {
                        tbody.innerHTML = data.rows_html;

                        const table = initWorkflowDataTable('#ticketsTable', '#hs-table-with-pagination-search', {
                            emptyTable: "{{ __('There are no registered :name.', ['name' => __('requests')]) }}",
                        });
                        table.order([]).draw();
                    });
            }

            function initAdminTicketsPage() {
                loadAdminTicketsTable();

                document.querySelectorAll('.ticket-filter-auto-submit').forEach((select) => {
                    if (select.dataset.bound) return;
                    select.dataset.bound = 'true';
                    select.addEventListener('change', () => select.closest('form')?.submit());
                });
            }

            document.addEventListener('DOMContentLoaded', initAdminTicketsPage);
            document.addEventListener('livewire:navigated', initAdminTicketsPage);
        </script>
    @endpush
</x-layouts.app>
