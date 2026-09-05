<x-layouts.app :title="__('Issued documents')">
    @include('partials.tittle', [
        'title' => __('Issued documents'),
        'subheading' => __('Invoices, credit notes and debit notes issued for this company.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
            <div class="py-3 px-4 flex justify-between items-center gap-4">
                <div class="relative max-w-xs">
                    <label class="sr-only">{{ __('Search') }}</label>
                    <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                </div>

                <div class="flex gap-2">
                    <button type="button" id="documents-refresh-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Refresh') }}" title="{{ __('Refresh') }}" onclick="loadDocumentsTable()">
                        <svg id="documents-refresh-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    </button>

                    <a href="{{ route('documents.create') }}" id="new-document-btn">
                        <flux:button type="button" variant="primary" icon="plus">{{ __('New document') }}</flux:button>
                    </a>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-lg">
            <table id="documentsTable" class="w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                <thead class="bg-gray-50 dark:bg-neutral-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Numeral') }}</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Document type') }}</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Customer') }}</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Total') }}</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
            </table>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')
    @include('documents.partials.retry-modal')

    @push('scripts')
        <script>
            (function () {
                // Instancia viva de la tabla -- se crea una sola vez (ver loadDocumentsTable())
                // y de ahí en adelante cada refresh solo le reemplaza las filas
                // (table.clear()/rows.add()/draw()) con datos, nunca con HTML armado en el
                // backend: DataTables arma cada <tr> a partir de columns.render, el backend
                // solo manda el array de documentos en JSON.
                let documentsTable = null;
                let documentTypeLabels = {};

                const i18n = {
                    viewPdf: @json(__('View PDF')),
                    view: @json(__('View')),
                    moreActions: @json(__('More actions')),
                    validate: @json(__('Validate')),
                    correctAndResend: @json(__('Correct and resend')),
                };

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function renderDate(row) {
                    return `<div>EMI: ${escapeHtml(row.emi ?? '—')}</div><div>EXP: ${escapeHtml(row.exp ?? '—')}</div>`;
                }

                function renderCustomer(row) {
                    const dv = row.customer_dv ? '-' + escapeHtml(row.customer_dv) : '';
                    return `<div class="text-gray-800 dark:text-neutral-200">${escapeHtml(row.customer_name ?? '—')}</div>`
                        + `<div>${escapeHtml(row.customer_identification ?? '—')}${dv}</div>`;
                }

                function renderStatus(row) {
                    return `<span class="rounded-md px-2 py-0.5 text-xs font-medium ${row.status_badge_classes}">${escapeHtml(row.status_label)}</span>`;
                }

                function renderActions(row) {
                    let html = '<div class="flex justify-end items-center gap-3">';

                    if (row.has_uuid) {
                        html += `<a href="${row.urls.preview}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.viewPdf}" title="${i18n.viewPdf}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        </a>`;
                    }

                    html += `<a href="${row.urls.show}" class="document-view-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.view}">
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>`;

                    if (row.can_retry) {
                        html += `<div class="hs-dropdown [--auto-close:true] relative inline-flex">
                            <button type="button" class="hs-dropdown-toggle flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.moreActions}">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            </button>
                            <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration fixed z-50 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 flex items-center gap-1 dark:bg-neutral-800 dark:border-neutral-700">
                                <button type="button" class="document-retry-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" data-url="${row.urls.retry}" aria-label="${i18n.validate}" title="${i18n.validate}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </button>`;

                        if (row.is_rejected) {
                            html += `<a href="${row.urls.edit}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.correctAndResend}" title="${i18n.correctAndResend}">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                    <path d="m15 5 4 4"></path>
                                </svg>
                            </a>`;
                        }

                        html += '</div></div>';
                    }

                    html += '</div>';
                    return html;
                }

                /**
                 * table.order([]).draw(): sin esto, DataTables reordena por la
                 * primera columna visible (la fecha, como texto) y pisa el orden
                 * más nuevo -> más viejo que ya viene armado desde el backend.
                 */
                function initDocumentsTable() {
                    documentsTable = initWorkflowDataTable('#documentsTable', '#hs-table-with-pagination-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('Issued documents')]) }}",
                        columns: [
                            { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderDate(row) },
                            { data: 'numeral', className: 'px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200' },
                            { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => escapeHtml(documentTypeLabels[row.tipo_documento] ?? row.tipo_documento) },
                            { data: null, className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderCustomer(row) },
                            { data: 'total_formatted', className: 'px-4 py-4 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: null, className: 'px-4 py-4 text-sm', render: (data, type, row) => renderStatus(row) },
                            { data: null, orderable: false, className: 'px-4 py-4 text-end text-sm', render: (data, type, row) => renderActions(row) },
                        ],
                    });
                    documentsTable.order([]).draw();
                }

                /**
                 * La tabla de documentos ya no viene lista en el HTML inicial
                 * (ver DocumentoEmitidoController::index()) -- se pide por AJAX
                 * apenas carga la página para no bloquear el primer render con
                 * la consulta completa del historial. El backend manda el JSON
                 * crudo (data.rows) y esta función arma las filas -- ver las
                 * funciones render*() de arriba.
                 * @returns {void}
                 */
                function loadDocumentsTable() {
                    const tbody = document.querySelector('#documentsTable tbody');
                    if (! tbody) return;

                    const refreshBtn = document.getElementById('documents-refresh-btn');
                    const refreshIcon = document.getElementById('documents-refresh-icon');
                    if (refreshBtn) refreshBtn.disabled = true;
                    if (refreshIcon) refreshIcon.classList.add('animate-spin');

                    fetch('{{ route('documents.data') }}', { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (! documentsTable) {
                                initDocumentsTable();
                            }

                            documentTypeLabels = data.document_type_labels;
                            documentsTable.clear();
                            documentsTable.rows.add(data.rows);
                            documentsTable.order([]).draw();

                            if (window.HSOverlay) HSOverlay.autoInit();
                        })
                        .finally(() => {
                            if (refreshBtn) refreshBtn.disabled = false;
                            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
                        });
                }

                /**
                 * Cierra (oculta) un menú de "más acciones" ya abierto.
                 * @param {HTMLElement} wrapper El ".hs-dropdown" que lo contiene.
                 * @returns {void}
                 */
                function closeDocumentDropdown(wrapper) {
                    wrapper.classList.remove('open');
                    wrapper.querySelector(':scope > .hs-dropdown-menu')?.classList.add('hidden');
                }

                /**
                 * El botón de "más acciones" (⋮) se arma en JS (ver
                 * renderActions()) apenas llega la respuesta AJAX -- Preline
                 * inicializa sus ".hs-dropdown" recorriendo el documento una
                 * sola vez al cargar la página (y "new HSDropdown(el)" no
                 * queda confiable para nodos creados por DataTables dentro
                 * de un <tbody> que ya existía), así que se abre/cierra a
                 * mano, delegado sobre document.body igual que
                 * ".document-retry-btn" en documents/partials/retry-modal.blade.php.
                 * El menú usa "position: fixed" con top/left calculados a
                 * mano (getBoundingClientRect del botón) en vez de
                 * "absolute": el contenedor de la tabla tiene overflow-hidden
                 * (para que las esquinas redondeadas del borde recorten bien
                 * el thead/tbody), y un menú "absolute" quedaría cortado por
                 * ese mismo overflow -- "fixed" se posiciona respecto al
                 * viewport y lo esquiva.
                 * @returns {void}
                 */
                function initDocumentDropdowns() {
                    if (document.body.dataset.documentDropdownsBound === 'true') return;
                    document.body.dataset.documentDropdownsBound = 'true';

                    document.addEventListener('click', function (event) {
                        const toggle = event.target.closest('#documentsTable .hs-dropdown-toggle');

                        document.querySelectorAll('#documentsTable .hs-dropdown.open').forEach((wrapper) => {
                            if (! toggle || wrapper !== toggle.closest('.hs-dropdown')) {
                                closeDocumentDropdown(wrapper);
                            }
                        });

                        if (! toggle) return;

                        const wrapper = toggle.closest('.hs-dropdown');
                        const menu = wrapper.querySelector(':scope > .hs-dropdown-menu');
                        if (! menu) return;

                        const isOpen = wrapper.classList.toggle('open');
                        menu.classList.toggle('hidden', ! isOpen);

                        if (isOpen) {
                            const rect = toggle.getBoundingClientRect();
                            menu.style.top = `${rect.bottom + 8}px`;
                            menu.style.left = `${Math.max(8, rect.right - menu.offsetWidth)}px`;
                        }
                    });

                    // El menú es "fixed" respecto al viewport, no a la fila --
                    // si la página se desplaza mientras está abierto, ya no
                    // queda alineado con el botón, así que mejor cerrarlo.
                    window.addEventListener('scroll', function () {
                        document.querySelectorAll('#documentsTable .hs-dropdown.open').forEach(closeDocumentDropdown);
                    }, true);
                }

                initDocumentDropdowns();

                window.loadDocumentsTable = loadDocumentsTable;

                document.addEventListener('DOMContentLoaded', loadDocumentsTable);
                document.addEventListener('livewire:navigated', loadDocumentsTable);
            })();
        </script>
    @endpush
</x-layouts.app>
