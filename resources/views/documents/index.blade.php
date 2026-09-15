<x-layouts.app :title="__('Issued documents')">
    @include('partials.tittle', [
        'title' => __('Issued documents'),
        'subheading' => __('Invoices, credit notes and debit notes issued for this company.'),
    ])

    @php
        $documentsDefaultFrom = now()->startOfMonth()->format('Y-m-d');
        $documentsDefaultTo = now()->format('Y-m-d');
        $documentsFilterTypeLabels = [
            '01' => __('Electronic sales invoice'),
            '02' => __('Electronic sales invoice (export)'),
            '03' => __('Electronic transmission instrument (type 03)'),
            '04' => __('Electronic sales invoice (type 04)'),
            '91' => __('Credit note'),
            '92' => __('Debit note'),
        ];
    @endphp

    <div class="flex flex-col gap-4">
        <div id="documents-filters" class="hidden flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-date-range-picker name-from="documents_from" name-to="documents_to" :label="__('Date range')" :value-from="$documentsDefaultFrom" :value-to="$documentsDefaultTo" :allow-open-end="true" :floating="true" align="left" />
            </div>
            <div class="w-56 relative">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400" for="documents-filter-customer">{{ __('Customer') }}</label>
                <input type="text" id="documents-filter-customer" autocomplete="off" placeholder="{{ __('Search by name or identification') }}"
                    class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">
                <input type="hidden" id="documents-filter-customer-id">
                <div id="documents-filter-customer-results" class="hidden absolute z-20 mt-1 w-full max-h-72 overflow-y-auto bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 rounded-lg shadow-xl"></div>
            </div>
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Numeral') }}</label>
                <flux:input type="text" id="documents-filter-numeral" placeholder="{{ __('Numeral') }}" autocomplete="off" />
            </div>
            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Document type') }}</label>
                <select id="documents-filter-document-type" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($documentsFilterTypeLabels as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Payment form') }}</label>
                <select id="documents-filter-payment-form" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    <option value="contado">{{ __('Cash') }}</option>
                    <option value="credito">{{ __('Credit') }}</option>
                </select>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
            <div class="py-3 px-4 flex justify-between items-center gap-4">
                <div class="relative max-w-xs">
                    <label class="sr-only">{{ __('Search') }}</label>
                    <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                </div>

                <div class="flex gap-2">
                    <button type="button" id="documents-filters-toggle-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden" aria-label="{{ __('Filters') }}" title="{{ __('Filters') }}">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    </button>

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
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Tracking') }}</th>
                        <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
            </table>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')
    <x-date-range-picker-script />
    @include('documents.partials.send-email-modal')
    @include('documents.partials.retry-modal')

    <div id="doc-tracking-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-5xl sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Email history') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#doc-tracking-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Recipient email') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Sent') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Delivered') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Opened') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Bounced') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Spam') }}</th>
                            </tr>
                        </thead>
                        <tbody id="doc-tracking-modal-body" class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
                    sendByEmail: @json(__('Send by email')),
                    email: @json(__('Email')),
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

                /**
                 * Sin contador ni condición -- el botón se muestra siempre, sin importar si el
                 * documento ya se mandó por correo o no. Contar cuántos EmailLog tiene cada
                 * documento (para decidir si mostrar el botón) obligaba a traer y agrupar todos
                 * los logs de la empresa en cada carga de la tabla (ver
                 * DocumentoEmitidoController::data()) -- ese conteo también quedaba
                 * desactualizado apenas se mandaba un correo nuevo sin recargar la tabla. El
                 * detalle real (si tiene historial o no) se resuelve al abrir el modal, pidiendo
                 * siempre a la base (ver openDocumentTrackingModal()).
                 */
                function renderTracking(row) {
                    return `<button type="button" class="document-tracking-btn inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 hover:opacity-80 focus:outline-hidden" data-url="${row.urls.emailLogs}">
                        ${i18n.email}
                    </button>`;
                }

                function renderActions(row) {
                    let html = '<div class="flex justify-end items-center gap-3">';

                    if (row.has_uuid) {
                        html += `<a href="${row.urls.preview}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.viewPdf}" title="${i18n.viewPdf}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        </a>`;

                        // Solo se puede mandar por correo si la DIAN ya lo autorizó (status 2) --
                        // uno pendiente o rechazado no es una factura válida para el cliente,
                        // aunque ya tenga UUID asignado (mismo criterio que
                        // DocumentoEmitidoController::sendEmail()).
                        if (row.status === 2) {
                            html += `<button type="button" class="document-send-email-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" data-url="${row.urls.sendEmail}" data-email="${escapeHtml(row.customer_email ?? '')}" aria-label="${i18n.sendByEmail}" title="${i18n.sendByEmail}">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </button>`;
                        }
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
                            { data: null, orderable: false, className: 'px-4 py-4 text-sm', render: (data, type, row) => renderTracking(row) },
                            { data: null, orderable: false, className: 'px-4 py-4 text-end text-sm', render: (data, type, row) => renderActions(row) },
                        ],
                    });
                    documentsTable.order([]).draw();
                }

                /**
                 * La tabla de documentos ya no viene lista en el HTML inicial
                 * (ver DocumentoEmitidoController::index()) -- se pide por AJAX
                 * apenas carga la página para no bloquear el primer render con
                 * la consulta completa del historial. El filtrado (fecha, cliente, numeral,
                 * tipo de documento, forma de pago) se resuelve del lado del servidor (ver
                 * DocumentoEmitidoController::data()), no en el navegador -- mismo criterio
                 * que received-documents/index.blade.php: con empresas que acumulan decenas de
                 * miles de documentos, traerlos todos de una para filtrar en DataTables no
                 * escala. El backend manda el JSON crudo (data.rows) y esta función arma las
                 * filas -- ver las funciones render*() de arriba.
                 * @returns {void}
                 */
                function buildDocumentsFilterParams() {
                    const params = new URLSearchParams();

                    const from = document.querySelector('[data-daterange-hidden-from]')?.value;
                    const to = document.querySelector('[data-daterange-hidden-to]')?.value;
                    const customerId = document.getElementById('documents-filter-customer-id')?.value ?? '';
                    const numeral = document.getElementById('documents-filter-numeral')?.value ?? '';
                    const documentType = document.getElementById('documents-filter-document-type')?.value ?? '';
                    const paymentForm = document.getElementById('documents-filter-payment-form')?.value ?? '';

                    if (from) params.set('from', from);
                    if (to) params.set('to', to);
                    if (customerId) params.set('customer_id', customerId);
                    if (numeral) params.set('numeral', numeral);
                    if (documentType) params.set('document_type', documentType);
                    if (paymentForm) params.set('payment_form', paymentForm);

                    return params;
                }

                function loadDocumentsTable() {
                    const tbody = document.querySelector('#documentsTable tbody');
                    if (! tbody) return;

                    const refreshBtn = document.getElementById('documents-refresh-btn');
                    const refreshIcon = document.getElementById('documents-refresh-icon');
                    if (refreshBtn) refreshBtn.disabled = true;
                    if (refreshIcon) refreshIcon.classList.add('animate-spin');

                    const params = buildDocumentsFilterParams();

                    fetch(`{{ route('documents.data') }}?${params.toString()}`, { headers: { Accept: 'application/json' } })
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
                 * Reset del select nativo de Preline (HSSelect) -- setValue() sincroniza el
                 * toggle visible, a diferencia de escribir .value directo en el <select> oculto.
                 * @param {string} id
                 * @returns {void}
                 */
                function resetDocumentsHsSelect(id) {
                    const el = document.getElementById(id);
                    if (! el) return;

                    const instance = window.HSSelect && HSSelect.getInstance(el);
                    if (instance) {
                        instance.setValue('');
                    } else {
                        el.value = '';
                    }
                }

                /**
                 * Vuelve el rango de fechas a "1° del mes actual -> hoy" -- mismo default que
                 * trae la página al cargar (ver $documentsDefaultFrom/To en el blade), así que
                 * cerrar el panel de filtros nunca deja la búsqueda sin rango de fechas.
                 * @returns {void}
                 */
                function resetDocumentsDateRangeFilter() {
                    const today = new Date();
                    const from = new Date(today.getFullYear(), today.getMonth(), 1);
                    const toIso = (date) => date.toISOString().slice(0, 10);

                    const hiddenFrom = document.querySelector('[data-daterange-hidden-from]');
                    const hiddenTo = document.querySelector('[data-daterange-hidden-to]');
                    const trigger = document.querySelector('[data-daterange-trigger]');

                    if (hiddenFrom) hiddenFrom.value = toIso(from);
                    if (hiddenTo) hiddenTo.value = toIso(today);
                    if (trigger) trigger.value = `${toIso(from)} / ${toIso(today)}`;
                }

                /**
                 * Deja los filtros en su estado por defecto (rango de fechas 1° del mes -> hoy,
                 * el resto vacío) y recarga la tabla -- se usa al cerrar el panel de filtros,
                 * para que cerrarlo sea lo mismo que "olvidate de estos filtros y mostrame el
                 * mes actual otra vez".
                 * @returns {void}
                 */
                function clearDocumentsFilters() {
                    resetDocumentsDateRangeFilter();

                    document.getElementById('documents-filter-customer').value = '';
                    document.getElementById('documents-filter-customer-id').value = '';
                    document.getElementById('documents-filter-numeral').value = '';
                    resetDocumentsHsSelect('documents-filter-document-type');
                    resetDocumentsHsSelect('documents-filter-payment-form');

                    loadDocumentsTable();
                }

                /**
                 * Buscador de cliente del filtro -- mismo patrón que el buscador de cliente de
                 * documents/create.blade.php (debounce de 400ms, mínimo 3 caracteres, descarta
                 * respuestas que ya quedaron obsoletas porque el usuario siguió escribiendo),
                 * reusando el mismo endpoint (documents.create-client-search); solo guarda el
                 * _id elegido en un input oculto -- es lo único que manda el filtro (ver
                 * customer_id en buildDocumentsFilterParams()).
                 * @returns {void}
                 */
                function initDocumentsCustomerSearch() {
                    const searchInput = document.getElementById('documents-filter-customer');
                    const hiddenId = document.getElementById('documents-filter-customer-id');
                    const resultsEl = document.getElementById('documents-filter-customer-results');
                    if (! searchInput || ! hiddenId || ! resultsEl) return;

                    let searchTimeout = null;

                    function closeResults() {
                        resultsEl.classList.add('hidden');
                        resultsEl.innerHTML = '';
                    }

                    function renderResults(clients) {
                        resultsEl.innerHTML = '';

                        if (! clients.length) {
                            resultsEl.innerHTML = `<div class="p-3 text-sm text-amber-600 dark:text-amber-400">${escapeHtml('{{ __('No results found.') }}')}</div>`;
                            resultsEl.classList.remove('hidden');
                            return;
                        }

                        clients.forEach((client) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'w-full text-start p-3 hover:bg-gray-100 dark:hover:bg-white/10 focus:outline-hidden';
                            item.innerHTML = `<div class="text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(client.name ?? '—')}</div>`
                                + `<div class="text-xs text-gray-500 dark:text-neutral-400">${escapeHtml(client.identificacion ?? '')}</div>`;
                            item.addEventListener('click', () => {
                                searchInput.value = client.name ?? '';
                                hiddenId.value = client.id;
                                closeResults();
                            });
                            resultsEl.appendChild(item);
                        });

                        resultsEl.classList.remove('hidden');
                    }

                    searchInput.addEventListener('input', () => {
                        hiddenId.value = '';

                        const query = searchInput.value.trim();
                        clearTimeout(searchTimeout);

                        if (query === '') {
                            closeResults();
                            return;
                        }

                        if (query.length < 3) {
                            resultsEl.innerHTML = `<div class="p-3 text-sm text-gray-500 dark:text-neutral-400">${escapeHtml('{{ __('Type at least 3 characters to search.') }}')}</div>`;
                            resultsEl.classList.remove('hidden');
                            return;
                        }

                        searchTimeout = setTimeout(() => {
                            fetch(`{{ route('documents.create-client-search') }}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (searchInput.value.trim() !== query) return;
                                    renderResults(data.clients || []);
                                });
                        }, 400);
                    });

                    // Sin guardia a propósito (mismo criterio que
                    // received-documents/index.blade.php): "searchInput"/"resultsEl" quedan
                    // capturados en el closure de esta llamada puntual, así que agregar una
                    // guardia por "document.body.dataset" dejaría este listener apuntando para
                    // siempre a los nodos de la PRIMERA carga -- en navegaciones posteriores,
                    // ".contains()" sobre un nodo ya desmontado del DOM siempre da falso, así
                    // que el listener viejo no hace nada (no hay bug), solo queda de más.
                    document.addEventListener('click', (event) => {
                        if (! searchInput.contains(event.target) && ! resultsEl.contains(event.target)) {
                            closeResults();
                        }
                    });
                }

                /**
                 * Sin guardia de "ya se enganchó" a propósito -- a diferencia de un listener
                 * delegado sobre "document" (que sí necesitaría esa guardia porque "document"
                 * sobrevive entre navegaciones Livewire), estos elementos se vuelven a crear en
                 * cada navegación wire:navigate, así que hace falta re-engancharlos cada vez que
                 * este script se re-ejecuta.
                 */
                function bindDocumentsFilters() {
                    initDocumentsCustomerSearch();

                    /**
                     * El panel de filtros arranca cerrado (ver la clase "hidden" en el blade) --
                     * al abrirlo no hace falta nada más (el usuario los configura y le da a
                     * "Refresh" para buscar con ellos), pero al cerrarlo se limpian y se recarga
                     * la tabla de una, así nunca queda una búsqueda filtrada "escondida" detrás
                     * de un panel cerrado.
                     */
                    document.getElementById('documents-filters-toggle-btn')?.addEventListener('click', () => {
                        const isNowHidden = document.getElementById('documents-filters')?.classList.toggle('hidden');

                        if (isNowHidden) {
                            clearDocumentsFilters();
                        }
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

                /**
                 * Cada etapa (sent/delivered/opened/bounced/complained) es una columna con su
                 * propia fecha -- "—" si esa etapa todavía no pasó -- en vez de un solo estado
                 * "más avanzado" por fila, para poder ver de una en qué momento pasó cada cosa.
                 * @param {string|null} date
                 * @returns {string}
                 */
                function trackingCell(date) {
                    return `<td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">${escapeHtml(date ?? '—')}</td>`;
                }

                /**
                 * Pide el historial de correos de un documento directo a la base (ver
                 * DocumentoEmitidoController::emailLogs()) cada vez que se abre el modal -- a
                 * propósito, para que siempre muestre el estado más actual (ej. un correo que se
                 * acaba de mandar) sin depender de que la tabla se haya vuelto a cargar.
                 * @param {string} url
                 * @returns {Promise<void>}
                 */
                async function openDocumentTrackingModal(url) {
                    const body = document.getElementById('doc-tracking-modal-body');
                    body.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('Loading...') }}</td></tr>`;

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#doc-tracking-modal');
                    }

                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    const logs = data.logs || [];

                    body.innerHTML = '';

                    if (! logs.length) {
                        body.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Email history')]) }}</td></tr>`;
                        return;
                    }

                    logs.forEach((log) => {
                        const row = document.createElement('tr');
                        const bouncedCell = `<td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">
                                ${escapeHtml(log.bounced_at ?? '—')}
                                ${log.bounce_reason ? `<div class="text-xs text-red-600 dark:text-red-400 whitespace-normal">${escapeHtml(log.bounce_reason)}</div>` : ''}
                            </td>`;
                        const complainedCell = `<td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">
                                ${escapeHtml(log.complained_at ?? '—')}
                                ${log.complaint_reason ? `<div class="text-xs text-red-600 dark:text-red-400 whitespace-normal">${escapeHtml(log.complaint_reason)}</div>` : ''}
                            </td>`;

                        row.innerHTML = `
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200 truncate">${escapeHtml(log.to)}</td>
                            ${trackingCell(log.sent_at)}
                            ${trackingCell(log.delivered_at)}
                            ${trackingCell(log.opened_at)}
                            ${bouncedCell}
                            ${complainedCell}
                        `;
                        body.appendChild(row);
                    });
                }

                function initDocumentTrackingButtons() {
                    if (document.body.dataset.docTrackingBound === 'true') return;
                    document.body.dataset.docTrackingBound = 'true';

                    document.addEventListener('click', function (event) {
                        const button = event.target.closest('.document-tracking-btn');
                        if (! button) return;

                        openDocumentTrackingModal(button.dataset.url);
                    });
                }

                initDocumentDropdowns();
                initDocumentTrackingButtons();
                bindDocumentsFilters();

                window.loadDocumentsTable = loadDocumentsTable;

                document.addEventListener('DOMContentLoaded', loadDocumentsTable);
                document.addEventListener('livewire:navigated', loadDocumentsTable);
            })();
        </script>
    @endpush
</x-layouts.app>
