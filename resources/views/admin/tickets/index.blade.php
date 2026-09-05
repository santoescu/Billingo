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

            <div class="overflow-hidden rounded-b-lg">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="ticketsTable">
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
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
            </table>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            // Instancia viva de la tabla -- se crea una sola vez en loadAdminTicketsTable() y
            // de ahí en adelante cada refresh solo le reemplaza las filas (clear/rows.add) en
            // vez de destruirla y reconstruirla (ver documents/index.blade.php para el porqué:
            // destroy() restaura el <tbody> al contenido del primer init).
            let ticketsTable = null;

            const moduleBadges = @json(
                collect($modules)->mapWithKeys(fn ($module, $key) => [
                    $key => ['name' => $module['name'], 'badge_classes' => $module['badge_classes'] ?? 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-200'],
                ])
            );

            function escapeHtmlTicketRow(value) {
                return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                })[char]);
            }

            function renderTicketDate(row) {
                const dot = row.is_unread_for_staff
                    ? `<span class="size-1.5 shrink-0 rounded-full bg-accent" title="{{ __('Unread') }}"></span>`
                    : '';
                return `<span class="inline-flex items-center gap-1.5">${dot}${escapeHtmlTicketRow(row.created_at)}</span>`;
            }

            function renderTicketCompany(row) {
                const leadBadge = row.is_lead
                    ? `<span class="ms-1 rounded-md bg-purple-100 px-1.5 py-0.5 text-[11px] font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Lead') }}</span>`
                    : '';
                return `${escapeHtmlTicketRow(row.company_name ?? '—')}${leadBadge}`;
            }

            function renderTicketModule(row) {
                if (row.module && row.module !== 'general' && moduleBadges[row.module]) {
                    const module = moduleBadges[row.module];
                    return `<span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium ${module.badge_classes}">${escapeHtmlTicketRow(module.name)}</span>`;
                }

                return `<span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ __('General') }}</span>`;
            }

            function renderTicketSubject(row) {
                const classes = row.is_unread_for_staff
                    ? 'px-4 py-4 text-sm break-words dark:text-neutral-200 font-semibold text-gray-900'
                    : 'px-4 py-4 text-sm break-words dark:text-neutral-200 font-medium text-gray-800';
                return { classes, html: escapeHtmlTicketRow(row.subject) };
            }

            function renderTicketBadge(label, classes) {
                return `<span class="rounded-md px-2 py-0.5 text-xs font-medium ${classes}">${escapeHtmlTicketRow(label)}</span>`;
            }

            function renderTicketActions(row) {
                return `<a href="${row.url}" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                </a>`;
            }

            /**
             * La tabla de tickets ya no viene lista en el HTML inicial (ver
             * AdminSupportTicketController::index()) -- se pide por AJAX
             * apenas carga la página para no bloquear el primer render con
             * la consulta de todos los tickets del sistema, igual que hace
             * loadDocumentsTable() en documents/index.blade.php.
             * "window.location.search" reenvía los mismos filtros que ya
             * están en la URL (los selects siguen recargando la página vía
             * GET normal), así data() aplica exactamente el mismo filtro
             * que el formulario está mostrando. El backend manda el JSON
             * crudo (data.rows) y las funciones render*() de arriba arman
             * cada celda.
             * @returns {void}
             */
            function loadAdminTicketsTable() {
                const tbody = document.querySelector('#ticketsTable tbody');
                if (! tbody) return;

                fetch('{{ route('admin.tickets.data') }}' + window.location.search, { headers: { Accept: 'application/json' } })
                    .then((response) => response.json())
                    .then((data) => {
                        if (! ticketsTable) {
                            ticketsTable = initWorkflowDataTable('#ticketsTable', '#hs-table-with-pagination-search', {
                                emptyTable: "{{ __('There are no registered :name.', ['name' => __('requests')]) }}",
                                columns: [
                                    { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderTicketDate(row) },
                                    { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderTicketCompany(row) },
                                    { data: null, className: 'px-4 py-4 text-sm', render: (data, type, row) => renderTicketModule(row) },
                                    {
                                        data: null,
                                        render: (data, type, row) => (type === 'display' ? renderTicketSubject(row).html : row.subject),
                                        createdCell: (cell, cellData, row) => { cell.className = renderTicketSubject(row).classes; },
                                    },
                                    { data: null, className: 'px-4 py-4 text-sm', render: (data, type, row) => renderTicketBadge(row.priority_label, row.priority_badge_classes) },
                                    { data: null, className: 'px-4 py-4 text-sm', render: (data, type, row) => renderTicketBadge(row.status_label, row.status_badge_classes) },
                                    { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => escapeHtmlTicketRow(row.assignee_name ?? @json(__('Unassigned'))) },
                                    { data: null, orderable: false, className: 'px-4 py-4 text-end text-sm', render: (data, type, row) => renderTicketActions(row) },
                                ],
                            });
                        }

                        ticketsTable.clear();
                        ticketsTable.rows.add(data.rows);
                        ticketsTable.order([]).draw();
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
