@php
    $isClient = $role === 'cliente';
    $storeRoute = $isClient ? 'clients.store' : 'providers.store';
    $updateRouteBase = $isClient ? 'clients.update' : 'providers.update';
    $destroyRouteBase = $isClient ? 'clients.destroy' : 'providers.destroy';

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

<x-layouts.app :title="$isClient ? __('Clients') : __('Providers')">
    @include('partials.tittle', [
        'title' => $isClient ? __('Clients') : __('Providers'),
        'subheading' => $isClient
            ? __('Everyone you issue invoices to.')
            : __('Everyone you receive documents from.'),
    ])

    <div class="flex flex-col">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <flux:button variant="primary" icon="plus" onclick="openThirdPartyPanel()">
                            {{ $isClient ? __('New client') : __('New provider') }}
                        </flux:button>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="thirdPartiesTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Email') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Phone') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($thirdParties as $thirdParty)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $thirdParty->name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->identificacion }}{{ $thirdParty->dv ? '-'.$thirdParty->dv : '' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->email ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->phone ?? '—' }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex justify-end gap-1">
                                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="openThirdPartyPanel({!! Illuminate\Support\Js::from($thirdParty) !!})">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                        <path d="m15 5 4 4"></path>
                                                    </svg>
                                                </button>

                                                <form action="{{ route($destroyRouteBase, $thirdParty->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}">
                                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel deslizante único: agregar/editar tercero -->
    <div id="third-party-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-lg w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="third-party-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="third-party-panel-label" class="font-bold text-gray-800 dark:text-white">
                {{ $isClient ? __('Client') : __('Provider') }}
            </h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#third-party-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <form id="thirdPartyForm" method="POST" action="{{ route($storeRoute) }}" class="space-y-6" data-dian-lookup-form>
                @csrf
                <input type="hidden" id="tp-method" name="_method" value="POST">

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex gap-3" data-dian-lookup>
                    <flux:field class="w-44 shrink-0 [&>.hs-select]:max-w-[11rem]">
                        <flux:label>{{ __('Identification type') }}</flux:label>
                        <select id="tp-identification_type" name="identification_type" data-hs-select='{!! $basicSelectConfig !!}' data-dian-lookup-type class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($identificationTypes as $code => $label)
                                <option value="{{ $code }}" @selected(old('identification_type', '13') === $code)>{{ $code }} - {{ $label }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                    <div class="flex-1 relative">
                        <flux:input id="tp-identificacion" name="identificacion" :label="__('Identification')" value="{{ old('identificacion') }}" data-dian-lookup-number required />
                        <div class="absolute bottom-0 end-0 h-10 flex items-center pe-3 pointer-events-none">
                            <div class="hidden" data-dian-lookup-spinner>
                                <div class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full text-accent" role="status" aria-label="{{ __('Loading') }}">
                                    <span class="sr-only">{{ __('Loading') }}...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="tp-dv_wrapper" class="hidden w-16 shrink-0" data-dian-lookup-dv-wrapper>
                        <flux:input id="tp-dv" name="dv" :label="__('DV')" maxlength="1" value="{{ old('dv') }}" readonly data-dian-lookup-dv />
                    </div>
                </div>

                <div class="-mt-3 text-xs">
                    <span data-dian-lookup-status class="hidden text-zinc-500 dark:text-neutral-400"></span>
                </div>

                <flux:input id="tp-name" name="name" :label="__('Name')" value="{{ old('name') }}" data-dian-lookup-name required />

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Person type') }}</label>
                    <select id="tp-person_type" name="person_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="">{{ __('Select...') }}</option>
                        <option value="1" @selected(old('person_type') === '1')>{{ __('Legal entity') }}</option>
                        <option value="2" @selected(old('person_type') === '2')>{{ __('Natural person') }}</option>
                    </select>
                </div>

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Fiscal responsibilities') }}</label>
                    @php $oldFiscalResponsibilities = old('fiscal_responsibilities', []); @endphp
                    <select id="tp-fiscal_responsibilities" name="fiscal_responsibilities[]" multiple data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        @foreach ($fiscalResponsibilities as $responsibility)
                            <option value="{{ $responsibility->codigo }}" @selected(in_array($responsibility->codigo, $oldFiscalResponsibilities))>
                                {{ $responsibility->codigo }} - {{ $responsibility->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <flux:input id="tp-address" name="address" :label="__('Address')" value="{{ old('address') }}" />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Department') }}</label>
                        <select id="tp-department_code" name="department_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->codigo }}" @selected(old('department_code') === $department->codigo)>{{ $department->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('City') }}</label>
                        <select id="tp-city_code" name="city_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="tp-phone" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Phone') }}</label>
                        <div class="relative">
                            <input type="text" inputmode="numeric" data-numeric-only id="tp-phone" name="phone" value="{{ old('phone') }}" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="tp-email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                        <div class="relative">
                            <input type="email" id="tp-email" name="email" value="{{ old('email') }}" data-dian-lookup-email class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            (function () {
                const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));

                function setSelectValue(selectId, value) {
                    const el = document.getElementById(selectId);
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

                window.openThirdPartyPanel = function (thirdParty) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#third-party-panel');
                    }

                    document.getElementById('tp-dian-lookup-status')?.classList.add('hidden');
                    document.getElementById('tp-dian-lookup-spinner')?.classList.add('hidden');

                    const form = document.getElementById('thirdPartyForm');

                    document.getElementById('tp-name').value = thirdParty?.name ?? '';
                    setSelectValue('tp-identification_type', thirdParty?.identification_type ?? '13');
                    document.getElementById('tp-identificacion').value = thirdParty?.identificacion ?? '';
                    document.querySelector('[data-dian-lookup]')?.dianLookupTrigger?.();
                    setSelectValue('tp-person_type', thirdParty?.person_type);

                    const fiscalResponsibilitiesCodes = (thirdParty?.fiscal_responsibilities ?? '').split(';').filter(Boolean);
                    const fiscalResponsibilitiesSelect = document.getElementById('tp-fiscal_responsibilities');
                    const fiscalResponsibilitiesInstance = window.HSSelect && HSSelect.getInstance(fiscalResponsibilitiesSelect);
                    if (fiscalResponsibilitiesInstance) {
                        fiscalResponsibilitiesInstance.setValue(fiscalResponsibilitiesCodes);
                    }

                    document.getElementById('tp-address').value = thirdParty?.address ?? '';
                    setSelectValue('tp-department_code', thirdParty?.department_code);
                    rebuildCitySelect(document.getElementById('tp-city_code'), thirdParty?.department_code ?? '', thirdParty?.city_code ?? '');
                    document.getElementById('tp-phone').value = thirdParty?.phone ?? '';
                    document.getElementById('tp-email').value = thirdParty?.email ?? '';

                    if (thirdParty?.id) {
                        form.action = @json(route($updateRouteBase, ['thirdParty' => '__ID__'])).replace('__ID__', thirdParty.id);
                        document.getElementById('tp-method').value = 'PUT';
                    } else {
                        form.action = @json(route($storeRoute));
                        document.getElementById('tp-method').value = 'POST';
                    }
                };

                function init() {
                    const departmentSelect = document.getElementById('tp-department_code');

                    if (!departmentSelect || departmentSelect.dataset.bound === 'true') {
                        return;
                    }
                    departmentSelect.dataset.bound = 'true';

                    rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value, '{{ old('city_code') }}');
                    departmentSelect.addEventListener('change', () => {
                        rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value);
                    });

                    initWorkflowDataTable('#thirdPartiesTable', '#hs-table-with-pagination-search');

                    @if ($errors->any())
                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#third-party-panel');
                        }
                    @endif
                }

                document.addEventListener('DOMContentLoaded', init);
                document.addEventListener('livewire:navigated', init);
            })();
        </script>

        <x-dian-acquirer-lookup-script />
    @endpush
</x-layouts.app>
