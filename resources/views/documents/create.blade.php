@php
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];

    $creditNoteConceptCodes = [
        ['code' => '1', 'label' => __('Partial refund of the goods and/or non-acceptance of part of the service')],
        ['code' => '2', 'label' => __('Cancellation of electronic invoice')],
        ['code' => '3', 'label' => __('Partial or total discount')],
        ['code' => '4', 'label' => __('Price adjustment')],
        ['code' => '5', 'label' => __('Trade discount for prompt payment')],
        ['code' => '6', 'label' => __('Trade discount for sales volume')],
    ];

    $debitNoteConceptCodes = [
        ['code' => '1', 'label' => __('Interest')],
        ['code' => '2', 'label' => __('Expenses for collection')],
        ['code' => '3', 'label' => __('Change of value')],
        ['code' => '4', 'label' => __('Other')],
    ];

    $operationTypesByDocumentType = [
        '01' => [
            ['code' => '10', 'label' => __('Standard')],
            ['code' => '09', 'label' => __('AIU')],
            ['code' => '11', 'label' => __('Mandates')],
            ['code' => '12', 'label' => __('Transport')],
            ['code' => '14', 'label' => __('Notaries')],
            ['code' => '15', 'label' => __('Currency purchase')],
            ['code' => '16', 'label' => __('Currency sale')],
        ],
        '91' => [
            ['code' => '20', 'label' => __('Credit note referencing an electronic invoice')],
            ['code' => '22', 'label' => __('Credit note without reference to an electronic invoice')],
        ],
        '92' => [
            ['code' => '30', 'label' => __('Debit note referencing an electronic invoice')],
            ['code' => '32', 'label' => __('Debit note without reference to an electronic invoice')],
        ],
    ];

    $referenceRequiredOperationTypes = ['20', '30'];
    $periodOperationTypes = ['22', '32'];

    $identificationTypes = [
        '11' => __('Civil registry'),
        '12' => __('Identity card'),
        '13' => __('Citizenship card'),
        '21' => __('Foreigner card'),
        '22' => __('Foreigner ID card'),
        '31' => __('NIT'),
        '41' => __('Passport'),
        '42' => __('Foreign identification document'),
        '47' => __('PEP (Special Permanence Permit)'),
        '48' => __('PPT (Temporary Protection Permit)'),
        '50' => __('NIT from another country'),
        '91' => __('NUIP'),
    ];

    $basicSelectConfig = \App\Support\SelectConfig::basic();
    $searchableSelectConfig = \App\Support\SelectConfig::searchable();
@endphp

