<x-layouts.app :title="__('Received documents')">
    @include('partials.tittle', [
        'title' => __('Received documents'),
        'subheading' => __('Invoices and notes your providers sent you.'),
    ])

    @if (session('received-documents-errors'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach (session('received-documents-errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (config('services.ses.inbound_address'))
        <div class="mb-6 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-gray-800 dark:text-white">{{ __('Reception email') }}</div>
                    <div class="text-gray-600 dark:text-neutral-400">{{ __('Give this address to your providers, or forward your already issued documents to this address. Documents sent here are matched to your company automatically by NIT and show up in this inbox.') }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <code id="received-documents-reception-email" class="rounded-md bg-white px-3 py-1.5 font-mono text-xs text-gray-800 border border-gray-200 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200">{{ config('services.ses.inbound_address') }}</code>
                    {{-- Mismo mecanismo que el link público de cotizaciones (quotations/index.blade.php):
                         ClipboardJS real vía window.hsClipboardHelper, no navigator.clipboard a mano --
                         funciona también en HTTP local, no solo en HTTPS. Se inicializa junto con el
                         resto del @push('scripts') de abajo. --}}
                    <button type="button" class="js-clipboard relative flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                        data-clipboard-target="#received-documents-reception-email"
                        data-clipboard-action="copy"
                        aria-label="{{ __('Copy') }}" title="{{ __('Copy') }}">
                        <svg class="js-clipboard-default size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                        <svg class="js-clipboard-success hidden size-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @php
        $receivedDocumentsDefaultFrom = now()->startOfMonth()->format('Y-m-d');
        $receivedDocumentsDefaultTo = now()->format('Y-m-d');
        $receivedDocumentsTypeLabels = [
            '01' => __('Electronic sales invoice'),
            '02' => __('Electronic sales invoice (export)'),
            '03' => __('Electronic transmission instrument (type 03)'),
            '04' => __('Electronic sales invoice (type 04)'),
            '91' => __('Credit note'),
            '92' => __('Debit note'),
        ];
    @endphp

    <div class="flex flex-col gap-4">
        <div id="received-documents-filters" class="hidden flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-date-range-picker name-from="received_from" name-to="received_to" :label="__('Date range')" :value-from="$receivedDocumentsDefaultFrom" :value-to="$receivedDocumentsDefaultTo" :allow-open-end="true" :floating="true" align="left" />
            </div>
            <div class="w-56 relative">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400" for="received-documents-filter-provider">{{ __('Provider') }}</label>
                <input type="text" id="received-documents-filter-provider" autocomplete="off" placeholder="{{ __('Search by name or identification') }}"
                    class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">
                <input type="hidden" id="received-documents-filter-provider-id">
                <div id="received-documents-filter-provider-results" class="hidden absolute z-20 mt-1 w-full max-h-72 overflow-y-auto bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 rounded-lg shadow-xl"></div>
            </div>
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Numeral') }}</label>
                <flux:input type="text" id="received-documents-filter-numeral" placeholder="{{ __('Numeral') }}" autocomplete="off" />
            </div>
            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Document type') }}</label>
                <select id="received-documents-filter-document-type" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($receivedDocumentsTypeLabels as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Payment form') }}</label>
                <select id="received-documents-filter-payment-form" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
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
                    <flux:input type="text" id="received-documents-search" placeholder="{{ __('Search') }}" autocomplete="off" />
                </div>

                <div class="flex gap-2">
                    <button type="button" id="received-documents-filters-toggle-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden" aria-label="{{ __('Filters') }}" title="{{ __('Filters') }}">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    </button>

                    <button type="button" id="received-documents-refresh-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Refresh') }}" title="{{ __('Refresh') }}" onclick="loadReceivedDocumentsTable()">
                        <svg id="received-documents-refresh-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    </button>

                    <flux:button variant="primary" icon="arrow-up-tray" data-hs-overlay="#upload-received-document-modal">
                        {{ __('Upload document') }}
                    </flux:button>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-lg">
                <table id="receivedDocumentsTable" class="w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Issue date') }}</th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Provider') }}</th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Document') }}</th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Document type') }}</th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Payment form') }}</th>
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

    <div id="upload-received-document-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form id="upload-received-document-form" method="POST" action="{{ route('received-documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Upload document') }}</h3>
                        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#upload-received-document-modal">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-4 space-y-3">
                        <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('Upload the XML/UBL file that the provider sent you, or the .zip they usually send it in (with the PDF included). You can select more than one at a time.') }}</p>

                        <div id="received-document-file-upload" data-hs-file-upload='{
                                "url": "#",
                                "autoProcessQueue": false,
                                "autoHideTrigger": false
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

                        <input type="file" name="files[]" id="received-document-file-input" multiple accept=".xml,.zip" class="hidden">
                        <p id="received-document-upload-error" class="hidden text-xs text-red-600 dark:text-red-400"></p>
                    </div>
                    <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                        <flux:button type="button" variant="ghost" data-hs-overlay="#upload-received-document-modal">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Upload') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')
    <x-date-range-picker-script />

    @push('scripts')
        <script>
            (function () {
                // Misma idea que documents/index.blade.php: la tabla se llena por AJAX (ver
                // DocumentoRecibidoController::data()), DataTables arma cada <tr> a partir de
                // columns.render, el backend solo manda el array de documentos en JSON.
                let receivedDocumentsTable = null;
                let documentTypeLabels = {};

                const i18n = {
                    viewPdf: @json(__('View PDF')),
                    view: @json(__('View')),
                };

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function renderProvider(row, type) {
                    if (type === 'filter' || type === 'sort') {
                        return `${row.provider_name ?? ''} ${row.provider_identification ?? ''}`.trim();
                    }

                    return `<div class="text-gray-800 dark:text-neutral-200">${escapeHtml(row.provider_name ?? '—')}</div>`
                        + `<div class="text-xs text-gray-400 dark:text-neutral-500">${escapeHtml(row.provider_identification ?? '')}</div>`;
                }

                function renderPaymentForm(row, type) {
                    if (type === 'filter' || type === 'sort') {
                        return row.payment_form ?? '';
                    }

                    return escapeHtml(row.payment_form_label ?? '—');
                }

                function renderDocumentType(row, type) {
                    if (type === 'filter') {
                        return row.tipo_documento ?? '';
                    }

                    const label = documentTypeLabels[row.tipo_documento] ?? row.tipo_documento;

                    if (type === 'sort') {
                        return label ?? '';
                    }

                    return escapeHtml(label ?? '—');
                }

                function renderStatus(row) {
                    return `<span class="rounded-md px-2 py-0.5 text-xs font-medium ${row.status_badge_classes}">${escapeHtml(row.status_label)}</span>`;
                }

                function renderActions(row) {
                    return `<div class="flex justify-end items-center gap-1">
                        <a href="${row.urls.pdf}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.viewPdf}" title="${i18n.viewPdf}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        </a>
                        <a href="${row.urls.show}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="${i18n.view}" title="${i18n.view}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        </a>
                    </div>`;
                }

                function initReceivedDocumentsTable() {
                    receivedDocumentsTable = initWorkflowDataTable('#receivedDocumentsTable', '#received-documents-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('Received documents')]) }}",
                        columns: [
                            { data: 'issue_date', className: 'px-6 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: null, className: 'px-6 py-3 text-sm', render: (data, type, row) => renderProvider(row, type) },
                            { data: 'numeral', className: 'px-6 py-3 text-sm font-medium text-gray-800 break-words dark:text-neutral-200' },
                            { data: null, className: 'px-6 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderDocumentType(row, type) },
                            { data: null, className: 'px-6 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderPaymentForm(row, type) },
                            { data: 'total_formatted', className: 'px-6 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: null, className: 'px-6 py-3 text-sm', render: (data, type, row) => renderStatus(row) },
                            { data: null, orderable: false, className: 'px-6 py-3 text-end text-sm', render: (data, type, row) => renderActions(row) },
                        ],
                    });
                    receivedDocumentsTable.order([]).draw();
                }

                /**
                 * La tabla no viene lista en el HTML inicial (ver
                 * DocumentoRecibidoController::index()) -- se pide por AJAX apenas carga la
                 * página, mismo patrón que documents/index.blade.php. El filtrado (fecha,
                 * proveedor, numeral, tipo de documento, forma de pago) se resuelve del lado del
                 * servidor (ver DocumentoRecibidoController::data()), no en el navegador -- con
                 * empresas que acumulan decenas de miles de documentos recibidos, traerlos todos
                 * de una para filtrar en DataTables no escala. Por eso cada llamada manda el
                 * estado actual de los filtros como query params; el rango de fechas siempre va
                 * (arranca en "1° del mes actual -> hoy", ver $receivedDocumentsDefaultFrom/To),
                 * así que un refresh nunca trae el historial completo sin querer.
                 * @returns {void}
                 */
                function buildReceivedDocumentsFilterParams() {
                    const params = new URLSearchParams();

                    const from = document.querySelector('[data-daterange-hidden-from]')?.value;
                    const to = document.querySelector('[data-daterange-hidden-to]')?.value;
                    const providerId = document.getElementById('received-documents-filter-provider-id')?.value ?? '';
                    const numeral = document.getElementById('received-documents-filter-numeral')?.value ?? '';
                    const documentType = document.getElementById('received-documents-filter-document-type')?.value ?? '';
                    const paymentForm = document.getElementById('received-documents-filter-payment-form')?.value ?? '';

                    if (from) params.set('from', from);
                    if (to) params.set('to', to);
                    if (providerId) params.set('provider_id', providerId);
                    if (numeral) params.set('numeral', numeral);
                    if (documentType) params.set('document_type', documentType);
                    if (paymentForm) params.set('payment_form', paymentForm);

                    return params;
                }

                function loadReceivedDocumentsTable() {
                    const tbody = document.querySelector('#receivedDocumentsTable tbody');
                    if (! tbody) return;

                    const refreshBtn = document.getElementById('received-documents-refresh-btn');
                    const refreshIcon = document.getElementById('received-documents-refresh-icon');
                    if (refreshBtn) refreshBtn.disabled = true;
                    if (refreshIcon) refreshIcon.classList.add('animate-spin');

                    const params = buildReceivedDocumentsFilterParams();

                    fetch(`{{ route('received-documents.data') }}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (! receivedDocumentsTable) {
                                initReceivedDocumentsTable();
                            }

                            documentTypeLabels = data.document_type_labels;
                            receivedDocumentsTable.clear();
                            receivedDocumentsTable.rows.add(data.rows);
                            receivedDocumentsTable.order([]).draw();

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
                function resetHsSelect(id) {
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
                 * trae la página al cargar (ver $receivedDocumentsDefaultFrom/To en el blade), así
                 * que "limpiar filtros" nunca deja la búsqueda sin rango de fechas.
                 * @returns {void}
                 */
                function resetDateRangeFilter() {
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
                 * el resto vacío) y recarga la tabla -- se usa al cerrar el panel de filtros (ver
                 * bindReceivedDocumentsFilters()), para que cerrar el panel sea lo mismo que
                 * "olvidate de estos filtros y mostrame el mes actual otra vez".
                 * @returns {void}
                 */
                function clearReceivedDocumentsFilters() {
                    resetDateRangeFilter();

                    document.getElementById('received-documents-filter-provider').value = '';
                    document.getElementById('received-documents-filter-provider-id').value = '';
                    document.getElementById('received-documents-filter-numeral').value = '';
                    resetHsSelect('received-documents-filter-document-type');
                    resetHsSelect('received-documents-filter-payment-form');

                    loadReceivedDocumentsTable();
                }

                /**
                 * Buscador de proveedor del filtro -- mismo patrón que el buscador de cliente de
                 * documents/create.blade.php (debounce de 400ms, mínimo 3 caracteres, descarta
                 * respuestas que ya quedaron obsoletas porque el usuario siguió escribiendo), pero
                 * en vez de llenar un formulario completo solo guarda el _id elegido en un input
                 * oculto -- es lo único que manda el filtro (ver provider_id en
                 * buildReceivedDocumentsFilterParams()).
                 * @returns {void}
                 */
                function initReceivedDocumentsProviderSearch() {
                    const searchInput = document.getElementById('received-documents-filter-provider');
                    const hiddenId = document.getElementById('received-documents-filter-provider-id');
                    const resultsEl = document.getElementById('received-documents-filter-provider-results');
                    if (! searchInput || ! hiddenId || ! resultsEl) return;

                    let searchTimeout = null;

                    function closeResults() {
                        resultsEl.classList.add('hidden');
                        resultsEl.innerHTML = '';
                    }

                    function renderResults(providers) {
                        resultsEl.innerHTML = '';

                        if (! providers.length) {
                            resultsEl.innerHTML = `<div class="p-3 text-sm text-amber-600 dark:text-amber-400">${escapeHtml('{{ __('No results found.') }}')}</div>`;
                            resultsEl.classList.remove('hidden');
                            return;
                        }

                        providers.forEach((provider) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'w-full text-start p-3 hover:bg-gray-100 dark:hover:bg-white/10 focus:outline-hidden';
                            item.innerHTML = `<div class="text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(provider.name ?? '—')}</div>`
                                + `<div class="text-xs text-gray-500 dark:text-neutral-400">${escapeHtml(provider.identificacion ?? '')}</div>`;
                            item.addEventListener('click', () => {
                                searchInput.value = provider.name ?? '';
                                hiddenId.value = provider.id;
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
                            fetch(`{{ route('received-documents.provider-search') }}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (searchInput.value.trim() !== query) return;
                                    renderResults(data.providers || []);
                                });
                        }, 400);
                    });

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
                function bindReceivedDocumentsFilters() {
                    initReceivedDocumentsProviderSearch();

                    /**
                     * El panel de filtros arranca cerrado (ver la clase "hidden" en el blade) --
                     * al abrirlo no hace falta nada más (el usuario los configura y le da a
                     * "Refresh" para buscar con ellos), pero al cerrarlo se limpian y se recarga
                     * la tabla de una, así nunca queda una búsqueda filtrada "escondida" detrás
                     * de un panel cerrado.
                     */
                    document.getElementById('received-documents-filters-toggle-btn')?.addEventListener('click', () => {
                        const isNowHidden = document.getElementById('received-documents-filters')?.classList.toggle('hidden');

                        if (isNowHidden) {
                            clearReceivedDocumentsFilters();
                        }
                    });
                }

                window.loadReceivedDocumentsTable = loadReceivedDocumentsTable;

                bindReceivedDocumentsFilters();

                function initReceivedDocumentsClipboard() {
                    window.hsClipboardHelper?.('.js-clipboard');
                }

                document.addEventListener('DOMContentLoaded', loadReceivedDocumentsTable);
                document.addEventListener('livewire:navigated', loadReceivedDocumentsTable);
                document.addEventListener('DOMContentLoaded', initReceivedDocumentsClipboard);
                document.addEventListener('livewire:navigated', initReceivedDocumentsClipboard);
            })();
        </script>
    @endpush

    <script>
        /**
         * Engancha el widget de arrastrar/soltar (Dropzone, vía Preline
         * HSFileUpload) con el <input type="file" multiple> real que se
         * manda en el submit -- mismo mecanismo que ya usa el certificado
         * digital (companies/create.blade.php), pero acumulando archivos en
         * vez de reemplazar el único que había, porque acá sí se permite
         * más de uno. autoProcessQueue está en false (no hay backend de
         * subida por partes acá), así que la barra de progreso se marca
         * "completa" apenas se agrega el archivo -- es solo la vista previa
         * con su nombre/tamaño, no una subida real en curso.
         */
        // Envuelto en DOMContentLoaded a propósito: el bundle de Vite carga app.js (que trae
        // Preline, de ahí sale window.HSFileUpload) como <script type="module">, que SIEMPRE se difiere hasta
        // que el documento termina de parsear -- si este bloque corriera de una al toque, "window.HSFileUpload"
        // todavía no existiría, el "if" de abajo se saltaría en silencio, y ni el input real se
        // sincronizaría con los archivos elegidos ni la barra de progreso se marcaría completa
        // (mismo motivo por el que companies/create.blade.php hace lo mismo con el certificado).
        document.addEventListener('DOMContentLoaded', function () {
            const uploadEl = document.getElementById('received-document-file-upload');
            const fileInput = document.getElementById('received-document-file-input');
            const errorMessage = document.getElementById('received-document-upload-error');
            const form = document.getElementById('upload-received-document-form');
            let selectedFiles = [];

            function syncFileInput() {
                const transfer = new DataTransfer();
                selectedFiles.forEach((file) => transfer.items.add(file));
                fileInput.files = transfer.files;
                errorMessage.classList.add('hidden');
            }

            function markPreviewComplete(file) {
                const previewElement = file.previewElement;
                if (! previewElement) {
                    return;
                }
                previewElement.classList.add('complete');
                previewElement.querySelector('[data-hs-file-upload-progress-bar]')?.setAttribute('aria-valuenow', '100');
                const pane = previewElement.querySelector('[data-hs-file-upload-progress-bar-pane]');
                if (pane) {
                    pane.style.width = '100%';
                }
                const value = previewElement.querySelector('[data-hs-file-upload-progress-bar-value]');
                if (value) {
                    value.textContent = '100';
                }
            }

            if (window.HSFileUpload) {
                HSFileUpload.autoInit();
            }

            const instance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
            const dropzone = instance?.element?.dropzone;

            if (dropzone) {
                dropzone.on('addedfile', (file) => {
                    selectedFiles.push(file);
                    syncFileInput();
                    markPreviewComplete(file);
                });

                dropzone.on('removedfile', (file) => {
                    selectedFiles = selectedFiles.filter((selected) => selected !== file);
                    syncFileInput();
                });
            }

            form?.addEventListener('submit', (event) => {
                if (! selectedFiles.length) {
                    event.preventDefault();
                    errorMessage.textContent = '{{ __('Choose at least one file first.') }}';
                    errorMessage.classList.remove('hidden');
                }
            });
        });
    </script>
</x-layouts.app>
