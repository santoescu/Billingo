@php
    // "allowEmptyOption: true": acá el <option value=""> inicial ES una opción real (nada
    // elegido todavía), no solo un placeholder decorativo. Sin esto, Preline lo descarta y el
    // select queda con la primera resolución de la lista ya elegida "por debajo" aunque el
    // cajero nunca haya tocado el campo, dejando abrir el turno con la resolución equivocada
    // sin avisar.
    $basicSelectConfig = \App\Support\SelectConfig::basic(null, true);
@endphp

<x-layouts.app :title="__('Cash register')">
    @include('partials.tittle', [
        'title' => __('Cash register'),
        'subheading' => __('Open, view or close your cash shift.'),
    ])

    @include('pos.partials.tabs', ['activeTab' => 'shift'])

    <div class="flex flex-col gap-6">
        
        @if (! $shift)
            <div>
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-white">{{ __('Your shift') }}</h3>

                @if ($fvResolutions->isEmpty())
                    <div class="mb-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                        {{ __('There is no active numbering resolution for this document type in the current environment.') }}
                        <a href="{{ route('dian.resolutions.index') }}" class="font-medium underline">{{ __('Resolutions') }}</a>
                    </div>
                @endif

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ __('Opening balance') }}</h2>

                        <form method="POST" action="{{ route('pos.shifts.store') }}" class="space-y-4">
                            @csrf

                            @if ($errors->any())
                                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div>
                                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="opening-balance-display">{{ __('Cash amount you are starting the shift with') }}</label>
                                <div class="relative">
                                    <input type="hidden" id="opening-balance-hidden" name="opening_balance" value="0">
                                    <input type="text" inputmode="decimal" id="opening-balance-display" autofocus
                                        class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                                    <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                    </div>
                                </div>
                            </div>

                            <div id="shift-fv-resolution-field">
                                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Sales invoice resolution') }}</label>
                                <select name="fv_resolution_id" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                    <option value=""></option>
                                    @foreach ($fvResolutions as $resolution)
                                        <option value="{{ $resolution->_id }}">{{ $resolution->prefix }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($invoicingResolutions->isNotEmpty())
                                <div id="shift-invoicing-resolution-field">
                                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Electronic invoice resolution') }}</label>
                                    <select name="invoicing_resolution_id" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                        <option value=""></option>
                                        @foreach ($invoicingResolutions as $resolution)
                                            <option value="{{ $resolution->_id }}">{{ $resolution->prefix }} - {{ __('Resolution') }} {{ $resolution->resolution_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div>
                                <flux:input name="notes" :label="__('Notes')" />
                            </div>

                            <flux:button id="shift-open-btn" type="submit" variant="primary" class="w-full" :disabled="$fvResolutions->isEmpty()">{{ __('Open shift') }}</flux:button>
                        </form>
                    </div>
                </section>
            </div>
        @endif

        @if ($openShifts->isNotEmpty())
            <div>
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-white">{{ $isAdmin ? __('Open cash shifts') : __('Your shift') }}</h3>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($openShifts as $summary)
                        @php $s = $summary['shift']; @endphp
                        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $s->user?->name ?? '—' }}</h2>
                                @if ($shift && (string) $s->_id === (string) $shift->_id)
                                    <span class="shrink-0 rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-neutral-700 dark:text-neutral-300">{{ __('You') }}</span>
                                @endif
                            </div>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Opened at') }} {{ $s->opened_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</p>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-xs uppercase text-gray-400 dark:text-neutral-500">{{ __('Opening balance') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ '$' . number_format((float) $s->opening_balance, 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase text-gray-400 dark:text-neutral-500">{{ __('Expected cash so far') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ '$' . number_format((float) $summary['expected_balance'], 2) }}</div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-xs uppercase text-gray-400 dark:text-neutral-500">{{ __('Sales in this shift') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ $summary['sales_count'] }} &middot; {{ '$' . number_format((float) $summary['sales_total'], 2) }}</div>
                                </div>
                            </div>

                            <flux:button type="button" size="sm" variant="filled" class="shift-close-btn w-full"
                                onclick="window.openAdminCloseShiftModal({{ Illuminate\Support\Js::from([
                                    'closeUrl' => route('pos.shifts.close', $s->_id),
                                    'showUrl' => route('pos.shifts.show', $s->_id),
                                ]) }})"
                            >
                                {{ __('Close shift') }}
                            </flux:button>
                        </div>
                    @endforeach
                </section>
            </div>

            <div id="admin-close-shift-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="admin-close-shift-modal-label">
                <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
                    <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                            <h3 id="admin-close-shift-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Close shift') }}</h3>
                            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#admin-close-shift-modal">
                                <span class="sr-only">Close</span>
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <form id="admin-close-shift-form" method="POST" action="" class="p-4 space-y-4">
                            @csrf

                            <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-white/5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-neutral-400">{{ __('Expected cash so far') }}</span>
                                    <span id="admin-close-expected" class="text-gray-800 dark:text-neutral-200">—</span>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Payment methods') }}</p>
                                <div id="admin-close-payment-breakdown" class="rounded-md bg-gray-50 p-3 text-sm dark:bg-white/5 space-y-1">
                                    <p class="text-zinc-400 dark:text-neutral-500">…</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Products sold') }}</p>
                                <div class="rounded-md bg-gray-50 dark:bg-white/5 max-h-40 overflow-y-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-xs text-zinc-500 dark:text-neutral-400">
                                                <th class="text-start font-medium px-3 py-1.5">{{ __('Name') }}</th>
                                                <th class="text-end font-medium px-3 py-1.5">{{ __('Quantity') }}</th>
                                                <th class="text-end font-medium px-3 py-1.5">{{ __('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-close-products">
                                            <tr><td colspan="3" class="px-3 py-2 text-zinc-400 dark:text-neutral-500">…</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="admin-close-counted-display">{{ __('Counted cash') }}</label>
                                <div class="relative">
                                    <input type="hidden" id="admin-close-counted-hidden" name="closing_balance" value="0">
                                    <input type="text" inputmode="decimal" id="admin-close-counted-display" required
                                        class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                                    <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:button type="button" variant="filled" data-hs-overlay="#admin-close-shift-modal">{{ __('Cancel') }}</flux:button>
                                <flux:button type="submit" variant="primary">{{ __('Close shift') }}</flux:button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if ($isAdmin)
            <div>
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-white">{{ __('Closed cash shifts') }}</h3>

                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <div class="w-64">
                        <x-date-range-picker name-from="closed_shifts_from" name-to="closed_shifts_to" :label="__('Date range')" :value-from="$closedShiftsDefaultFrom" :value-to="$closedShiftsDefaultTo" :allow-open-end="true" :floating="true" align="left" />
                    </div>
                    <button type="button" id="closed-shifts-refresh-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Refresh') }}" title="{{ __('Refresh') }}" onclick="loadClosedShiftsTable()">
                        <svg id="closed-shifts-refresh-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    </button>
                </div>

                <div class="-m-1.5 overflow-x-auto">
                    <div class="p-1.5 min-w-full inline-block align-middle">
                        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                            <div class="overflow-hidden">
                                <table id="closedShiftsTable" class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                                    <thead class="bg-gray-50 dark:bg-neutral-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Cashier') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Opened at') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Closed at') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Opening balance') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Expected balance') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Counted cash') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Difference') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Payment methods') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Products sold') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Sales') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                @include('partials.datatable-pagination')
            </div>

            <x-date-range-picker-script />

            <div id="closed-shift-products-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="closed-shift-products-modal-label">
                <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
                    <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                            <h3 id="closed-shift-products-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Products sold') }}</h3>
                            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#closed-shift-products-modal">
                                <span class="sr-only">Close</span>
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="p-4 max-h-[70vh] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-zinc-500 dark:text-neutral-400">
                                        <th class="text-start font-medium px-3 py-1.5">{{ __('Name') }}</th>
                                        <th class="text-end font-medium px-3 py-1.5">{{ __('Quantity') }}</th>
                                        <th class="text-end font-medium px-3 py-1.5">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="closed-shift-products-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            (function () {
                function formatMoney(intPart, decPart, hasComma) {
                    if (! intPart && ! hasComma) {
                        return '';
                    }
                    const formattedInt = Number(intPart || '0').toLocaleString('es-CO');
                    return hasComma ? `${formattedInt},${decPart}` : formattedInt;
                }

                function rawValue(intPart, decPart) {
                    if (! intPart && ! decPart) {
                        return '0';
                    }
                    return decPart ? `${intPart || '0'}.${decPart}` : (intPart || '0');
                }

                function wireMoneyInput(hiddenId, displayId) {
                    const hidden = document.getElementById(hiddenId);
                    const display = document.getElementById(displayId);
                    if (! hidden || ! display) {
                        return;
                    }

                    display.addEventListener('input', () => {
                        const str = display.value.replace(/\./g, '');
                        const commaIndex = str.indexOf(',');
                        let intPart, decPart, hasComma;
                        if (commaIndex === -1) {
                            intPart = str.replace(/\D/g, '');
                            decPart = '';
                            hasComma = false;
                        } else {
                            intPart = str.slice(0, commaIndex).replace(/\D/g, '');
                            decPart = str.slice(commaIndex + 1).replace(/\D/g, '').slice(0, 2);
                            hasComma = true;
                        }
                        hidden.value = rawValue(intPart, decPart);
                        display.value = formatMoney(intPart, decPart, hasComma);
                    });
                }

                wireMoneyInput('opening-balance-hidden', 'opening-balance-display');
                wireMoneyInput('admin-close-counted-hidden', 'admin-close-counted-display');

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function formatMoneyDisplay(value) {
                    return '$' + Number(value ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderPaymentBreakdownCell(row) {
                    if (! row.payment_breakdown || ! row.payment_breakdown.length) return '—';

                    return row.payment_breakdown.map((p) => `<div>${escapeHtml(p.name)}: ${formatMoneyDisplay(p.amount)}</div>`).join('');
                }

                function renderProductsCell(row) {
                    if (! row.products || ! row.products.length) return '—';

                    return `
                        <button type="button" class="closed-shift-products-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View products sold') }}" title="{{ __('View products sold') }}" data-products='${escapeHtml(JSON.stringify(row.products))}'>
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    `;
                }

                let closedShiftsTable = null;

                function initClosedShiftsTable() {
                    closedShiftsTable = initWorkflowDataTable('#closedShiftsTable', '#closed-shifts-search', {
                        emptyTable: "{{ __('There are no registered :name.', ['name' => __('closed cash shifts')]) }}",
                        columns: [
                            { data: 'cashier', className: 'px-4 py-3 text-sm text-gray-800 dark:text-neutral-200' },
                            { data: 'opened_at', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: 'closed_at', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                            { data: null, className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => formatMoneyDisplay(row.opening_balance) },
                            { data: null, className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => formatMoneyDisplay(row.expected_balance) },
                            { data: null, className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => formatMoneyDisplay(row.closing_balance) },
                            { data: null, className: 'px-4 py-3 text-sm', render: (data, type, row) => `<span class="${row.variance < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-neutral-400'}">${formatMoneyDisplay(row.variance)}</span>` },
                            { data: null, orderable: false, className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderPaymentBreakdownCell(row) },
                            { data: null, orderable: false, className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400', render: (data, type, row) => renderProductsCell(row) },
                            { data: 'sales_count', className: 'px-4 py-3 text-sm text-gray-600 dark:text-neutral-400' },
                        ],
                    });
                    closedShiftsTable.order([]).draw();
                }

                function loadClosedShiftsTable() {
                    const table = document.getElementById('closedShiftsTable');
                    if (! table) return;

                    const refreshBtn = document.getElementById('closed-shifts-refresh-btn');
                    const refreshIcon = document.getElementById('closed-shifts-refresh-icon');
                    if (refreshBtn) refreshBtn.disabled = true;
                    if (refreshIcon) refreshIcon.classList.add('animate-spin');

                    const params = new URLSearchParams();
                    const from = document.querySelector('[data-daterange-hidden-from]')?.value;
                    const to = document.querySelector('[data-daterange-hidden-to]')?.value;
                    if (from) params.set('from', from);
                    if (to) params.set('to', to);

                    fetch(`{{ route('pos.shifts.closed-data') }}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (! closedShiftsTable) initClosedShiftsTable();
                            closedShiftsTable.clear();
                            closedShiftsTable.rows.add(data.rows);
                            closedShiftsTable.order([]).draw();
                        })
                        .finally(() => {
                            if (refreshBtn) refreshBtn.disabled = false;
                            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
                        });
                }

                window.loadClosedShiftsTable = loadClosedShiftsTable;

                if (document.getElementById('closedShiftsTable')) {
                    document.addEventListener('DOMContentLoaded', loadClosedShiftsTable);
                    document.addEventListener('livewire:navigated', loadClosedShiftsTable);
                }

                window.openAdminCloseShiftModal = function ({ closeUrl, showUrl }) {
                    document.getElementById('admin-close-shift-form').action = closeUrl;

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#admin-close-shift-modal');
                    }

                    const expectedEl = document.getElementById('admin-close-expected');
                    const paymentBreakdownEl = document.getElementById('admin-close-payment-breakdown');
                    const productsEl = document.getElementById('admin-close-products');
                    expectedEl.textContent = '…';
                    paymentBreakdownEl.innerHTML = '<p class="text-zinc-400 dark:text-neutral-500">…</p>';
                    productsEl.innerHTML = '<tr><td colspan="3" class="px-3 py-2 text-zinc-400 dark:text-neutral-500">…</td></tr>';

                    fetch(showUrl, { headers: { 'Accept': 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            expectedEl.textContent = formatMoneyDisplay(data.expected_balance);

                            const payments = data.payment_breakdown || [];
                            paymentBreakdownEl.innerHTML = payments.length
                                ? payments.map((row) => `
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-neutral-400">${escapeHtml(row.name)}</span>
                                        <span class="text-gray-800 dark:text-neutral-200">${formatMoneyDisplay(row.amount)}</span>
                                    </div>
                                `).join('')
                                : `<p class="text-zinc-400 dark:text-neutral-500">{{ __('No sales yet.') }}</p>`;

                            const products = data.products || [];
                            productsEl.innerHTML = products.length
                                ? products.map((row) => `
                                    <tr>
                                        <td class="px-3 py-1.5 text-gray-500 dark:text-neutral-400">${escapeHtml(row.description)}</td>
                                        <td class="px-3 py-1.5 text-end text-gray-800 dark:text-neutral-200">${row.quantity}</td>
                                        <td class="px-3 py-1.5 text-end text-gray-800 dark:text-neutral-200">${formatMoneyDisplay(row.total)}</td>
                                    </tr>
                                `).join('')
                                : `<tr><td colspan="3" class="px-3 py-2 text-zinc-400 dark:text-neutral-500">{{ __('No sales yet.') }}</td></tr>`;
                        })
                        .catch(() => {
                            expectedEl.textContent = '—';
                        });
                };

                document.addEventListener('click', function (event) {
                    const btn = event.target.closest('.closed-shift-products-btn');
                    if (! btn) return;

                    const products = JSON.parse(btn.dataset.products || '[]');
                    const body = document.getElementById('closed-shift-products-body');

                    body.innerHTML = products.map((row) => `
                        <tr>
                            <td class="px-3 py-1.5 text-gray-500 dark:text-neutral-400">${escapeHtml(row.description)}</td>
                            <td class="px-3 py-1.5 text-end text-gray-800 dark:text-neutral-200">${row.quantity}</td>
                            <td class="px-3 py-1.5 text-end text-gray-800 dark:text-neutral-200">${formatMoneyDisplay(row.total)}</td>
                        </tr>
                    `).join('');

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#closed-shift-products-modal');
                    }
                });
            })();
        </script>
    @endpush
</x-layouts.app>