<x-layouts.app :title="__('New document')">
    @include('partials.tittle', [
        'title' => __('New document'),
        'subheading' => __('Issue an electronic document for this company.'),
    ])

    @if ($posMode ?? false)
        @include('pos.partials.tabs', ['activeTab' => 'sell'])
    @else
        <div class="mb-6">
            <a href="{{ route('documents.index') }}" class="text-sm font-medium text-accent hover:underline">&larr; {{ __('Back to issued documents') }}</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="doc-no-resolution-warning" class="mb-6 rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400 hidden {{ ($posMode ?? false) ? '!hidden' : '' }}">
        {{ __('There is no active numbering resolution for this document type in the current environment.') }}
    </div>

    <form id="documentForm" method="POST" action="{{ ($posMode ?? false) ? route('pos.checkout') : route('documents.store') }}" class="flex flex-col gap-6">
        @csrf
        @if ($quotationId ?? null)
            <input type="hidden" name="quotation_id" value="{{ $quotationId }}">
        @endif

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Document') }}</h3>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="{{ ($posMode ?? false) ? 'hidden' : '' }}">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Document type') }}</label>
                    <select id="doc-tipo_documento" name="tipo_documento" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        @foreach ($documentTypeLabels as $code => $label)
                            <option value="{{ $code }}" @selected(old('tipo_documento', '01') === $code)>{{ $code }} - {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="{{ ($posMode ?? false) ? 'hidden' : '' }}">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Operation type') }}</label>
                    <select id="doc-tipo_operacion" name="tipo_operacion" data-hs-select='{!! $basicSelectConfig !!}' class="hidden"></select>
                </div>
                <div class="{{ ($posMode ?? false) ? 'hidden' : '' }}">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Resolution') }}</label>
                    <select id="doc-resolution" name="resolution_id" data-hs-select='{!! $basicSelectConfig !!}' class="hidden"></select>
                </div>
                @if ($posMode ?? false)
                    <flux:input id="doc-prefix-display" :label="__('Prefix')" value="{{ $shift->fvResolution?->prefix }}" readonly disabled />
                    <flux:input id="doc-secuencial-display" :label="__('Number')" value="{{ $shift->fvResolution ? ($shift->fvResolution->current_number ?: $shift->fvResolution->range_from) : '' }}" readonly disabled />
                @else
                    <flux:input id="doc-prefix-display" :label="__('Prefix')" value="" readonly disabled />
                    <flux:input id="doc-secuencial-display" :label="__('Number')" value="" readonly disabled />
                @endif
                <x-date-picker name="issue_date" :label="__('Issue date')" :value="old('issue_date')" readonly />
            </div>
        </div>

        <div id="doc-reference-section" class="border border-gray-200 rounded-lg dark:border-neutral-700 hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Referenced invoice') }}</h3>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div id="doc-referencia_factura_id-wrapper">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="doc-referencia_factura_id">{{ __('Invoice number') }}</label>
                    <input type="text" id="doc-referencia_factura_id" name="referencia_factura_id" autocomplete="off"
                        placeholder="{{ __('e.g. FEED12345') }}"
                        class="w-full uppercase bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">

                    <div id="doc-factura-lookup-status" class="mt-2 text-sm"></div>
                </div>
                <div id="doc-referencia_periodo-wrapper" class="hidden">
                    <x-date-range-picker name-from="referencia_periodo_desde" name-to="referencia_periodo_hasta" :label="__('Period')" />
                </div>
                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Concept') }}</label>
                    <select id="doc-referencia_concepto_codigo" name="referencia_concepto_codigo" data-hs-select='{!! $basicSelectConfig !!}' class="hidden"></select>
                </div>
                <div id="doc-factura-manual-fields" class="hidden sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:input id="doc-referencia_factura_uuid" name="referencia_factura_uuid" :label="__('UUID (CUFE)')" />
                            </div>
                            <button type="button" id="doc-validate-uuid-btn"
                                class="hidden h-10 py-2 px-3 inline-flex items-center gap-x-1 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent disabled:opacity-50 disabled:pointer-events-none">
                                {{ __('Validate') }}
                            </button>
                        </div>
                    </div>
                    <x-date-picker name="referencia_factura_fecha_emision" :label="__('Invoice issue date')" :allow-empty="true" />
                </div>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700" data-dian-lookup-form>
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Customer') }}</h3>
            </div>
            <div class="p-4 flex flex-col gap-4">
                <div class="relative">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="doc-cliente-search">{{ __('Search existing client') }}</label>
                    <div class="relative">
                        <input type="text" id="doc-cliente-search" autocomplete="off"
                            placeholder="{{ __('Search by name or identification') }}"
                            class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 pe-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <button type="button" id="doc-cliente-search-open-modal"
                            class="absolute inset-y-0 end-0 flex items-center justify-center w-10 text-zinc-400 hover:text-accent focus:outline-hidden"
                            aria-label="{{ __('Search all clients') }}" title="{{ __('Search all clients') }}">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </button>
                    </div>

                    <div id="doc-cliente-search-results" class="hidden absolute z-20 mt-1 w-full max-h-72 overflow-y-auto bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 rounded-lg shadow-xl [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>

                    <div id="doc-cliente-search-status" class="mt-2 text-sm"></div>
                </div>

                <div class="flex gap-3" data-dian-lookup>
                    <flux:field class="w-44 shrink-0 [&>.hs-select]:max-w-[11rem]">
                        <flux:label>{{ __('Identification type') }}</flux:label>
                        <select id="doc-cliente_tipo_identificacion" name="cliente_tipo_identificacion" data-hs-select='{!! $basicSelectConfig !!}' data-dian-lookup-type class="hidden">
                            @foreach ($identificationTypes as $code => $label)
                                <option value="{{ $code }}" @selected(old('cliente_tipo_identificacion', '13') == $code)>{{ $code }} - {{ $label }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                    <div class="flex-1 relative">
                        <flux:input id="doc-cliente_identificacion" name="cliente_identificacion" :label="__('Identification')" value="{{ old('cliente_identificacion') }}" data-dian-lookup-number required />
                        <div class="absolute bottom-0 end-0 h-10 flex items-center pe-3 pointer-events-none">
                            <div class="hidden" data-dian-lookup-spinner>
                                <div class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full text-accent" role="status" aria-label="{{ __('Loading') }}">
                                    <span class="sr-only">{{ __('Loading') }}...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="doc-cliente_dv_wrapper" class="hidden w-16 shrink-0" data-dian-lookup-dv-wrapper>
                        <flux:input id="doc-cliente_dv" name="cliente_dv" :label="__('DV')" maxlength="1" value="{{ old('cliente_dv') }}" readonly data-dian-lookup-dv />
                    </div>
                </div>

                <div class="-mt-3 text-xs">
                    <span data-dian-lookup-status class="hidden text-zinc-500 dark:text-neutral-400"></span>
                </div>

                <flux:input id="doc-cliente_nombre" name="cliente_nombre" :label="__('Name')" value="{{ old('cliente_nombre') }}" data-dian-lookup-name required />

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Person type') }}</label>
                    <select id="doc-cliente_tipo_persona" name="cliente_tipo_persona" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="2" @selected(old('cliente_tipo_persona', '2') === '2')>{{ __('Natural person') }}</option>
                        <option value="1" @selected(old('cliente_tipo_persona') === '1')>{{ __('Legal entity') }}</option>
                    </select>
                </div>

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Fiscal responsibilities') }}</label>
                    @php $oldFiscalResponsibilities = old('cliente_responsabilidades', []); @endphp
                    <select id="doc-cliente_responsabilidades" name="cliente_responsabilidades[]" multiple data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        @foreach ($fiscalResponsibilities as $responsibility)
                            <option value="{{ $responsibility->codigo }}" @selected(in_array($responsibility->codigo, $oldFiscalResponsibilities))>
                                {{ $responsibility->codigo }} - {{ $responsibility->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <flux:input id="doc-cliente_direccion" name="cliente_direccion" :label="__('Address')" value="{{ old('cliente_direccion') }}" required />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Department') }}</label>
                        <select id="doc-cliente_departamento_codigo" name="cliente_departamento_codigo" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->codigo }}" @selected(old('cliente_departamento_codigo') === $department->codigo)>{{ $department->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('City') }}</label>
                        <select id="doc-cliente_ciudad_codigo" name="cliente_ciudad_codigo" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="doc-cliente_telefono" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Phone') }}</label>
                        <div class="relative">
                            <input type="text" id="doc-cliente_telefono" name="cliente_telefono" value="{{ old('cliente_telefono') }}" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="doc-cliente_email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                        <div class="relative">
                            <input type="email" id="doc-cliente_email" name="cliente_email" value="{{ old('cliente_email') }}" data-dian-lookup-email class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($posMode ?? false)
            <input type="hidden" id="pos-emitir-electronica-input" name="emitir_electronica" value="0">
        @endif

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <button type="button" class="w-full px-4 py-3 flex justify-between items-center" onclick="toggleChargesSection()">
                <span class="flex items-center gap-2">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Charges and discounts') }}</h3>
                    <span class="text-xs text-gray-400 dark:text-neutral-500">({{ __('Optional') }})</span>
                </span>
                <svg id="chargesToggleIcon" class="shrink-0 size-4 text-gray-500 dark:text-neutral-400 transition-transform" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div id="chargesSectionBody" class="hidden">
                <div id="chargeLinesBody" class="divide-y divide-gray-200 dark:divide-neutral-700 border-t border-gray-200 dark:border-neutral-700"></div>
                <div class="p-4 border-t border-gray-200 dark:border-neutral-700">
                    <flux:button type="button" size="sm" variant="filled" icon="plus" onclick="addChargeLine()">{{ __('Add charge or discount') }}</flux:button>
                </div>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Lines') }}</h3>
            </div>
            <div class="hidden lg:flex flex-wrap items-center gap-2 px-3 pt-3 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                <div class="w-56">{{ __('Search product') }}</div>
                <div class="w-56">{{ __('Description') }}</div>
                <div class="w-56">{{ __('Warehouse') }}</div>
                <div class="w-32">{{ __('Quantity') }}</div>
                @if ($priceTypes->isNotEmpty())
                    <div class="w-48 hs-dropdown [--auto-close:false] relative">
                        <button type="button" id="doc-bulk-price-type-btn" class="hs-dropdown-toggle inline-flex items-center gap-1 hover:text-accent focus:outline-hidden" title="{{ __('Apply price type to all lines') }}">
                            {{ __('Unit price') }}
                            <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-64 bg-white border border-zinc-200 rounded-lg shadow-xl p-3 space-y-2 dark:bg-neutral-800 dark:border-neutral-700">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Apply price type to all lines') }}</p>
                            <div class="flex flex-col">
                                @foreach ($priceTypes as $priceType)
                                    <button type="button" class="doc-bulk-price-type-option text-start px-2 py-1.5 text-sm rounded-lg text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10" data-price-type-id="{{ (string) $priceType->_id }}">
                                        {{ $priceType->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="w-48">{{ __('Unit price') }}</div>
                @endif
                <div class="w-40">{{ __('Discount') }}</div>
                <div class="flex-1">{{ __('Taxes') }}</div>
                <div class="w-24 text-end">{{ __('Subtotal') }}</div>
                <div class="w-10"></div>
            </div>
            <div id="documentLinesBody" class="divide-y divide-gray-200 dark:divide-neutral-700"></div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-neutral-700 flex justify-end items-center gap-3">
                <span class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('Total') }}</span>
                <span id="documentLinesTotal" class="text-sm font-semibold text-gray-800 dark:text-neutral-200">$0.00</span>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex justify-between items-center">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Payment') }}</h3>
                <flux:button type="button" size="sm" variant="filled" icon="plus" onclick="addPaymentLine()">{{ __('Add payment') }}</flux:button>
            </div>

            <div id="paymentLinesBody" class="divide-y divide-gray-200 dark:divide-neutral-700"></div>

            @if ($posMode ?? false)
                <div id="pos-cash-section" class="hidden p-4 border-t border-gray-200 dark:border-neutral-700 flex flex-wrap items-end gap-4">
                    <div class="w-48">
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="pos-efectivo-display">{{ __('Cash received') }}</label>
                        <div class="relative">
                            <input type="hidden" id="pos-efectivo-hidden" name="efectivo_recibido" value="">
                            <input type="text" inputmode="decimal" id="pos-efectivo-display"
                                class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                            <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                            </div>
                        </div>
                    </div>
                    <div class="w-48">
                        <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Change') }}</span>
                        <span id="pos-change-display" class="block text-sm font-semibold text-gray-800 dark:text-neutral-200 h-10 leading-10">$0.00</span>
                    </div>
                </div>
            @endif
        </div>

        @if ($posMode ?? false)
            <div id="pos-checkout-error" class="hidden rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ route('documents.index') }}">
                <flux:button type="button" variant="filled">{{ __('Cancel') }}</flux:button>
            </a>
            <flux:button type="submit" variant="primary" id="documentSubmitBtn">{{ __('Issue document') }}</flux:button>
        </div>
    </form>

    @if ($posMode ?? false)
        <div id="pos-result-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="pos-result-modal-label">
            <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
                <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 id="pos-result-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Sale completed') }}</h3>
                        <span id="pos-result-numeral" class="text-sm text-zinc-500 dark:text-zinc-400"></span>
                    </div>
                    <div class="p-4">
                        <div id="pos-result-message" class="mb-3 rounded-md p-3 text-sm hidden"></div>
                        <iframe id="pos-result-preview" class="w-full rounded-lg border border-gray-200 dark:border-neutral-700" style="height: 50vh;" title="{{ __('Receipt preview') }}"></iframe>
                    </div>
                    <div class="p-4 pt-0 grid grid-cols-2 gap-2">
                        <flux:button type="button" id="pos-result-issue-electronic-btn" variant="primary" icon="bolt" class="col-span-2 hidden" onclick="window.posIssueElectronic()">
                            {{ __('Issue electronic invoice') }}
                        </flux:button>
                        <flux:button type="button" variant="filled" icon="printer" onclick="window.posPrintReceipt()">{{ __('Print') }}</flux:button>
                        <flux:button type="button" variant="filled" icon="arrow-down-tray" onclick="window.posDownloadReceipt()">{{ __('Download') }}</flux:button>
                        <a href="{{ route('pos.sales.index') }}" class="col-span-2">
                            <flux:button type="button" variant="filled" icon="receipt-percent" class="w-full">{{ __('Go to sales') }}</flux:button>
                        </a>
                        <flux:button type="button" variant="primary" class="col-span-2" onclick="window.posNewSale()">{{ __('New sale') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div id="validate-uuid-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="validate-uuid-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-3xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="validate-uuid-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Validate document with the DIAN') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#validate-uuid-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div id="validate-uuid-modal-body" class="p-4 max-h-[70vh] overflow-y-auto text-sm text-gray-700 dark:text-neutral-300 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                </div>
                <div class="flex justify-end p-4 border-t border-gray-200 dark:border-neutral-700">
                    <flux:button type="button" variant="filled" data-hs-overlay="#validate-uuid-modal">{{ __('Close') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: buscar en todos los clientes -->
    <div id="all-clients-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="all-clients-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-3xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="all-clients-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Search all clients') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#all-clients-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    @if ($clients->isEmpty())
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('You have no registered clients yet.') }}</p>
                    @else
                        <div class="relative mb-3">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <input type="text" id="all-clients-search" placeholder="{{ __('Search') }}" autocomplete="off"
                                class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700" id="all-clients-table">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($clients as $client)
                                    <tr>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $client['name'] }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">{{ $client['identificacion'] }}</td>
                                        <td class="px-3 py-2 text-end">
                                            <button type="button" class="text-xs font-medium text-accent hover:underline" onclick="selectClientFromModal('{{ $client['id'] }}')">
                                                {{ __('Select this client') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: buscar en todos los productos (para cualquier línea) -->
    <div id="all-products-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="all-products-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-3xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="all-products-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Search all products') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#all-products-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    <div class="relative mb-3">
                        <label class="sr-only">{{ __('Search') }}</label>
                        <input type="text" id="all-products-search" placeholder="{{ __('Code, barcode or description') }}" autocomplete="off"
                            class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Barcode') }}</th>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</th>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Stock') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody id="all-products-tbody" class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
                    </table>
                    <p id="all-products-empty" class="hidden text-sm text-neutral-500 dark:text-neutral-400">{{ __('No products found.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <template id="paymentLineTemplate">
        <div class="payment-line p-4 grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-4 items-end">
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Payment form') }}</label>
                <select name="payment_means_id[__INDEX__]" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                    <option value="1" selected>{{ __('Cash') }}</option>
                    <option value="2">{{ __('Credit') }}</option>
                </select>
            </div>
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Payment method') }}</label>
                @if ($posMode ?? false)
                    <select name="payment_method_id[__INDEX__]" class="payment-code-select hidden" data-hs-select='{!! $searchableSelectConfig !!}'>
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->_id }}" data-dian-code="{{ $paymentMethod->dian_payment_means_code }}">{{ $paymentMethod->name }}</option>
                        @endforeach
                    </select>
                @else
                    <select name="payment_means_code[__INDEX__]" class="payment-code-select hidden" data-hs-select='{!! $searchableSelectConfig !!}'>
                        @foreach ($paymentMeansCodes as $paymentMeansCode)
                            <option value="{{ $paymentMeansCode->codigo }}" data-dian-code="{{ $paymentMeansCode->codigo }}" @selected($paymentMeansCode->codigo === '10')>{{ $paymentMeansCode->medio }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <x-date-picker name="payment_due_date[__INDEX__]" :label="__('Due date')" :allow-empty="true" />
            <button type="button" class="h-10 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" onclick="removePaymentLine(this)">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
            </button>
        </div>
    </template>

    <template id="chargeLineTemplate">
        <div class="charge-line p-4 grid grid-cols-1 sm:grid-cols-[1fr_2fr_1fr_1fr_auto] gap-4 items-end">
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Type') }}</label>
                <select name="cargo_tipo[__INDEX__]" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                    <option value="descuento" selected>{{ __('Discount') }}</option>
                    <option value="cargo">{{ __('Charge') }}</option>
                </select>
            </div>
            <flux:input class="charge-motivo" name="cargo_motivo[__INDEX__]" :label="__('Reason')" required />
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Value type') }}</label>
                <select class="charge-valor-tipo hidden" name="cargo_valor_tipo[__INDEX__]" data-hs-select='{!! $basicSelectConfig !!}'>
                    <option value="porcentaje" selected>{{ __('Percentage') }}</option>
                    <option value="fijo">{{ __('Fixed amount') }}</option>
                </select>
            </div>
            <flux:input class="charge-valor" type="number" step="0.01" min="0" name="cargo_valor[__INDEX__]" :label="__('Value')" required />
            <button type="button" class="h-10 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" onclick="removeChargeLine(this)">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
            </button>
        </div>
    </template>

    <template id="documentLineTemplate">
        <div class="document-line p-3 space-y-2">
            <input type="hidden" class="line-unidad" name="items[__INDEX__][unidad_medida]" value="EA">

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-56">
                    <input type="text" class="line-product-search w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs h-10 py-2 px-3 pe-9 focus:outline-hidden focus:ring-2 focus:ring-accent" autocomplete="off" placeholder="{{ __('Code, barcode or description') }}">
                    <button type="button" class="line-product-search-open-modal absolute inset-y-0 end-0 flex items-center justify-center w-9 text-zinc-400 hover:text-accent focus:outline-hidden" aria-label="{{ __('Search all products') }}" title="{{ __('Search all products') }}">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                    <div class="line-product-results hidden absolute z-10 mt-1 w-64 bg-white border border-zinc-200 rounded-lg shadow-lg max-h-56 overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:bg-neutral-800 dark:border-neutral-700 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                    <input type="hidden" class="line-codigo" name="items[__INDEX__][codigo]">
                    <input type="hidden" class="line-barcode" name="items[__INDEX__][codigo_barras]">
                </div>

                <div class="w-56">
                    <flux:input class:input="line-descripcion" name="items[__INDEX__][descripcion]" />
                </div>

                <div class="w-56">
                    <select class="line-bodega hidden" name="items[__INDEX__][bodega_id]" data-hs-select='{!! $basicSelectConfig !!}' disabled></select>
                </div>

                <div class="w-32">
                    <div class="line-cantidad-wrapper bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg h-10" data-hs-input-number='{"step": 1, "min": 0}'>
                        <div class="w-full h-full flex justify-between items-center gap-x-1">
                            <div class="grow px-3">
                                <input class="line-cantidad w-full h-full p-0 bg-transparent border-0 text-sm text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-400 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" style="-moz-appearance: textfield;" type="number" aria-roledescription="{{ __('Quantity') }}" name="items[__INDEX__][cantidad]" value="0" required data-hs-input-number-input>
                            </div>
                            <div class="flex flex-col h-full divide-y divide-zinc-200 dark:divide-white/10 border-s border-zinc-200 dark:border-white/10">
                                <button type="button" class="flex-1 w-7 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-se-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Increase') }}" data-hs-input-number-increment>
                                    <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                </button>
                                <button type="button" class="flex-1 w-7 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-ee-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Decrease') }}" data-hs-input-number-decrement>
                                    <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-48">
                    <div class="relative">
                        <input type="hidden" class="line-precio" name="items[__INDEX__][precio_unitario]" value="0">
                        <input type="text" inputmode="decimal" class="line-precio-display h-10 py-2 px-3 ps-6 pe-9 block w-full {{ $canEditPrice ? 'bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300' : 'bg-zinc-50 dark:bg-white/5 text-zinc-500 dark:text-neutral-400 cursor-not-allowed' }} border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0"
                            @unless ($canEditPrice) readonly title="{{ __('Only an administrator can type a custom price -- pick from the price list instead.') }}" @endunless>
                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                        </div>
                        <div class="line-precio-select-wrapper hidden absolute inset-y-0 end-0 flex items-center pe-0.5">
                            <label class="sr-only">{{ __('Price list') }}</label>
                            <select class="line-precio-select hidden" data-hs-select='{!! \App\Support\SelectConfig::iconTrigger() !!}' title="{{ __('Price list') }}"></select>
                        </div>
                    </div>
                </div>

                <div class="w-40">
                    <div class="relative">
                        <input type="hidden" class="line-descuento-valor" name="items[__INDEX__][descuento_valor]" value="0">
                        <input type="hidden" class="line-descuento-tipo" name="items[__INDEX__][descuento_valor_tipo]" value="porcentaje">
                        <input type="text" inputmode="decimal" class="line-descuento-display h-10 py-2 px-3 ps-6 pe-9 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                            <span class="line-descuento-prefix text-xs text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                        <div class="absolute inset-y-0 end-0 flex items-center pe-0.5">
                            <label class="sr-only">{{ __('Value type') }}</label>
                            <select class="line-descuento-tipo-select hidden" data-hs-select='{!! \App\Support\SelectConfig::iconTrigger() !!}' title="{{ __('Value type') }}">
                                <option value="porcentaje" selected>{{ __('Percentage') }}</option>
                                <option value="fijo">{{ __('Fixed amount') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex flex-col items-start gap-1">
                        <div class="line-taxes-body flex flex-col gap-1"></div>

                        <div class="hs-dropdown [--auto-close:false] relative inline-flex">
                            <button type="button" class="line-tax-add-btn hs-dropdown-toggle h-10 ps-3 pe-4 inline-flex items-center justify-center gap-2 rounded-lg text-sm font-medium whitespace-nowrap bg-zinc-800/5 hover:bg-zinc-800/10 dark:bg-white/10 dark:hover:bg-white/20 text-zinc-800 dark:text-white focus:outline-hidden" onclick="prefillNewTaxBase(this)">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                <span class="line-tax-add-label">{{ __('Add tax') }}</span>
                            </button>
                            <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-64 bg-white border border-zinc-200 rounded-lg shadow-xl p-3 space-y-3 dark:bg-neutral-800 dark:border-neutral-700">
                                <div>
                                    <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Tax') }}</label>
                                    <select class="line-newtax-tipo hidden" data-hs-select='{!! $basicSelectConfig !!}'>
                                        <option value="01" selected>01 - IVA</option>
                                        <option value="03">03 - ICA</option>
                                        <option value="04">04 - INC</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Percentage') }}</label>
                                    <div class="relative">
                                        <input type="hidden" class="line-newtax-porcentaje" value="19">
                                        <input type="text" inputmode="decimal" class="line-newtax-porcentaje-display h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0" value="19">
                                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Taxable base') }}</label>
                                    <div class="relative">
                                        <input type="hidden" class="line-newtax-base" value="">
                                        <input type="text" inputmode="decimal" class="line-newtax-base-display h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="{{ __('Subtotal') }}">
                                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <flux:button type="button" size="sm" variant="primary" onclick="saveLineTax(this)">{{ __('Save') }}</flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-24 text-end">
                    <span class="line-subtotal block text-sm font-semibold text-gray-800 dark:text-neutral-200 h-10 leading-10">$0.00</span>
                </div>

                <button type="button" class="line-delete-btn flex size-10 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400 disabled:opacity-30 disabled:pointer-events-none" aria-label="{{ __('Delete') }}" onclick="removeDocumentLine(this)" disabled>
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                </button>
            </div>
        </div>
    </template>

    <template id="documentLineTaxBadgeTemplate">
        <div class="line-tax-row flex items-center gap-1">
            <div class="line-tax inline-flex items-center gap-2 rounded-full bg-zinc-100 dark:bg-white/10 px-3 py-1 text-xs text-zinc-700 dark:text-zinc-300">
                <input type="hidden" class="line-tax-tipo" name="items[__LINEINDEX__][impuestos][__TAXINDEX__][tipo]">
                <input type="hidden" class="line-tax-porcentaje" name="items[__LINEINDEX__][impuestos][__TAXINDEX__][porcentaje]">
                <input type="hidden" class="line-tax-base" name="items[__LINEINDEX__][impuestos][__TAXINDEX__][base_gravable]">
                <span class="line-tax-label font-medium"></span>
                <button type="button" class="text-zinc-400 hover:text-red-600 dark:hover:text-red-400 focus:outline-hidden" aria-label="{{ __('Delete') }}" onclick="removeLineTax(this)">
                    <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>
        </div>
    </template>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            (function () {
                const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));
                const allClientsById = @json($clients->keyBy('id'));
                const quotationPrefill = @json($quotationPrefill ?? null);
                let lineIndex = 0;
                let paymentLineIndex = 0;
                let chargeLineIndex = 0;

                function setSelectValue(el, value) {
                    const instance = window.HSSelect && HSSelect.getInstance(el);
                    if (instance) {
                        instance.setValue(value ?? '');
                    } else {
                        el.value = value ?? '';
                    }
                }

                function rebuildCitySelect(citySelect, departmentCode, selectedCityCode = '') {
                    const instance = window.HSSelect && HSSelect.getInstance(citySelect);
                    if (instance && typeof instance.destroy === 'function') {
                        instance.destroy();
                        citySelect.parentElement.appendChild(citySelect);
                    }

                    const municipios = municipiosByDepartment[departmentCode] || [];
                    citySelect.innerHTML = '<option value="">{{ __('Select...') }}</option>';
                    municipios.forEach((municipio) => {
                        const option = document.createElement('option');
                        option.value = municipio.codigo;
                        option.textContent = municipio.descripcion.trim();
                        option.selected = municipio.codigo === selectedCityCode;
                        citySelect.appendChild(option);
                    });

                    if (window.HSSelect) {
                        new HSSelect(citySelect);
                    }
                }

                const operationTypesByDocumentType = @json($operationTypesByDocumentType);
                const creditNoteConceptCodes = @json($creditNoteConceptCodes);
                const debitNoteConceptCodes = @json($debitNoteConceptCodes);
                const referenceRequiredOperationTypes = @json($referenceRequiredOperationTypes);
                const periodOperationTypes = @json($periodOperationTypes);
                const createOptionsUrl = '{{ route('documents.create-options') }}';
                const facturaLookupUrl = '{{ route('documents.create-factura-lookup') }}';
                const validateUuidUrl = '{{ route('documents.create-validate-uuid') }}';
                const clientSearchUrl = '{{ route('documents.create-client-search') }}';
                const productSearchUrl = '{{ route('documents.create-product-search') }}';

                function rebuildSelect(selectEl, options) {
                    const instance = window.HSSelect && HSSelect.getInstance(selectEl);
                    if (instance && typeof instance.destroy === 'function') {
                        instance.destroy();
                        selectEl.parentElement.appendChild(selectEl);
                    }

                    selectEl.innerHTML = '';
                    options.forEach((opt) => {
                        const option = document.createElement('option');
                        option.value = opt.value;
                        option.textContent = opt.label;
                        Object.entries(opt.dataset || {}).forEach(([key, val]) => {
                            option.dataset[key] = val;
                        });
                        selectEl.appendChild(option);
                    });

                    if (window.HSSelect) {
                        new HSSelect(selectEl);
                    }
                }

                /**
                 * Pinta el prefijo/número del documento a partir de la
                 * resolución elegida. En modo POS el prefijo/número siempre
                 * son los de la resolución "FV" del turno (ya vienen
                 * pintados desde el servidor, ver "$shift->fvResolution" en
                 * el blade) -- esta función no hace nada; si se dejara
                 * correr, pisaría esos valores con los de la resolución de
                 * facturación electrónica (la que sí carga este select,
                 * oculto en POS).
                 * @returns {void}
                 */
                function fillPrefixSecuencial() {
                    if ({{ ($posMode ?? false) ? 'true' : 'false' }}) {
                        return;
                    }

                    const resolutionSelect = document.getElementById('doc-resolution');
                    const option = resolutionSelect.selectedOptions[0];
                    document.getElementById('doc-prefix-display').value = option?.dataset.prefix || '';
                    document.getElementById('doc-secuencial-display').value = option?.dataset.nextNumber || '';
                }

                function resetFacturaLookup() {
                    document.getElementById('doc-referencia_factura_id').value = '';
                    document.getElementById('doc-factura-lookup-status').innerHTML = '';
                    setFacturaFieldsReadonly(false, '', '');
                }

                /**
                 * Reconstruye el select de concepto de nota crédito/débito
                 * según el tipo de documento y operación. Sin referencia a
                 * una factura puntual (operación 22) no tiene sentido
                 * "Anulación de factura electrónica" -- ese concepto exige
                 * saber cuál factura se está anulando.
                 * @returns {void}
                 */
                function rebuildConceptCodes() {
                    const tipoDocumento = document.getElementById('doc-tipo_documento').value;
                    const tipoOperacion = document.getElementById('doc-tipo_operacion').value;
                    if (tipoDocumento !== '91' && tipoDocumento !== '92') {
                        return;
                    }

                    let conceptCodes = tipoDocumento === '91' ? creditNoteConceptCodes : debitNoteConceptCodes;

                    if (tipoOperacion === '22') {
                        conceptCodes = conceptCodes.filter(({ code }) => code !== '2');
                    }

                    rebuildSelect(document.getElementById('doc-referencia_concepto_codigo'), conceptCodes.map(({ code, label }) => ({ value: code, label: code + ' - ' + label })));
                }

                /**
                 * Muestra/oculta la sección de referencia (factura anulada o
                 * periodo) según el tipo de operación. En modo POS esta
                 * sección entera no existe en el DOM (ver "$posMode" en el
                 * blade), así que los `?.` de abajo simplemente no hacen nada.
                 * @returns {void}
                 */
                function updateReferenceVisibility() {
                    const tipoOperacion = document.getElementById('doc-tipo_operacion').value;
                    const requiresFullReference = referenceRequiredOperationTypes.includes(tipoOperacion);
                    const requiresPeriod = periodOperationTypes.includes(tipoOperacion);

                    document.getElementById('doc-reference-section')?.classList.toggle('hidden', ! requiresFullReference && ! requiresPeriod);
                    document.getElementById('doc-referencia_factura_id-wrapper')?.classList.toggle('hidden', ! requiresFullReference);
                    document.getElementById('doc-factura-manual-fields')?.classList.toggle('hidden', ! requiresFullReference);
                    document.getElementById('doc-validate-uuid-btn')?.classList.toggle('hidden', ! requiresFullReference);
                    document.getElementById('doc-referencia_periodo-wrapper')?.classList.toggle('hidden', ! requiresPeriod);

                    rebuildConceptCodes();

                    if (! requiresFullReference) {
                        resetFacturaLookup();
                    }
                    if (! requiresPeriod) {
                        const periodoRoot = document.querySelector('#doc-referencia_periodo-wrapper [data-daterange]');
                        if (periodoRoot) {
                            periodoRoot.querySelector('[data-daterange-hidden-from]').value = '';
                            periodoRoot.querySelector('[data-daterange-hidden-to]').value = '';
                            periodoRoot.querySelector('[data-daterange-trigger]').value = '';
                        }
                    }
                }

                /**
                 * Reconstruye el tipo de operación y las referencias para un
                 * tipo de documento, y recarga las resoluciones. En modo POS
                 * la resolución no se elige por documento (ya quedó fija al
                 * abrir el turno), así que no recarga nada -- evita además
                 * pisar el prefijo/número que el servidor ya pintó (ver
                 * fillPrefixSecuencial()).
                 * @param {string} tipoDocumento
                 * @returns {Promise<void>}
                 */
                async function applyDocumentType(tipoDocumento) {
                    const operationTypes = operationTypesByDocumentType[tipoDocumento] || [];
                    rebuildSelect(document.getElementById('doc-tipo_operacion'), operationTypes.map(({ code, label }) => ({ value: code, label: code + ' - ' + label })));
                    updateReferenceVisibility();

                    if ({{ ($posMode ?? false) ? 'true' : 'false' }}) {
                        return;
                    }

                    await reloadResolutionOptions(tipoDocumento);
                }

                /**
                 * Recarga solo las resoluciones disponibles para un tipo de
                 * documento (sin tocar tipo_operacion) -- separado de
                 * applyDocumentType() para poder llamarse solo. No aplica en
                 * modo POS (ver "$posMode" en el blade): ahí la resolución ya
                 * quedó fija al abrir el turno, este selector ni se muestra.
                 * @param {string} tipoDocumento
                 * @returns {Promise<void>}
                 */
                async function reloadResolutionOptions(tipoDocumento) {
                    const resolutionSelect = document.getElementById('doc-resolution');
                    rebuildSelect(resolutionSelect, [{ value: '', label: '{{ __('Loading...') }}' }]);

                    const response = await fetch(createOptionsUrl + '?tipo_documento=' + encodeURIComponent(tipoDocumento), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();

                    const resolutionOptions = data.resolutions.map((resolution) => ({
                        value: resolution.id,
                        label: resolution.label,
                        dataset: { prefix: resolution.prefix, nextNumber: resolution.next_number },
                    }));
                    rebuildSelect(resolutionSelect, resolutionOptions.length ? resolutionOptions : [{ value: '', label: '{{ __('No resolutions available') }}' }]);
                    fillPrefixSecuencial();

                    document.getElementById('doc-no-resolution-warning').classList.toggle('hidden', data.resolutions.length > 0);
                }

                let facturaLookupTimeout = null;

                /**
                 * Fija UUID/fecha de la factura referenciada como readOnly
                 * (no disabled): así el valor sí viaja en el submit del
                 * formulario -- cuando la factura se encontró, el UUID/fecha
                 * que trajo la búsqueda por AJAX quedan en el payload desde
                 * el frontend, sin que el backend tenga que volver a
                 * consultarlos.
                 * @param {boolean} readonly
                 * @param {string} uuidValue
                 * @param {string} fechaValue
                 * @returns {void}
                 */
                function setFacturaFieldsReadonly(readonly, uuidValue, fechaValue) {
                    const uuidInput = document.getElementById('doc-referencia_factura_uuid');
                    uuidInput.value = uuidValue ?? '';
                    uuidInput.readOnly = readonly;
                    uuidInput.classList.toggle('cursor-not-allowed', readonly);
                    uuidInput.classList.toggle('bg-zinc-50', readonly);
                    uuidInput.classList.toggle('dark:bg-white/5', readonly);
                    uuidInput.classList.toggle('text-zinc-500', readonly);
                    uuidInput.classList.toggle('dark:text-zinc-400', readonly);

                    const fechaWrapper = document.querySelector('#doc-factura-manual-fields [data-datepicker]');
                    if (fechaWrapper) {
                        const trigger = fechaWrapper.querySelector('[data-datepicker-trigger]');
                        if (fechaWrapper.datepickerSetValue) {
                            fechaWrapper.datepickerSetValue(fechaValue || '');
                        } else {
                            trigger.value = fechaValue ?? '';
                            fechaWrapper.querySelector('[data-datepicker-hidden]').value = fechaValue ?? '';
                        }
                        trigger.disabled = readonly;
                        trigger.classList.toggle('cursor-not-allowed', readonly);
                        trigger.classList.toggle('cursor-pointer', ! readonly);
                        trigger.classList.toggle('bg-zinc-50', readonly);
                        trigger.classList.toggle('bg-white', ! readonly);
                        trigger.classList.toggle('dark:bg-white/5', readonly);
                        trigger.classList.toggle('dark:bg-white/10', ! readonly);
                        trigger.classList.toggle('text-zinc-500', readonly);
                        trigger.classList.toggle('text-zinc-700', ! readonly);
                        trigger.classList.toggle('dark:text-zinc-400', readonly);
                        trigger.classList.toggle('dark:text-zinc-300', ! readonly);
                    }
                }

                function lookupFactura(numeral) {
                    const statusEl = document.getElementById('doc-factura-lookup-status');

                    if (! numeral) {
                        statusEl.innerHTML = '';
                        setFacturaFieldsReadonly(false, '', '');
                        return;
                    }

                    statusEl.innerHTML = '<span class="text-zinc-500 dark:text-zinc-400">{{ __('Searching...') }}</span>';

                    fetch(facturaLookupUrl + '?numeral=' + encodeURIComponent(numeral), {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (document.getElementById('doc-referencia_factura_id').value.trim() !== numeral) {
                                return;
                            }

                            if (data.found) {
                                statusEl.innerHTML = '<span class="text-green-700 dark:text-green-400">'
                                    + '{{ __('Document found') }}'
                                    + '</span>';
                                setFacturaFieldsReadonly(true, data.uuid, data.issue_date);
                            } else {
                                statusEl.innerHTML = '<span class="text-amber-700 dark:text-amber-400">'
                                    + '{{ __('Not found in our system. It may have been issued through another technology provider — enter its UUID and issue date below, or issue this as a note without reference.') }}'
                                    + '</span>';
                                setFacturaFieldsReadonly(false, '', '');
                            }
                        });
                }

                function dianEscapeHtml(value) {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
                }

                /**
                 * Normaliza un valor del XML de la DIAN a array siempre.
                 * xmlToArray() en el backend solo agrupa en array cuando hay
                 * más de una ocurrencia del mismo elemento -- si solo hay uno
                 * (p. ej. un único evento), llega como objeto suelto.
                 * @param {*} value
                 * @returns {Array}
                 */
                function dianAsArray(value) {
                    if (value === undefined || value === null || value === '') {
                        return [];
                    }
                    return Array.isArray(value) ? value : [value];
                }

                /**
                 * Arma el numeral completo de un documento DIAN. El "Folio"
                 * a veces ya trae el prefijo pegado (p. ej. Serie "SETP" +
                 * Folio "SETP990000000") y a veces no (Serie "FEL" + Folio
                 * "227106") -- evita duplicar el prefijo cuando el Folio ya
                 * lo trae.
                 * @param {object} numeroDocumento
                 * @returns {string}
                 */
                function dianFullNumeral(numeroDocumento) {
                    const serie = (numeroDocumento.Serie || '').trim();
                    const folio = (numeroDocumento.Folio || '').trim();
                    if (! folio) {
                        return '';
                    }
                    if (! serie || folio.startsWith(serie)) {
                        return folio;
                    }
                    return serie + folio;
                }

                function dianMoney(value) {
                    const number = parseFloat(value);
                    if (isNaN(number)) {
                        return dianEscapeHtml(value);
                    }
                    return '$' + number.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function dianBadge(text, tone) {
                    const tones = {
                        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                        zinc: 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300',
                    };
                    return '<span class="inline-flex items-center gap-x-1 py-1 px-2 rounded-full text-xs font-medium ' + (tones[tone] || tones.zinc) + '">' + dianEscapeHtml(text) + '</span>';
                }

                function dianEntity(entity) {
                    if (! entity) {
                        return '<span class="text-zinc-400">—</span>';
                    }
                    return '<p class="font-medium text-zinc-800 dark:text-white">' + dianEscapeHtml(entity.Nombre) + '</p>'
                        + '<p class="text-xs text-zinc-500 dark:text-zinc-400">'
                            + (entity.TipoDoc ? dianEscapeHtml(entity.TipoDoc) + ' ' : '')
                            + dianEscapeHtml(entity.NumeroDoc)
                        + '</p>';
                }

                /**
                 * Pinta la lista de validaciones de un documento DIAN,
                 * ocultando "Documento validado por la DIAN": no aporta nada
                 * nuevo, si el evento/documento aparece aquí es porque ya
                 * está validado, esa línea solo repite algo implícito.
                 * @param {object} validacionesDoc
                 * @returns {string}
                 */
                function dianValidaciones(validacionesDoc) {
                    const items = dianAsArray(validacionesDoc && validacionesDoc.ValidacionDoc)
                        .filter((item) => item.Nombre !== 'Documento validado por la DIAN');
                    if (items.length === 0) {
                        return '';
                    }

                    return '<div class="mt-2 space-y-1.5">'
                        + items.map((item) => {
                            const isValid = String(item.IsValida).toLowerCase() === 'true';
                            const icon = isValid
                                ? '<svg class="shrink-0 size-3.5 mt-0.5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
                                : '<svg class="shrink-0 size-3.5 mt-0.5 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                            return '<div class="flex items-start gap-x-2 text-xs">'
                                + icon
                                + '<span class="text-zinc-600 dark:text-zinc-400">'
                                    + '<span class="font-medium text-zinc-700 dark:text-zinc-300">' + dianEscapeHtml(item.Nombre) + '</span>'
                                    + (item.MensajeError ? ': ' + dianEscapeHtml(item.MensajeError) : '')
                                + '</span>'
                            + '</div>';
                        }).join('')
                        + '</div>';
                }

                function dianEstados(estado) {
                    const items = dianAsArray(estado && estado.KeyValueOfintstring);
                    if (items.length === 0) {
                        return '';
                    }

                    return '<div class="flex flex-wrap gap-2 mb-4">'
                        + items.map((item) => dianBadge(item.Key + ' · ' + item.Value, 'zinc')).join('')
                        + '</div>';
                }

                function dianIsTituloValor(estado) {
                    return dianAsArray(estado && estado.KeyValueOfintstring)
                        .some((item) => String(item.Value).toLowerCase().includes('título valor') || String(item.Value).toLowerCase().includes('titulo valor'));
                }

                function dianTituloValorWarning(estado) {
                    if (! dianIsTituloValor(estado)) {
                        return '';
                    }

                    return '<div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40">'
                        + '<p class="text-sm text-amber-800 dark:text-amber-400">'
                            + '{{ __('This invoice is registered as a negotiable instrument (Título Valor). A note referencing it directly can no longer be issued — it must be issued without reference instead, using a period.') }}'
                        + '</p>'
                        + '<button type="button" id="doc-use-without-reference-btn" class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-400 underline hover:no-underline">'
                            + '{{ __('Switch to "without reference"') }}'
                        + '</button>'
                    + '</div>';
                }

                function dianEvento(evento) {
                    const numeroDocumento = evento.NumeroDocumento || {};

                    return '<div class="relative ps-6 pb-5 last:pb-0 border-s-2 border-zinc-200 dark:border-white/10 last:border-transparent">'
                        + '<span class="absolute -start-[7px] top-0.5 size-3 rounded-full bg-accent"></span>'
                        + '<p class="text-sm font-semibold text-zinc-800 dark:text-white">' + dianEscapeHtml(evento.Descripcion) + '</p>'
                        + '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">'
                            + dianEscapeHtml(numeroDocumento.Folio)
                            + (numeroDocumento.FechaEmision ? ' · ' + dianEscapeHtml(numeroDocumento.FechaEmision) : '')
                        + '</p>'
                        + (evento.Emisor ? '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('By') }} ' + dianEscapeHtml(evento.Emisor.Nombre) + '</p>' : '')
                        + dianValidaciones(evento.ValidacionesDoc)
                    + '</div>';
                }

                function renderDianDocument(doc) {
                    const emisor = doc.Emisor || {};
                    const receptor = doc.Receptor || {};
                    const numeroDocumento = doc.NumeroDocumento || {};
                    const totales = doc.TotalEImpuestos || {};
                    const eventos = dianAsArray(doc.Eventos && doc.Eventos.Evento);

                    let html = '';

                    html += '<div class="flex items-start justify-between gap-4 pb-4 mb-4 border-b border-zinc-200 dark:border-white/10">'
                        + '<div>'
                            + '<p class="font-semibold text-zinc-800 dark:text-white">' + dianEscapeHtml(doc.DocumentTypeName) + ' (' + dianEscapeHtml(doc.DocumentTypeId) + ')</p>'
                            + '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">'
                                + dianEscapeHtml(dianFullNumeral(numeroDocumento))
                                + (numeroDocumento.FechaEmision ? ' · ' + dianEscapeHtml(numeroDocumento.FechaEmision) : '')
                            + '</p>'
                        + '</div>'
                        + dianBadge('{{ __('Found by the DIAN') }}', 'green')
                    + '</div>';

                    html += dianEstados(doc.Estado);
                    html += dianTituloValorWarning(doc.Estado);

                    html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">'
                        + '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5">'
                            + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Issuer') }}</p>'
                            + dianEntity(emisor)
                        + '</div>'
                        + '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5">'
                            + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Recipient') }}</p>'
                            + dianEntity(receptor)
                        + '</div>'
                    + '</div>';

                    if (doc.LegitimoTenedor && doc.LegitimoTenedor.Nombre) {
                        html += '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5 mb-4">'
                            + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Current holder') }}</p>'
                            + '<p class="font-medium text-zinc-800 dark:text-white">' + dianEscapeHtml(doc.LegitimoTenedor.Nombre) + '</p>'
                            + (doc.LegitimoTenedor.FechaInscripcionComoTituloValor
                                ? '<p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Registered as a negotiable instrument on') }} ' + dianEscapeHtml(doc.LegitimoTenedor.FechaInscripcionComoTituloValor) + '</p>'
                                : '')
                        + '</div>';
                    }

                    if (totales.Total !== undefined) {
                        html += '<div class="grid grid-cols-2 gap-4 mb-4">'
                            + '<div>'
                                + '<p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('VAT') }}</p>'
                                + '<p class="font-semibold text-zinc-800 dark:text-white">' + dianMoney(totales.Iva) + '</p>'
                            + '</div>'
                            + '<div>'
                                + '<p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>'
                                + '<p class="font-semibold text-zinc-800 dark:text-white">' + dianMoney(totales.Total) + '</p>'
                            + '</div>'
                        + '</div>';
                    }

                    html += '<div class="mb-4">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">UUID</p>'
                        + '<p class="text-xs font-mono break-all text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-white/5 rounded-lg p-2">' + dianEscapeHtml(doc.UUID) + '</p>'
                    + '</div>';

                    if (eventos.length > 0) {
                        html += '<div class="mb-4">'
                            + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Events') }}</p>'
                            + eventos.map(dianEvento).join('')
                        + '</div>';
                    }

                    const validacionesHtml = dianValidaciones(doc.ValidacionesDoc);
                    if (validacionesHtml) {
                        html += '<div>'
                            + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Validations') }}</p>'
                            + validacionesHtml
                        + '</div>';
                    }

                    return html;
                }

                function renderDianInfo(info) {
                    const documents = dianAsArray(info && info.documents);

                    if (documents.length === 0) {
                        return '<p class="text-zinc-500 dark:text-zinc-400">{{ __('The DIAN did not return information for this document.') }}</p>';
                    }

                    return documents.map(renderDianDocument).join('<hr class="my-4 border-zinc-200 dark:border-white/10">');
                }

                /**
                 * Consulta un UUID/CUFE contra la DIAN y muestra el
                 * resultado en el modal. El usuario puede llegar aquí solo
                 * con el CUFE (sin saber el numeral) -- si la DIAN lo
                 * encuentra, se autocompletan el numeral y la fecha de
                 * emisión con lo que trajo la consulta.
                 * @returns {void}
                 */
                function validateUuid() {
                    const uuid = document.getElementById('doc-referencia_factura_uuid').value.trim();
                    const modalBody = document.getElementById('validate-uuid-modal-body');

                    if (! uuid) {
                        return;
                    }

                    modalBody.innerHTML = '<p class="text-zinc-500 dark:text-zinc-400">{{ __('Querying the DIAN...') }}</p>';

                    if (window.HSOverlay) {
                        HSOverlay.open('#validate-uuid-modal');
                    }

                    fetch(validateUuidUrl + '?uuid=' + encodeURIComponent(uuid), {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (! data.success) {
                                modalBody.innerHTML = '<p class="text-red-600 dark:text-red-400">' + data.message + '</p>';
                                return;
                            }

                            modalBody.innerHTML = renderDianInfo(data.info);

                            const foundDoc = dianAsArray(data.info && data.info.documents)[0];
                            if (foundDoc) {
                                const numeroDocumento = foundDoc.NumeroDocumento || {};
                                const fullNumeral = dianFullNumeral(numeroDocumento);
                                if (fullNumeral) {
                                    document.getElementById('doc-referencia_factura_id').value = fullNumeral;
                                }
                                if (numeroDocumento.FechaEmision) {
                                    const fechaWrapper = document.querySelector('#doc-factura-manual-fields [data-datepicker]');
                                    if (fechaWrapper && fechaWrapper.datepickerSetValue) {
                                        fechaWrapper.datepickerSetValue(numeroDocumento.FechaEmision);
                                    }
                                }
                            }

                            const switchBtn = document.getElementById('doc-use-without-reference-btn');
                            if (switchBtn) {
                                switchBtn.addEventListener('click', () => {
                                    const tipoDocumento = document.getElementById('doc-tipo_documento').value;
                                    const withoutReferenceCode = tipoDocumento === '92' ? '32' : '22';
                                    setSelectValue(document.getElementById('doc-tipo_operacion'), withoutReferenceCode);
                                    updateReferenceVisibility();
                                    if (window.HSOverlay) {
                                        HSOverlay.close('#validate-uuid-modal');
                                    }
                                });
                            }
                        })
                        .catch(() => {
                            modalBody.innerHTML = '<p class="text-red-600 dark:text-red-400">{{ __('Could not connect to the DIAN service.') }}</p>';
                        });
                }

                function applyClientToFields(client, departmentSelect) {
                    setSelectValue(document.getElementById('doc-cliente_tipo_identificacion'), client.identification_type);
                    document.getElementById('doc-cliente_identificacion').value = client.identificacion || '';
                    document.getElementById('doc-cliente_nombre').value = client.name || '';
                    setSelectValue(document.getElementById('doc-cliente_tipo_persona'), client.person_type || '2');

                    const responsibilitiesCodes = (client.fiscal_responsibilities || '').split(';').filter(Boolean);
                    const responsibilitiesSelect = document.getElementById('doc-cliente_responsabilidades');
                    const responsibilitiesInstance = window.HSSelect && HSSelect.getInstance(responsibilitiesSelect);
                    if (responsibilitiesInstance) {
                        responsibilitiesInstance.setValue(responsibilitiesCodes);
                    }

                    document.getElementById('doc-cliente_direccion').value = client.address || '';
                    setSelectValue(departmentSelect, client.department_code);
                    rebuildCitySelect(document.getElementById('doc-cliente_ciudad_codigo'), client.department_code || '', client.city_code || '');
                    document.getElementById('doc-cliente_telefono').value = client.phone || '';
                    document.getElementById('doc-cliente_email').value = client.email || '';

                    const lookupRoot = document.querySelector('[data-dian-lookup]');
                    if (lookupRoot && lookupRoot.dianLookupTrigger) {
                        lookupRoot.dianLookupTrigger();
                    }
                }

                /**
                 * Engancha la búsqueda incremental de cliente y el modal
                 * "buscar en todos los clientes" (botón de lupa dentro del
                 * campo, tabla con su propio buscador, sin tocar la
                 * búsqueda incremental de arriba).
                 * @param {HTMLSelectElement} departmentSelect
                 * @returns {void}
                 */
                function initClientSearch(departmentSelect) {
                    const searchInput = document.getElementById('doc-cliente-search');
                    const resultsEl = document.getElementById('doc-cliente-search-results');
                    const statusEl = document.getElementById('doc-cliente-search-status');
                    let searchTimeout = null;

                    function closeResults() {
                        resultsEl.classList.add('hidden');
                        resultsEl.innerHTML = '';
                    }

                    function renderResults(clients) {
                        if (clients.length === 0) {
                            resultsEl.classList.add('hidden');
                            statusEl.innerHTML = '<span class="text-amber-700 dark:text-amber-400">'
                                + '{{ __('No client found with that name. Fill in the fields below to create a new one.') }}'
                                + '</span>';
                            return;
                        }

                        statusEl.innerHTML = '';
                        resultsEl.innerHTML = '';
                        clients.forEach((client) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'block w-full text-start px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10';
                            item.innerHTML = '<span class="font-medium text-zinc-800 dark:text-white">' + dianEscapeHtml(client.name) + '</span>'
                                + '<span class="block text-xs text-zinc-500 dark:text-zinc-400">' + dianEscapeHtml(client.identificacion) + '</span>';
                            item.addEventListener('click', () => {
                                applyClientToFields(client, departmentSelect);
                                searchInput.value = client.name;
                                statusEl.innerHTML = '';
                                closeResults();
                            });
                            resultsEl.appendChild(item);
                        });
                        resultsEl.classList.remove('hidden');
                    }

                    searchInput.addEventListener('input', (event) => {
                        const query = event.target.value.trim();
                        clearTimeout(searchTimeout);

                        if (! query) {
                            statusEl.innerHTML = '';
                            closeResults();
                            return;
                        }

                        if (query.length < 3) {
                            statusEl.innerHTML = '<span class="text-zinc-500 dark:text-zinc-400">{{ __('Type at least 3 characters to search.') }}</span>';
                            closeResults();
                            return;
                        }

                        searchTimeout = setTimeout(() => {
                            fetch(clientSearchUrl + '?q=' + encodeURIComponent(query), {
                                headers: { 'Accept': 'application/json' },
                            })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (searchInput.value.trim() !== query) {
                                        return;
                                    }
                                    renderResults(data.clients || []);
                                });
                        }, 400);
                    });

                    document.addEventListener('click', (event) => {
                        if (! searchInput.contains(event.target) && ! resultsEl.contains(event.target)) {
                            closeResults();
                        }
                    });

                    const openAllClientsBtn = document.getElementById('doc-cliente-search-open-modal');
                    if (openAllClientsBtn) {
                        openAllClientsBtn.addEventListener('click', () => {
                            if (window.HSOverlay) {
                                HSOverlay.autoInit();
                                HSOverlay.open('#all-clients-modal');
                            }
                        });
                    }

                    if (window.$ && $.fn.DataTable && document.getElementById('all-clients-table') && ! $.fn.DataTable.isDataTable('#all-clients-table')) {
                        initWorkflowDataTable('#all-clients-table', '#all-clients-search', { pageLength: 10 });
                    }

                    window.selectClientFromModal = function (clientId) {
                        const client = allClientsById[clientId];
                        if (! client) {
                            return;
                        }

                        applyClientToFields(client, departmentSelect);
                        searchInput.value = client.name;
                        statusEl.innerHTML = '';
                        closeResults();

                        if (window.HSOverlay) {
                            HSOverlay.close('#all-clients-modal');
                        }
                    };
                }

                /**
                 * Inicializa los componentes Preline (select, input number,
                 * dropdown) de una fila de línea recién insertada. El
                 * auto-cierre de Preline se desactiva en cada dropdown
                 * ([--auto-close:false]) porque cerraba el panel con
                 * CUALQUIER clic adentro (hasta escribiendo en los campos);
                 * se cierra a mano solo con clics realmente afuera.
                 * @param {HTMLElement} row
                 * @returns {void}
                 */
                function initLineSelects(row) {
                    if (window.HSSelect) {
                        row.querySelectorAll('[data-hs-select]').forEach((el) => new HSSelect(el));
                    }
                    if (window.HSInputNumber) {
                        row.querySelectorAll('[data-hs-input-number]').forEach((el) => new HSInputNumber(el));
                    }
                    if (window.HSDropdown) {
                        row.querySelectorAll('.hs-dropdown').forEach((el) => {
                            new HSDropdown(el);

                            document.addEventListener('click', (event) => {
                                if (! el.classList.contains('open')) {
                                    return;
                                }
                                if (! el.contains(event.target)) {
                                    HSDropdown.close(el);
                                }
                            });
                        });
                    }
                }

                const taxNames = { '01': 'IVA', '03': 'ICA', '04': 'INC' };

                /**
                 * Reposiciona el botón "Agregar impuesto" de una línea según
                 * si ya tiene impuestos. Con el primer impuesto agregado, el
                 * botón grande pasa a ser un "+" chiquito al final de la
                 * lista de badges (misma columna, para no ocupar tanto
                 * espacio) -- siempre enseguida del último impuesto, en su
                 * misma fila. Sin impuestos, vuelve a su lugar original,
                 * debajo de la lista vacía de badges.
                 * @param {HTMLElement} row
                 * @returns {void}
                 */
                function updateAddTaxButtonState(row) {
                    const badgesBody = row.querySelector('.line-taxes-body');
                    const rows = badgesBody.querySelectorAll(':scope > .line-tax-row');
                    const dropdown = row.querySelector('.hs-dropdown');
                    const btn = row.querySelector('.line-tax-add-btn');
                    const label = btn.querySelector('.line-tax-add-label');

                    if (rows.length > 0) {
                        label.classList.add('hidden');
                        btn.classList.remove('ps-3', 'pe-4');
                        btn.classList.add('w-10', 'px-0');
                        rows[rows.length - 1].appendChild(dropdown);
                    } else {
                        label.classList.remove('hidden');
                        btn.classList.remove('w-10', 'px-0');
                        btn.classList.add('ps-3', 'pe-4');
                        badgesBody.after(dropdown);
                    }
                }

                function formatMoney(value) {
                    return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                /**
                 * Recalcula el cambio del POS cada vez que cambia el total
                 * de la venta o lo que escribió el cajero en "efectivo
                 * recibido". Los campos solo existen en modo POS (ver
                 * "$posMode" en el blade); si no están, no hace nada.
                 * @returns {void}
                 */
                function updatePosChange() {
                    const hidden = document.getElementById('pos-efectivo-hidden');
                    const changeDisplay = document.getElementById('pos-change-display');
                    if (! hidden || ! changeDisplay) {
                        return;
                    }

                    const total = parseFloat(document.getElementById('documentLinesTotal').dataset.raw) || 0;
                    const recibido = parseFloat(hidden.value) || 0;
                    changeDisplay.textContent = formatMoney(Math.max(recibido - total, 0));
                }

                /**
                 * Engancha el checkout del POS: la venta se manda por fetch
                 * (no un submit normal de formulario), así la página se
                 * queda quieta y, en vez de navegar a otra pantalla, se abre
                 * el modal de resultado con la vista previa del recibo y las
                 * acciones (emitir electrónica, imprimir, descargar, nueva
                 * venta). Se crea SIEMPRE como factura de venta primero --
                 * "Emitir factura electrónica" en el modal es una acción
                 * aparte, después de que la venta ya existe (ver
                 * PosController::issueElectronic()).
                 * @returns {void}
                 */
                function initPosAjaxCheckout() {
                    const form = document.getElementById('documentForm');
                    const modal = document.getElementById('pos-result-modal');
                    if (! form || ! modal) {
                        return;
                    }

                    const submitBtn = document.getElementById('documentSubmitBtn');
                    const messageBox = document.getElementById('pos-result-message');
                    const numeralLabel = document.getElementById('pos-result-numeral');
                    const preview = document.getElementById('pos-result-preview');
                    const issueElectronicBtn = document.getElementById('pos-result-issue-electronic-btn');

                    let currentSale = null;

                    function showFormError(message) {
                        const box = document.getElementById('pos-checkout-error');
                        box.textContent = message;
                        box.classList.remove('hidden');
                        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const formData = new FormData(form);

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' },
                                body: formData,
                            });
                            const data = await response.json();

                            if (! response.ok) {
                                throw new Error(data.message || '{{ __('Could not issue the document.') }}');
                            }

                            currentSale = data;
                            numeralLabel.textContent = data.numeral;
                            messageBox.classList.add('hidden');
                            preview.src = data.receipt_preview_url;
                            issueElectronicBtn.classList.toggle('hidden', ! data.can_issue_electronic);
                            issueElectronicBtn.disabled = false;
                            issueElectronicBtn.textContent = '{{ __('Issue electronic invoice') }}';

                            if (window.HSOverlay) {
                                HSOverlay.autoInit();
                                HSOverlay.open('#pos-result-modal');
                            }
                        } catch (error) {
                            showFormError(error.message || '{{ __('Could not issue the document.') }}');
                            submitBtn.disabled = false;
                            submitBtn.textContent = '{{ __('Issue document') }}';
                        }
                    });

                    window.posIssueElectronic = async function () {
                        if (! currentSale) {
                            return;
                        }

                        issueElectronicBtn.disabled = true;
                        issueElectronicBtn.textContent = '{{ __('Processing...') }}';

                        try {
                            const response = await fetch(currentSale.issue_electronic_url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                            });
                            const data = await response.json();

                            if (! response.ok) {
                                throw new Error(data.message || '{{ __('Could not issue the electronic invoice.') }}');
                            }

                            messageBox.className = 'mb-3 rounded-md p-3 text-sm ' + (data.accepted
                                ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                : 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400');
                            messageBox.textContent = data.accepted
                                ? '{{ __('Sale completed and accepted by the DIAN.') }}'
                                : '{{ __('The sale was sent, but the DIAN did not accept the electronic invoice yet.') }}';
                            issueElectronicBtn.classList.add('hidden');
                            preview.src = currentSale.receipt_preview_url;
                        } catch (error) {
                            messageBox.className = 'mb-3 rounded-md p-3 text-sm bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                            messageBox.textContent = error.message || '{{ __('Could not issue the electronic invoice.') }}';
                            issueElectronicBtn.disabled = false;
                            issueElectronicBtn.textContent = '{{ __('Issue electronic invoice') }}';
                        }

                        messageBox.classList.remove('hidden');
                    };

                    window.posDownloadReceipt = function () {
                        if (currentSale) {
                            window.location.href = currentSale.receipt_url;
                        }
                    };

                    window.posPrintReceipt = function () {
                        if (currentSale) {
                            window.open(currentSale.receipt_preview_url, '_blank');
                        }
                    };

                    window.posNewSale = function () {
                        window.location.href = '{{ route('pos.create') }}';
                    };
                }

                /**
                 * Deshabilita el botón de envío desde el primer submit, para
                 * evitar ventas duplicadas si alguien le da clic varias
                 * veces a "Emitir documento" (por ejemplo porque no ve
                 * ningún cambio mientras la petición está en camino).
                 * Cualquier submit del formulario termina en un envío real
                 * tarde o temprano (el modal de "¿Emitir factura
                 * electrónica?" del POS no tiene forma de cancelar, solo
                 * Sí/No, ambos mandan el formulario), así que es seguro
                 * deshabilitarlo ya desde el primer submit.
                 * @returns {void}
                 */
                function initSubmitProcessingState() {
                    const form = document.getElementById('documentForm');
                    const button = document.getElementById('documentSubmitBtn');
                    if (! form || ! button) {
                        return;
                    }

                    form.addEventListener('submit', () => {
                        if (button.disabled) {
                            return;
                        }

                        button.disabled = true;
                        button.textContent = '{{ __('Processing...') }}';
                    });
                }

                /**
                 * Quita del formulario, justo antes de enviarlo, la línea de
                 * producto vacía que siempre queda al final (esperando el
                 * siguiente producto que se busque -- se agrega sola al
                 * elegir un producto, ver applyProduct()). Si el usuario
                 * emite el documento sin llenarla, no debe mandarse al
                 * servidor como si fuera un ítem real (descripción vacía,
                 * cantidad 0); se quita solo al enviar, nunca antes, porque
                 * mientras se sigue editando sigue siendo el lugar donde
                 * buscar el próximo producto.
                 * @returns {void}
                 */
                function initEmptyLineSubmitCleanup() {
                    const form = document.getElementById('documentForm');
                    if (! form) {
                        return;
                    }

                    form.addEventListener('submit', () => {
                        document.querySelectorAll('#documentLinesBody .document-line').forEach((row) => {
                            if (row.dataset.productPicked !== 'true') {
                                row.remove();
                            }
                        });
                    });
                }

                /**
                 * Formatea un valor decimal al formato visible colombiano
                 * (punto de miles, coma de centavos), igual que en el módulo
                 * de productos; el campo oculto (rawLinePriceValue) guarda
                 * el valor con punto decimal estándar, que es lo que espera
                 * el backend.
                 * @param {string} intPart
                 * @param {string} decPart
                 * @param {boolean} hasComma
                 * @returns {string}
                 */
                function formatLinePrice(intPart, decPart, hasComma) {
                    if (! intPart && ! hasComma) {
                        return '';
                    }
                    const formattedInt = Number(intPart || '0').toLocaleString('es-CO');
                    return hasComma ? `${formattedInt},${decPart}` : formattedInt;
                }

                function rawLinePriceValue(intPart, decPart) {
                    if (! intPart && ! decPart) {
                        return '0';
                    }
                    return decPart ? `${intPart || '0'}.${decPart}` : (intPart || '0');
                }

                /**
                 * Precarga el precio visible/oculto de una línea a partir de
                 * un valor crudo con punto decimal. "row" es el contenedor
                 * donde buscar los campos por defecto
                 * (.line-precio/.line-precio-display); se pueden pasar los
                 * elementos directamente (hiddenEl/displayEl) para reusar
                 * este mismo formato en otros campos (descuento, impuestos).
                 * @param {HTMLElement} row
                 * @param {string|number} rawValue
                 * @param {HTMLElement} [hiddenEl]
                 * @param {HTMLElement} [displayEl]
                 * @returns {void}
                 */
                function setLinePriceValue(row, rawValue, hiddenEl = null, displayEl = null) {
                    const hidden = hiddenEl || row.querySelector('.line-precio');
                    const display = displayEl || row.querySelector('.line-precio-display');
                    const str = String(rawValue ?? '');
                    if (! str) {
                        hidden.value = '0';
                        display.value = '';
                        return;
                    }
                    const [intRaw, decRaw] = str.split('.');
                    const intPart = (intRaw ?? '').replace(/\D/g, '');
                    const decPart = (decRaw ?? '').replace(/\D/g, '').slice(0, 2);
                    hidden.value = rawLinePriceValue(intPart, decPart);
                    display.value = formatLinePrice(intPart, decPart, decRaw !== undefined);
                }

                function handleLinePriceInput(row, typedValue, hiddenEl = null, displayEl = null) {
                    let str = String(typedValue ?? '').replace(/\./g, '');
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
                    const hidden = hiddenEl || row.querySelector('.line-precio');
                    const display = displayEl || row.querySelector('.line-precio-display');
                    hidden.value = rawLinePriceValue(intPart, decPart);
                    display.value = formatLinePrice(intPart, decPart, hasComma);
                }

                /**
                 * Actualiza el símbolo ("%" o "$") que antecede al campo de
                 * descuento de una línea, según el tipo elegido (porcentaje
                 * o valor fijo) -- reusa el mismo formato/estructura que el
                 * precio unitario. El tope máximo (100 para porcentaje, el
                 * subtotal de la línea para valor fijo, ver discountCap())
                 * se aplica al calcular el subtotal, no mientras se escribe,
                 * para no pelearle al cursor del usuario.
                 * @param {HTMLElement} row
                 * @returns {void}
                 */
                function updateDiscountPrefix(row) {
                    const tipo = row.querySelector('.line-descuento-tipo').value || 'porcentaje';
                    row.querySelector('.line-descuento-prefix').textContent = tipo === 'porcentaje' ? '%' : '$';
                }

                function discountCap(row) {
                    const tipo = row.querySelector('.line-descuento-tipo').value || 'porcentaje';
                    if (tipo === 'porcentaje') {
                        return 100;
                    }
                    const cantidad = parseFloat(row.querySelector('.line-cantidad').value) || 0;
                    const precio = parseFloat(row.querySelector('.line-precio').value) || 0;
                    return cantidad * precio;
                }

                /**
                 * Reformatea un campo mientras se escribe (mismo formato
                 * colombiano que el precio) y lo recorta a "cap" si se pasa
                 * -- así el campo nunca queda mostrando un valor que después
                 * se iba a ignorar en el cálculo. hiddenEl guarda el valor
                 * real (punto decimal), displayEl es lo que ve el usuario.
                 * @param {string} typedValue
                 * @param {HTMLElement} hiddenEl
                 * @param {HTMLElement} displayEl
                 * @param {number} [cap]
                 * @returns {void}
                 */
                function handleCappedPriceInput(typedValue, hiddenEl, displayEl, cap) {
                    let str = String(typedValue ?? '').replace(/\./g, '');
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

                    let raw = parseFloat(rawLinePriceValue(intPart, decPart)) || 0;
                    if (cap !== null && raw > cap) {
                        raw = cap;
                        const [cappedInt, cappedDec] = String(raw).split('.');
                        intPart = cappedInt || '0';
                        decPart = (cappedDec || '').slice(0, 2);
                        hasComma = Boolean(cappedDec);
                    }

                    hiddenEl.value = rawLinePriceValue(intPart, decPart);
                    displayEl.value = formatLinePrice(intPart, decPart, hasComma);
                }

                function handleLineDiscountInput(row, typedValue) {
                    handleCappedPriceInput(
                        typedValue,
                        row.querySelector('.line-descuento-valor'),
                        row.querySelector('.line-descuento-display'),
                        discountCap(row),
                    );
                }

                /**
                 * Fija el tope de cantidad de una línea al stock disponible
                 * en la bodega elegida (la mínima siempre es 0), sin
                 * importar si el producto tiene marcado "controla
                 * inventario" -- lo que importa es el stock real de esa
                 * bodega puntual, que siempre viene informado. Sin
                 * producto/bodega elegida todavía, no hay tope. Se
                 * reconstruye el componente +/- con ese nuevo tope cada vez
                 * que cambia el producto o la bodega; el valor se recorta
                 * ANTES de reconstruirlo (si se hiciera después, el contador
                 * +/- quedaría pensando que el valor sigue siendo el viejo,
                 * de ahí que "+"/"-" no respondieran bien con varias
                 * líneas). Todo el campo de cantidad (escribir a mano, "+" y
                 * "-") queda bloqueado hasta que haya un producto elegido --
                 * sin producto no hay bodega ni stock de referencia. La
                 * librería reactiva los botones ella sola cada vez que
                 * cambia el valor (al escribir, al hacer clic, o incluso al
                 * construir la instancia), sin importar el atributo
                 * "disabled" que se les puso a mano -- por eso hace falta
                 * reforzarlo también después de cada cambio, no solo una vez
                 * al armar el componente.
                 * @param {HTMLElement} row
                 * @returns {void}
                 */
                function updateCantidadLimits(row) {
                    const wrapper = row.querySelector('.line-cantidad-wrapper');
                    const cantidadInput = row.querySelector('.line-cantidad');
                    const bodegaSelect = row.querySelector('.line-bodega');

                    let max = null;
                    if (bodegaSelect.options.length > 0) {
                        const selectedOption = bodegaSelect.selectedOptions[0];
                        const stock = selectedOption ? parseFloat(selectedOption.dataset.stock) : NaN;
                        max = isNaN(stock) ? null : stock;
                    }

                    const config = { step: 1, min: 0 };
                    if (max !== null) {
                        config.max = max;
                    }
                    wrapper.setAttribute('data-hs-input-number', JSON.stringify(config));

                    if (max !== null && (parseFloat(cantidadInput.value) || 0) > max) {
                        cantidadInput.value = max;
                    }

                    if (window.HSInputNumber) {
                        HSInputNumber.getInstance(wrapper, true)?.element?.destroy();
                        new HSInputNumber(wrapper);
                    }

                    const hasProduct = bodegaSelect.options.length > 0;
                    const incrementBtn = wrapper.querySelector('[data-hs-input-number-increment]');
                    const decrementBtn = wrapper.querySelector('[data-hs-input-number-decrement]');
                    const applyDisabledState = () => {
                        const stillHasProduct = bodegaSelect.options.length > 0;
                        cantidadInput.disabled = ! stillHasProduct;
                        if (incrementBtn) {
                            incrementBtn.disabled = ! stillHasProduct;
                        }
                        if (decrementBtn && ! stillHasProduct) {
                            decrementBtn.disabled = true;
                        }
                    };
                    applyDisabledState();

                    if (! wrapper.dataset.stepperGuardBound) {
                        wrapper.dataset.stepperGuardBound = 'true';
                        wrapper.addEventListener('change.hs.inputNumber', applyDisabledState);
                    }
                }

                /**
                 * Calcula el subtotal de una línea: cantidad x precio, menos
                 * el descuento de línea (si hay, topado a 100 si es
                 * porcentaje o al subtotal si es valor fijo), más la suma de
                 * sus impuestos (cada uno sobre su propia base gravable, que
                 * nunca puede superar ese subtotal ya con el descuento
                 * aplicado).
                 * @param {HTMLElement} row
                 * @returns {number}
                 */
                function computeLineSubtotal(row) {
                    const cantidad = parseFloat(row.querySelector('.line-cantidad').value) || 0;
                    const precio = parseFloat(row.querySelector('.line-precio').value) || 0;
                    const baseAmount = cantidad * precio;

                    const descuentoTipo = row.querySelector('.line-descuento-tipo')?.value || 'porcentaje';
                    const descuentoValor = parseFloat(row.querySelector('.line-descuento-valor')?.value) || 0;
                    const descuentoAmount = descuentoTipo === 'porcentaje'
                        ? baseAmount * (Math.min(descuentoValor, 100) / 100)
                        : Math.min(descuentoValor, baseAmount);

                    const lineExtension = Math.max(baseAmount - descuentoAmount, 0);

                    let taxTotal = 0;
                    row.querySelectorAll('.line-tax').forEach((taxRow) => {
                        const porcentaje = parseFloat(taxRow.querySelector('.line-tax-porcentaje').value) || 0;
                        let base = parseFloat(taxRow.querySelector('.line-tax-base').value);
                        if (isNaN(base) || base <= 0) {
                            base = lineExtension;
                        }
                        base = Math.min(base, lineExtension);
                        taxTotal += base * (porcentaje / 100);
                    });

                    return lineExtension + taxTotal;
                }

                function recalcLine(row) {
                    row.querySelector('.line-subtotal').textContent = formatMoney(computeLineSubtotal(row));
                    recalcTotal();
                }

                /**
                 * Recalcula el total del documento sumando el subtotal de
                 * cada línea. totalEl.dataset.raw guarda el valor crudo
                 * para el cálculo de cambio del POS (ver updatePosChange())
                 * -- no incluye cargos/descuentos de documento, solo la
                 * suma de líneas, que es el caso normal de una venta rápida.
                 * @returns {void}
                 */
                function recalcTotal() {
                    let total = 0;
                    document.querySelectorAll('#documentLinesBody .document-line').forEach((row) => {
                        total += computeLineSubtotal(row);
                    });
                    const totalEl = document.getElementById('documentLinesTotal');
                    totalEl.textContent = formatMoney(total);
                    totalEl.dataset.raw = total;
                    updatePosChange?.();
                }

                /**
                 * Engancha el modal "buscar en todos los productos",
                 * compartido entre todas las líneas (no hay un solo
                 * <select> con todo el catálogo -- puede haber miles de
                 * productos -- así que se busca por AJAX igual que el
                 * campo incremental de cada línea, solo que en formato
                 * tabla dentro de un modal). Expone window.openAllProductsModal(row).
                 * @returns {void}
                 */
                function initAllProductsModal() {
                    const searchInput = document.getElementById('all-products-search');
                    const tbody = document.getElementById('all-products-tbody');
                    const emptyMsg = document.getElementById('all-products-empty');
                    let activeRow = null;
                    let searchTimeout = null;

                    function renderRows(products) {
                        tbody.innerHTML = '';
                        emptyMsg.classList.toggle('hidden', products.length > 0);

                        products.forEach((product) => {
                            const stockLabel = product.tracks_inventory
                                ? Number(product.stock ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 2 })
                                : '<span class="text-xs text-neutral-400">{{ __('Not tracked') }}</span>';

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${dianEscapeHtml(product.barcode) || '—'}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${dianEscapeHtml(product.code) || '—'}</td>
                                <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-neutral-200">${dianEscapeHtml(product.description)}</td>
                                <td class="px-3 py-2 text-sm text-end text-gray-600 dark:text-neutral-400">${stockLabel}</td>
                                <td class="px-3 py-2 text-end">
                                    <button type="button" class="text-xs font-medium text-accent hover:underline">{{ __('Select this product') }}</button>
                                </td>
                            `;
                            tr.querySelector('button').addEventListener('click', () => {
                                if (activeRow && activeRow.applyProduct) {
                                    activeRow.applyProduct(product);
                                }
                                if (window.HSOverlay) {
                                    HSOverlay.close('#all-products-modal');
                                }
                            });
                            tbody.appendChild(tr);
                        });
                    }

                    function fetchProducts(query) {
                        fetch(productSearchUrl + '?q=' + encodeURIComponent(query), {
                            headers: { 'Accept': 'application/json' },
                        })
                            .then((response) => response.json())
                            .then((data) => renderRows(data.products || []))
                            .catch(() => renderRows([]));
                    }

                    searchInput.addEventListener('input', () => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => fetchProducts(searchInput.value.trim()), 300);
                    });

                    window.openAllProductsModal = function (row) {
                        activeRow = row;
                        searchInput.value = '';
                        fetchProducts('');

                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#all-products-modal');
                        }
                        searchInput.focus();
                    };
                }

                /**
                 * Engancha la búsqueda de producto y todos los campos
                 * derivados (precio, descuento, bodega) de una línea. El
                 * selector de bodega es un HSSelect (UI custom de Preline):
                 * al elegir una opción NO dispara el evento nativo "change"
                 * del <select>, dispara uno propio "change.hs.select", por
                 * eso se escuchan ambos. El botón de lupa dentro del campo
                 * de producto abre el modal "buscar en todos los productos"
                 * (compartido entre todas las líneas, ver
                 * window.openAllProductsModal más abajo), sin tocar la
                 * búsqueda incremental.
                 * @param {HTMLElement} row
                 * @returns {void}
                 */
                function initLineProductSearch(row) {
                    const searchInput = row.querySelector('.line-product-search');
                    const resultsEl = row.querySelector('.line-product-results');
                    const codigoInput = row.querySelector('.line-codigo');
                    const barcodeInput = row.querySelector('.line-barcode');
                    const descripcionInput = row.querySelector('.line-descripcion');
                    const unidadInput = row.querySelector('.line-unidad');
                    const precioDisplay = row.querySelector('.line-precio-display');
                    const precioSelect = row.querySelector('.line-precio-select');
                    const precioSelectWrapper = row.querySelector('.line-precio-select-wrapper');
                    const bodegaSelect = row.querySelector('.line-bodega');
                    let searchTimeout = null;

                    function closeResults() {
                        resultsEl.classList.add('hidden');
                        resultsEl.innerHTML = '';
                    }

                    /**
                     * Llena el selector de lista de precios embebido en el
                     * propio campo de precio: mismo diseño de select que el
                     * resto de la app, pero el botón nunca muestra el
                     * precio elegido (solo una flechita) -- el valor ya se
                     * refleja en el campo de precio de al lado.
                     * @param {Array} prices
                     * @returns {void}
                     */
                    function fillPriceSelect(prices) {
                        if (! prices.length) {
                            precioSelectWrapper.classList.add('hidden');
                            rebuildSelect(precioSelect, []);
                            return;
                        }

                        rebuildSelect(precioSelect, prices.map((price) => ({
                            value: String(price.price),
                            label: price.price_type_name + ': ' + formatMoney(price.price),
                        })));
                        precioSelectWrapper.classList.remove('hidden');
                    }

                    /**
                     * Aplica un producto elegido a esta línea: precios,
                     * bodegas y desbloqueo del botón de borrar. Los precios
                     * del producto se guardan en la propia fila
                     * (row.productPrices, no solo en el <select>) para poder
                     * aplicar "este tipo de precio a todas las líneas" desde
                     * el selector global sin volver a pedirlos. La bodega
                     * siempre se ve, pero arranca deshabilitada hasta que se
                     * encuentra un producto -- si no tiene bodegas propias,
                     * igual trae "Sin asignar" con lo que haya de cantidad
                     * disponible; cada opción guarda su stock (data-stock)
                     * para topar la cantidad máxima según la bodega elegida.
                     * Sin producto, la línea no se puede borrar: es la que
                     * queda disponible para seguir buscando (ya no hay botón
                     * "Agregar línea" aparte). Por la misma razón, en cuanto
                     * se elige un producto en la ÚLTIMA fila, se agrega una
                     * fila vacía nueva debajo automáticamente (si la fila ya
                     * no era la última, quiere decir que se está cambiando
                     * el producto de una línea existente, y ya hay una fila
                     * vacía más adelante, no hace falta agregar otra).
                     * Expuesta como row.applyProduct para que el modal
                     * "buscar en todos los productos" (compartido entre
                     * todas las líneas) pueda aplicar el producto elegido a
                     * ESTA fila en concreto.
                     * @param {object} product
                     * @returns {void}
                     */
                    function applyProduct(product) {
                        row.dataset.productPicked = 'true';
                        row.dataset.tracksInventory = product.tracks_inventory ? 'true' : 'false';
                        codigoInput.value = product.code || '';
                        barcodeInput.value = product.barcode || '';
                        descripcionInput.value = product.description || '';
                        unidadInput.value = product.unit_code || 'EA';
                        searchInput.value = (product.code || '') + ' - ' + (product.description || '');

                        row.productPrices = product.prices || [];

                        fillPriceSelect(product.prices || []);
                        setLinePriceValue(row, (product.prices && product.prices.length) ? product.prices[0].price : (product.unit_price || 0));

                        rebuildSelect(bodegaSelect, (product.warehouses || []).map((warehouse) => ({
                            value: warehouse.warehouse_id || '',
                            label: warehouse.warehouse_name + ' (' + warehouse.stock + ')',
                            dataset: { stock: warehouse.stock },
                        })));
                        bodegaSelect.disabled = false;

                        const deleteBtn = row.querySelector('.line-delete-btn');
                        if (deleteBtn) {
                            deleteBtn.disabled = false;
                        }

                        updateCantidadLimits(row);
                        recalcLine(row);

                        const allRows = document.querySelectorAll('#documentLinesBody .document-line');
                        if (row === allRows[allRows.length - 1]) {
                            const newRow = addDocumentLine();
                            newRow.querySelector('.line-product-search')?.focus();
                        }
                    }

                    row.applyProduct = applyProduct;

                    function renderResults(products) {
                        if (! products.length) {
                            closeResults();
                            return;
                        }

                        resultsEl.innerHTML = '';
                        products.forEach((product) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'block w-full text-start px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10';
                            item.innerHTML = '<span class="font-medium text-zinc-800 dark:text-white">' + dianEscapeHtml(product.code) + ' - ' + dianEscapeHtml(product.description) + '</span>'
                                + (product.barcode ? '<span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Barcode') }}: ' + dianEscapeHtml(product.barcode) + '</span>' : '');
                            item.addEventListener('click', () => {
                                applyProduct(product);
                                closeResults();
                            });
                            resultsEl.appendChild(item);
                        });
                        resultsEl.classList.remove('hidden');
                    }

                    searchInput.addEventListener('input', () => {
                        const query = searchInput.value.trim();
                        clearTimeout(searchTimeout);
                        row.dataset.productPicked = 'false';

                        if (query.length < 2) {
                            closeResults();
                            return;
                        }

                        searchTimeout = setTimeout(() => {
                            fetch(productSearchUrl + '?q=' + encodeURIComponent(query), {
                                headers: { 'Accept': 'application/json' },
                            })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (searchInput.value.trim() !== query) {
                                        return;
                                    }
                                    renderResults(data.products || []);
                                })
                                .catch(() => closeResults());
                        }, 300);
                    });

                    document.addEventListener('click', (event) => {
                        if (! row.contains(event.target)) {
                            closeResults();
                        }
                    });

                    descripcionInput.addEventListener('input', () => {
                        if (row.dataset.productPicked !== 'true') {
                            codigoInput.value = descripcionInput.value;
                        }
                    });

                    precioSelect.addEventListener('change', () => {
                        if (precioSelect.value !== '') {
                            setLinePriceValue(row, precioSelect.value);
                            recalcLine(row);
                        }
                    });

                    bodegaSelect.addEventListener('change', () => {
                        updateCantidadLimits(row);
                        recalcLine(row);
                    });
                    bodegaSelect.addEventListener('change.hs.select', () => {
                        updateCantidadLimits(row);
                        recalcLine(row);
                    });

                    precioDisplay.addEventListener('input', () => {
                        handleLinePriceInput(row, precioDisplay.value);
                        handleLineDiscountInput(row, row.querySelector('.line-descuento-display').value);
                        recalcLine(row);
                    });

                    row.querySelector('.line-product-search-open-modal')?.addEventListener('click', () => {
                        window.openAllProductsModal(row);
                    });
                }

                /**
                 * Precarga la base gravable del formulario de "agregar
                 * impuesto" con cantidad x precio (el subtotal de la línea)
                 * cada vez que se abre, para que quede seleccionada por
                 * defecto sin tener que escribirla a mano.
                 * @param {HTMLElement} button
                 * @returns {void}
                 */
                window.prefillNewTaxBase = function (button) {
                    const row = button.closest('.document-line');
                    const panel = button.closest('.hs-dropdown').querySelector('.hs-dropdown-menu');
                    const cantidad = parseFloat(row.querySelector('.line-cantidad').value) || 0;
                    const precio = parseFloat(row.querySelector('.line-precio').value) || 0;
                    const baseHidden = panel.querySelector('.line-newtax-base');
                    const baseDisplay = panel.querySelector('.line-newtax-base-display');
                    setLinePriceValue(row, (cantidad * precio).toFixed(2), baseHidden, baseDisplay);
                    baseDisplay.select();
                };

                /**
                 * Guarda el impuesto del mini formulario como un badge en la
                 * línea, y limpia el formulario para la próxima vez que se
                 * abra.
                 * @param {HTMLElement} button
                 * @returns {void}
                 */
                window.saveLineTax = function (button) {
                    const row = button.closest('.document-line');
                    const panel = button.closest('.hs-dropdown-menu');
                    const dropdown = button.closest('.hs-dropdown');

                    const tipoSelect = panel.querySelector('.line-newtax-tipo');
                    const porcentajeInput = panel.querySelector('.line-newtax-porcentaje');
                    const baseInput = panel.querySelector('.line-newtax-base');

                    const tipo = tipoSelect.value || '01';
                    const porcentaje = Math.min(parseFloat(porcentajeInput.value) || 0, 100);
                    const baseRaw = baseInput.value.trim();
                    const base = baseRaw !== '' ? Math.max(parseFloat(baseRaw) || 0, 0) : null;

                    if (porcentaje <= 0) {
                        panel.querySelector('.line-newtax-porcentaje-display').focus();
                        return;
                    }

                    const template = document.getElementById('documentLineTaxBadgeTemplate');
                    const taxIndex = parseInt(row.dataset.taxIndex || '0', 10);
                    const html = template.innerHTML.replaceAll('__LINEINDEX__', row.dataset.lineIndex).replaceAll('__TAXINDEX__', taxIndex);
                    const container = row.querySelector('.line-taxes-body');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const badge = wrapper.firstElementChild;
                    container.appendChild(badge);
                    row.dataset.taxIndex = taxIndex + 1;

                    badge.querySelector('.line-tax-tipo').value = tipo;
                    badge.querySelector('.line-tax-porcentaje').value = porcentaje;
                    badge.querySelector('.line-tax-base').value = base !== null ? base : '';

                    const porcentajeLabel = porcentaje.toLocaleString('es-CO', { maximumFractionDigits: 2 });
                    const baseLabel = base !== null ? ' · ' + formatMoney(base) : '';
                    badge.querySelector('.line-tax-label').textContent = (taxNames[tipo] || tipo) + ' ' + porcentajeLabel + '%' + baseLabel;

                    setSelectValue(tipoSelect, '01');
                    setLinePriceValue(row, '19', porcentajeInput, panel.querySelector('.line-newtax-porcentaje-display'));
                    baseInput.value = '';
                    panel.querySelector('.line-newtax-base-display').value = '';

                    if (window.HSDropdown) {
                        HSDropdown.close(dropdown);
                    }

                    updateAddTaxButtonState(row);
                    recalcLine(row);
                };

                /**
                 * Quita un impuesto de una línea. El botón "+" de "agregar
                 * impuesto" puede estar viviendo dentro de esta misma fila
                 * de impuesto (si era la última) -- se saca antes de borrar
                 * la fila para no perderlo.
                 * @param {HTMLElement} button
                 * @returns {void}
                 */
                window.removeLineTax = function (button) {
                    const row = button.closest('.document-line');
                    const taxRow = button.closest('.line-tax-row');
                    const badgesBody = row.querySelector('.line-taxes-body');
                    const dropdown = row.querySelector('.hs-dropdown');

                    if (taxRow.contains(dropdown)) {
                        badgesBody.after(dropdown);
                    }

                    taxRow.remove();
                    updateAddTaxButtonState(row);
                    recalcLine(row);
                };

                /**
                 * Agrega una nueva línea de producto vacía al documento.
                 * updateCantidadLimits(row) sincroniza el componente +/-
                 * desde el arranque (queda igual que el de la plantilla,
                 * pero se reconstruye para que su estado interno no dependa
                 * de lo que haya en el atributo del HTML nada más). El
                 * máximo de descuento/impuesto cambia según el tipo elegido
                 * (100 para porcentaje, el subtotal de la línea para valor
                 * fijo): si el valor ya escrito queda por encima del nuevo
                 * tope, se recorta también. Los listeners de "input"/cantidad se
                 * enganchan por delegación en la propia fila (row), para
                 * cubrir también las filas de impuesto que se agregan
                 * dinámicamente después; los botones +/- del campo de
                 * cantidad no disparan un evento "input" nativo (el plugin
                 * solo pone el valor a mano), así que también se escucha su
                 * propio evento "change.hs.inputNumber".
                 * @returns {HTMLElement} La fila creada.
                 */
                window.addDocumentLine = function () {
                    const template = document.getElementById('documentLineTemplate');
                    const html = template.innerHTML.replaceAll('__INDEX__', lineIndex);
                    const container = document.getElementById('documentLinesBody');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const row = wrapper.firstElementChild;
                    row.dataset.lineIndex = lineIndex;
                    row.dataset.taxIndex = 0;
                    row.dataset.productPicked = 'false';
                    container.appendChild(row);
                    lineIndex++;

                    initLineSelects(row);
                    initLineProductSearch(row);
                    updateCantidadLimits(row);

                    const descuentoDisplay = row.querySelector('.line-descuento-display');
                    const descuentoTipoSelect = row.querySelector('.line-descuento-tipo-select');
                    const descuentoTipoHidden = row.querySelector('.line-descuento-tipo');

                    descuentoDisplay.addEventListener('input', () => {
                        handleLineDiscountInput(row, descuentoDisplay.value);
                        recalcLine(row);
                    });

                    descuentoTipoSelect.addEventListener('change', () => {
                        descuentoTipoHidden.value = descuentoTipoSelect.value;
                        updateDiscountPrefix(row);
                        handleLineDiscountInput(row, row.querySelector('.line-descuento-display').value);
                        recalcLine(row);
                    });

                    const newTaxPorcentajeHidden = row.querySelector('.line-newtax-porcentaje');
                    const newTaxPorcentajeDisplay = row.querySelector('.line-newtax-porcentaje-display');
                    const newTaxBaseHidden = row.querySelector('.line-newtax-base');
                    const newTaxBaseDisplay = row.querySelector('.line-newtax-base-display');

                    newTaxPorcentajeDisplay.addEventListener('input', () => {
                        handleCappedPriceInput(newTaxPorcentajeDisplay.value, newTaxPorcentajeHidden, newTaxPorcentajeDisplay, 100);
                    });

                    newTaxBaseDisplay.addEventListener('input', () => {
                        const cantidad = parseFloat(row.querySelector('.line-cantidad').value) || 0;
                        const precio = parseFloat(row.querySelector('.line-precio').value) || 0;
                        handleCappedPriceInput(newTaxBaseDisplay.value, newTaxBaseHidden, newTaxBaseDisplay, cantidad * precio);
                    });

                    row.addEventListener('input', (event) => {
                        if (event.target.matches('.line-cantidad')) {
                            handleLineDiscountInput(row, row.querySelector('.line-descuento-display').value);
                        }
                        if (event.target.matches('.line-cantidad')) {
                            recalcLine(row);
                        }
                    });

                    row.querySelector('[data-hs-input-number]')?.addEventListener('change.hs.inputNumber', () => {
                        handleLineDiscountInput(row, row.querySelector('.line-descuento-display').value);
                        recalcLine(row);
                    });

                    recalcLine(row);

                    return row;
                };

                window.removeDocumentLine = function (button) {
                    const row = button.closest('.document-line');
                    if (row.dataset.productPicked !== 'true') {
                        return;
                    }
                    if (document.querySelectorAll('#documentLinesBody .document-line').length > 1) {
                        row.remove();
                        recalcTotal();
                    }
                };

                /**
                 * Muestra/oculta el bloque "efectivo recibido", que solo
                 * tiene sentido si el PRIMER medio de pago es efectivo
                 * (código 10); con cualquier otro medio (tarjeta,
                 * transferencia) no hay nada que cobrar en físico. Solo
                 * existe en modo POS.
                 * @returns {void}
                 */
                function updatePosCashSectionVisibility() {
                    const section = document.getElementById('pos-cash-section');
                    if (! section) {
                        return;
                    }

                    const firstCodeSelect = document.querySelector('#paymentLinesBody .payment-line select.payment-code-select');
                    const isCash = ! firstCodeSelect || firstCodeSelect.selectedOptions[0]?.dataset.dianCode === '10';
                    section.classList.toggle('hidden', ! isCash);

                    if (! isCash) {
                        document.getElementById('pos-efectivo-hidden').value = '';
                        document.getElementById('pos-efectivo-display').value = '';
                        updatePosChange();
                    }
                }

                window.addPaymentLine = function () {
                    const template = document.getElementById('paymentLineTemplate');
                    const html = template.innerHTML.replaceAll('__INDEX__', paymentLineIndex);
                    const container = document.getElementById('paymentLinesBody');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const row = wrapper.firstElementChild;
                    container.appendChild(row);
                    paymentLineIndex++;

                    if (window.HSSelect) {
                        row.querySelectorAll('[data-hs-select]').forEach((el) => new HSSelect(el));
                    }
                    window.initDatePickers?.();

                    const codeSelect = row.querySelector('select.payment-code-select');
                    codeSelect?.addEventListener('change', updatePosCashSectionVisibility);
                    codeSelect?.addEventListener('change.hs.select', updatePosCashSectionVisibility);
                    updatePosCashSectionVisibility();

                    return row;
                };

                window.removePaymentLine = function (button) {
                    const row = button.closest('.payment-line');
                    if (document.querySelectorAll('#paymentLinesBody .payment-line').length > 1) {
                        row.remove();
                        updatePosCashSectionVisibility();
                    }
                };

                window.toggleChargesSection = function () {
                    const body = document.getElementById('chargesSectionBody');
                    const icon = document.getElementById('chargesToggleIcon');
                    const isHidden = body.classList.toggle('hidden');
                    icon.classList.toggle('rotate-180', ! isHidden);

                    if (! isHidden && document.querySelectorAll('#chargeLinesBody .charge-line').length === 0) {
                        addChargeLine();
                    }
                };

                window.addChargeLine = function () {
                    const template = document.getElementById('chargeLineTemplate');
                    const html = template.innerHTML.replaceAll('__INDEX__', chargeLineIndex);
                    const container = document.getElementById('chargeLinesBody');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const row = wrapper.firstElementChild;
                    container.appendChild(row);
                    chargeLineIndex++;

                    if (window.HSSelect) {
                        row.querySelectorAll('[data-hs-select]').forEach((el) => new HSSelect(el));
                    }

                    return row;
                };

                window.removeChargeLine = function (button) {
                    button.closest('.charge-line').remove();
                };

                /**
                 * Wiring inicial de la pantalla del documento. El orden de
                 * los tres init de submit importa: la limpieza de líneas
                 * vacías (initEmptyLineSubmitCleanup) tiene que registrarse
                 * ANTES que el envío por fetch del POS (initPosAjaxCheckout),
                 * para que ya haya quitado la línea sin producto del DOM
                 * antes de que se arme el FormData (los listeners de
                 * "submit" corren en el orden en que se registraron). El
                 * popover "aplicar tipo de precio a todas las líneas" pone,
                 * por cada línea que YA tenga producto elegido, el precio de
                 * ese tipo si el producto lo tiene (si no, la línea se deja
                 * tal como estaba); usa "--auto-close:false" así que se
                 * cierra a mano con clics realmente afuera, mismo motivo que
                 * los popovers de impuestos por línea.
                 * @returns {void}
                 */
                function init() {
                    const tipoDocumentoSelect = document.getElementById('doc-tipo_documento');
                    tipoDocumentoSelect.addEventListener('change', () => applyDocumentType(tipoDocumentoSelect.value));
                    document.getElementById('doc-resolution').addEventListener('change', fillPrefixSecuencial);
                    document.getElementById('doc-tipo_operacion').addEventListener('change', updateReferenceVisibility);
                    document.getElementById('doc-referencia_factura_id').addEventListener('input', (event) => {
                        const cursor = event.target.selectionStart;
                        event.target.value = event.target.value.toUpperCase();
                        event.target.setSelectionRange(cursor, cursor);

                        const numeral = event.target.value.trim();
                        clearTimeout(facturaLookupTimeout);
                        facturaLookupTimeout = setTimeout(() => lookupFactura(numeral), 500);
                    });
                    document.getElementById('doc-validate-uuid-btn').addEventListener('click', validateUuid);
                    applyDocumentType(tipoDocumentoSelect.value);

                    const departmentSelect = document.getElementById('doc-cliente_departamento_codigo');
                    rebuildCitySelect(document.getElementById('doc-cliente_ciudad_codigo'), departmentSelect.value, '{{ old('cliente_ciudad_codigo') }}');
                    departmentSelect.addEventListener('change', () => {
                        rebuildCitySelect(document.getElementById('doc-cliente_ciudad_codigo'), departmentSelect.value);
                    });

                    initClientSearch(departmentSelect);

                    const posEfectivoDisplay = document.getElementById('pos-efectivo-display');
                    const posEfectivoHidden = document.getElementById('pos-efectivo-hidden');
                    if (posEfectivoDisplay && posEfectivoHidden) {
                        posEfectivoDisplay.addEventListener('input', () => {
                            handleLinePriceInput(null, posEfectivoDisplay.value, posEfectivoHidden, posEfectivoDisplay);
                            updatePosChange();
                        });
                    }

                    initEmptyLineSubmitCleanup();
                    initSubmitProcessingState();
                    initPosAjaxCheckout();

                    initAllProductsModal();

                    const bulkPriceTypeDropdown = document.getElementById('doc-bulk-price-type-btn')?.closest('.hs-dropdown');
                    if (bulkPriceTypeDropdown) {
                        document.querySelectorAll('.doc-bulk-price-type-option').forEach((option) => {
                            option.addEventListener('click', () => {
                                const priceTypeId = option.dataset.priceTypeId;

                                document.querySelectorAll('#documentLinesBody .document-line').forEach((row) => {
                                    const prices = row.productPrices || [];
                                    const match = prices.find((price) => String(price.price_type_id) === priceTypeId);
                                    if (! match) {
                                        return;
                                    }

                                    setLinePriceValue(row, match.price);
                                    const precioSelect = row.querySelector('.line-precio-select');
                                    if (precioSelect) {
                                        setSelectValue(precioSelect, String(match.price));
                                    }
                                    recalcLine(row);
                                });

                                if (window.HSDropdown) {
                                    HSDropdown.close(bulkPriceTypeDropdown);
                                }
                            });
                        });

                        document.addEventListener('click', (event) => {
                            if (! bulkPriceTypeDropdown.classList.contains('open')) {
                                return;
                            }
                            if (! bulkPriceTypeDropdown.contains(event.target)) {
                                HSDropdown.close(bulkPriceTypeDropdown);
                            }
                        });
                    }

                    if (quotationPrefill) {
                        applyClientToFields(quotationPrefill.client, departmentSelect);
                        quotationPrefill.lines.forEach((line) => {
                            const row = addDocumentLine();
                            row.applyProduct(line.product);
                            const cantidadInput = row.querySelector('.line-cantidad');
                            if (cantidadInput) {
                                cantidadInput.value = line.qty;
                                cantidadInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            if (line.warehouse_id) {
                                const bodegaSelect = row.querySelector('.line-bodega');
                                setSelectValue(bodegaSelect, line.warehouse_id);
                                bodegaSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            // El precio siempre es el que quedó guardado en la
                            // cotización, no el que applyProduct() tomó del
                            // catálogo actual (que puede ser otra lista de
                            // precios distinta a la que se usó al cotizar) --
                            // por eso también se deja el selector de tipo de
                            // precio sin selección, igual que cuando se
                            // escribe un precio a mano.
                            setLinePriceValue(row, line.unit_price);
                            const precioSelect = row.querySelector('.line-precio-select');
                            if (precioSelect) {
                                setSelectValue(precioSelect, '');
                            }
                            recalcLine(row);
                        });
                    }

                    if (document.querySelectorAll('#documentLinesBody .document-line').length === 0) {
                        addDocumentLine();
                    }

                    if (document.querySelectorAll('#paymentLinesBody .payment-line').length === 0) {
                        addPaymentLine();
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
        </script>

        <x-date-picker-script />
        <x-date-range-picker-script />
        <x-dian-acquirer-lookup-script />
    @endpush
</x-layouts.app>
