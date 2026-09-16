<x-layouts.app :title="__('Leads')">
    @include('partials.tittle', [
        'title' => __('Leads'),
        'subheading' => __('Import prospects, send cold outreach emails, and track replies to win new customers for Billingo.'),
    ])

    @if (session('leads-errors'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach (session('leads-errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('leads-imported'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {{ __(':created imported, :skipped skipped (duplicate email or empty).', ['created' => session('leads-imported')['created'], 'skipped' => session('leads-imported')['skipped']]) }}
        </div>
    @endif

    @if (session('leads-sent'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {{ __('Email sent to :count leads.', ['count' => session('leads-sent')]) }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        <flux:button type="button" variant="filled" icon="arrow-up-tray" data-hs-overlay="#leads-import-modal">
            {{ __('Import leads') }}
        </flux:button>
    </div>

    <form action="{{ route('admin.leads.send') }}" method="POST" id="leads-send-form">
        @csrf
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Template') }}</label>
                <select name="variant" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}'>
                    @foreach ($variants as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-64">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Replies go to') }}</label>
                <flux:input type="email" name="reply_to" value="{{ old('reply_to') }}" required placeholder="tu-correo@ejemplo.com" />
            </div>
            <flux:button type="submit" variant="primary">{{ __('Send to selected') }}</flux:button>
        </div>

        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
            <div class="py-3 px-4 flex flex-wrap justify-between items-center gap-4">
                <div class="relative max-w-xs">
                    <label class="sr-only">{{ __('Search') }}</label>
                    <flux:input type="text" id="leads-search" placeholder="{{ __('Search') }}" autocomplete="off" />
                </div>

                <div class="flex items-center gap-2">
                    <div class="w-48">
                        <select id="leads-status-filter" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All statuses')) !!}'>
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($statuses as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" id="leads-refresh-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Refresh') }}" title="{{ __('Refresh') }}" onclick="window.loadLeadsTable()">
                        <svg id="leads-refresh-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-lg">
                <table id="leadsTable" class="w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 w-8">
                                <input type="checkbox" id="leads-select-all" class="shrink-0 size-4 rounded-sm border-gray-300 accent-accent focus:ring-accent dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-offset-neutral-800">
                            </th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('NIT') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Recipient email') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('City') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
                </table>
            </div>
        </div>
    </form>

    <div id="leads-import-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form action="{{ route('admin.leads.import') }}" method="POST" enctype="multipart/form-data" id="leads-import-form">
                    @csrf
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Import leads') }}</h3>
                        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#leads-import-modal">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-4 space-y-3">
                        <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('Upload an Excel (.xlsx) or CSV file with your prospects.') }}</p>

                        {{-- Mismo widget de arrastrar/soltar (Preline HSFileUpload, "singleton")
                             que ya usa el certificado digital en companies/create.blade.php -- un
                             solo archivo, reemplaza el anterior si se elige otro, sincronizado al
                             <input type="file"> real que se manda en el submit. --}}
                        <div id="leads-import-upload" data-hs-file-upload='{
                                "url": "#",
                                "autoProcessQueue": false,
                                "singleton": true,
                                "autoHideTrigger": true
                            }'>
                            <template data-hs-file-upload-preview>
                                <div class="p-3 bg-white border border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700">
                                    <div class="mb-1 flex justify-between items-center">
                                        <div class="flex items-center gap-x-3">
                                            <span class="size-8 shrink-0 flex justify-center items-center bg-gray-100 text-gray-500 rounded-lg dark:bg-neutral-700 dark:text-neutral-400" data-hs-file-upload-file-icon>
                                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                                    <span data-hs-file-upload-file-name></span>.<span data-hs-file-upload-file-ext></span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-neutral-400" data-hs-file-upload-file-size></p>
                                            </div>
                                        </div>
                                        <button type="button" class="shrink-0 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" data-hs-file-upload-remove title="{{ __('Remove') }}">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-x-3 whitespace-nowrap">
                                        <div class="flex w-full h-2 bg-gray-100 rounded-full overflow-hidden dark:bg-neutral-700" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-hs-file-upload-progress-bar>
                                            <div class="flex flex-col justify-center rounded-full overflow-hidden bg-accent text-xs text-white text-center whitespace-nowrap transition-all duration-500 hs-file-upload-complete:bg-green-500" style="width: 0" data-hs-file-upload-progress-bar-pane></div>
                                        </div>
                                        <div class="w-10 text-end">
                                            <span class="text-sm text-gray-800 dark:text-white">
                                                <span data-hs-file-upload-progress-bar-value>0</span>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="cursor-pointer h-20 flex items-center justify-center gap-2 border border-dashed border-gray-300 rounded-lg text-center dark:border-neutral-600" data-hs-file-upload-trigger>
                                <svg class="shrink-0 size-5 text-gray-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 13v8"/><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="m8 17 4-4 4 4"/></svg>
                                <p class="text-sm text-gray-600 dark:text-neutral-400">
                                    {{ __('Drop your file here or') }} <span class="font-semibold text-accent">{{ __('browse') }}</span>
                                </p>
                            </div>

                            <div class="mt-2 space-y-2 empty:mt-0" data-hs-file-upload-previews></div>
                        </div>

                        <input type="file" name="file" id="leads-import-file-input" accept=".csv,.xlsx,.xls" class="hidden">
                    </div>
                    <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                        <flux:button type="button" variant="ghost" data-hs-overlay="#leads-import-modal">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" id="leads-import-submit-btn">
                            <span id="leads-import-submit-label">{{ __('Import') }}</span>
                            <span id="leads-import-submit-spinner" class="hidden">
                                <span class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                    {{ __('Importing...') }}
                                </span>
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            (function () {
                // Mismo patrón que received-documents/index.blade.php: la tabla se llena por AJAX
                // (ver AdminLeadController::data()), DataTables arma cada <tr> a partir de
                // columns.render, el backend solo manda el array de leads en JSON. A diferencia de
                // documentos recibidos, acá no hay filtros de servidor (fecha, proveedor, etc.) que
                // justifiquen mandar query params -- todos los leads se traen de una y DataTables
                // pagina/busca del lado del cliente.
                let leadsTable = null;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const i18n = {
                    delete: @json(__('Delete')),
                    notContacted: @json(__('Not contacted')),
                    confirmDelete: @json(__('This action cannot be undone.')),
                };

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function renderSelect(row) {
                    return `<input type="checkbox" name="lead_ids[]" value="${escapeHtml(row.id)}" form="leads-send-form" class="lead-checkbox shrink-0 size-4 rounded-sm border-gray-300 accent-accent focus:ring-accent dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-offset-neutral-800">`;
                }

                function renderCompany(row, type) {
                    if (type === 'filter' || type === 'sort') {
                        return `${row.razon_social ?? ''} ${row.meta ?? ''}`.trim();
                    }

                    let html = `<div class="text-gray-800 dark:text-neutral-200">${escapeHtml(row.razon_social ?? '—')}</div>`;
                    if (row.meta) {
                        html += `<div class="text-xs font-normal text-neutral-400">${escapeHtml(row.meta)}</div>`;
                    }

                    return html;
                }

                function renderStatus(row) {
                    let html = `<span class="rounded-md px-2 py-0.5 text-xs font-medium ${row.status_badge_classes}">${escapeHtml(row.status_label)}</span>`;
                    if (row.sent_at) {
                        html += `<div class="text-xs text-neutral-400 mt-1">${escapeHtml(row.sent_at)}</div>`;
                    }
                    if (row.status_reason) {
                        html += `<div class="text-xs text-red-600 dark:text-red-400 mt-1">${escapeHtml(row.status_reason)}</div>`;
                    }

                    return html;
                }

                function renderActions(row) {
                    return `<form action="${row.urls.destroy}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '${i18n.confirmDelete}');" class="inline">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.delete}" title="${i18n.delete}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                        </button>
                    </form>`;
                }

                function initLeadsTable() {
                    leadsTable = initWorkflowDataTable('#leadsTable', '#leads-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('leads')]) }}",
                        columns: [
                            { data: null, orderable: false, className: 'px-4 py-3', render: (data, type, row) => renderSelect(row) },
                            { data: null, className: 'px-4 py-3 text-sm', render: (data, type, row) => renderCompany(row, type) },
                            { data: 'nit', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data) => escapeHtml(data ?? '—') },
                            { data: 'email', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 truncate' },
                            { data: 'city', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data) => escapeHtml(data ?? '—') },
                            { data: null, className: 'px-4 py-3 text-sm', render: (data, type, row) => renderStatus(row) },
                            { data: null, orderable: false, className: 'px-4 py-3 text-end text-sm', render: (data, type, row) => renderActions(row) },
                            { data: 'status_code', visible: false },
                        ],
                    });
                    leadsTable.order([]).draw();
                }

                /**
                 * Filtro de estado (no contactado/enviado/entregado/abierto/click/rebotado/spam)
                 * -- compara contra la columna oculta "status_code" (índice 7, ver
                 * initLeadsTable()) con match exacto, no la columna visible que muestra el label
                 * ya traducido.
                 */
                function bindLeadsStatusFilter() {
                    document.getElementById('leads-status-filter')?.addEventListener('change', function () {
                        const value = this.value;
                        leadsTable?.column(7).search(value ? `^${value}$` : '', true, false).draw();
                    });
                }

                function loadLeadsTable() {
                    const tbody = document.querySelector('#leadsTable tbody');
                    if (! tbody) return;

                    const refreshBtn = document.getElementById('leads-refresh-btn');
                    const refreshIcon = document.getElementById('leads-refresh-icon');
                    if (refreshBtn) refreshBtn.disabled = true;
                    if (refreshIcon) refreshIcon.classList.add('animate-spin');

                    fetch('{{ route('admin.leads.data') }}', { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (! leadsTable) {
                                initLeadsTable();
                            }

                            leadsTable.clear();
                            leadsTable.rows.add(data.rows);
                            leadsTable.order([]).draw();

                            if (window.HSOverlay) HSOverlay.autoInit();
                        })
                        .finally(() => {
                            if (refreshBtn) refreshBtn.disabled = false;
                            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
                        });
                }

                // El checkbox de "seleccionar todo" solo marca las filas visibles en la página
                // actual de DataTables (mismo criterio que cualquier tabla paginada: "todo" es
                // "todo lo que se ve", no todos los leads que existan).
                document.getElementById('leads-select-all')?.addEventListener('click', function () {
                    document.querySelectorAll('#leadsTable tbody .lead-checkbox').forEach((checkbox) => {
                        checkbox.checked = this.checked;
                    });
                });

                /**
                 * Antes de mandar, se pide el asunto+cuerpo real que va a recibir el primer lead
                 * seleccionado (ver AdminLeadController::preview()) y se muestra dentro del mismo
                 * modal de confirmación -- así se ve exactamente lo que se está por enviar, no
                 * solo un mensaje genérico de "¿enviar?".
                 */
                function bindLeadsSendForm() {
                    const form = document.getElementById('leads-send-form');
                    if (! form) return;

                    form.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const checked = form.querySelectorAll('.lead-checkbox:checked');
                        if (! checked.length) {
                            window.appConfirmDialog.notify(@json(__('Select at least one lead.')));
                            return;
                        }

                        const variant = form.querySelector('select[name="variant"]').value;
                        const leadId = checked[0].value;

                        fetch(`{{ route('admin.leads.preview') }}?variant=${encodeURIComponent(variant)}&lead_id=${encodeURIComponent(leadId)}`, { headers: { Accept: 'application/json' } })
                            .then((response) => response.json())
                            .then((data) => {
                                const intro = checked.length === 1
                                    ? @json(__('Send this email to :count lead?'))
                                    : @json(__('Send this email to :count leads?'));
                                const message = intro.replace(':count', checked.length)
                                    + '\n\n' + @json(__('Subject')) + ': ' + data.subject
                                    + '\n\n' + data.body;

                                window.appConfirmDialog.ask(message, @json(__('Confirm before sending')), { variant: 'primary', label: @json(__('Send')) })
                                    .then((ok) => {
                                        if (ok) form.submit();
                                    });
                            });
                    });
                }

                window.loadLeadsTable = loadLeadsTable;

                bindLeadsSendForm();
                bindLeadsStatusFilter();

                document.addEventListener('DOMContentLoaded', loadLeadsTable);
                document.addEventListener('livewire:navigated', loadLeadsTable);
            })();
        </script>

        <script>
            /**
             * Mismo mecanismo que companies/create.blade.php para el certificado digital:
             * engancha el widget de arrastrar/soltar (Preline HSFileUpload, "singleton") con el
             * <input type="file"> real que se manda en el submit del form de importar.
             */
            document.addEventListener('DOMContentLoaded', function () {
                const uploadEl = document.getElementById('leads-import-upload');
                const fileInput = document.getElementById('leads-import-file-input');

                if (window.HSFileUpload) {
                    HSFileUpload.autoInit();
                }

                const instance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
                if (instance?.element?.dropzone) {
                    instance.element.dropzone.on('addedfile', function (file) {
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        fileInput.files = transfer.files;

                        const previewElement = file.previewElement;
                        if (previewElement) {
                            previewElement.classList.add('complete');
                            const bar = previewElement.querySelector('[data-hs-file-upload-progress-bar]');
                            const pane = previewElement.querySelector('[data-hs-file-upload-progress-bar-pane]');
                            const value = previewElement.querySelector('[data-hs-file-upload-progress-bar-value]');
                            if (bar) bar.setAttribute('aria-valuenow', '100');
                            if (pane) pane.style.width = '100%';
                            if (value) value.textContent = '100';
                        }
                    });
                }

                // Al enviar el import (puede tardar varios segundos con archivos grandes, ver
                // AdminLeadController::import()), se deshabilita el botón y se muestra el spinner
                // -- es un submit normal (recarga de página), no fetch, así que basta con dejarlo
                // así hasta que la página navegue.
                document.getElementById('leads-import-form')?.addEventListener('submit', function () {
                    document.getElementById('leads-import-submit-label').classList.add('hidden');
                    document.getElementById('leads-import-submit-spinner').classList.remove('hidden');
                    document.getElementById('leads-import-submit-btn').disabled = true;
                });
            });
        </script>
    @endpush
</x-layouts.app>
