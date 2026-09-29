@php
    $basicSelectConfig = \App\Support\SelectConfig::basic();
    $searchableSelectConfig = \App\Support\SelectConfig::searchable();
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
@endphp

<x-layouts.public :title="$program->name . ' — ' . __('Loyalty program')">
    <div class="flex min-h-[70vh] items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                @if ($program->branding['logo_url'] ?? null)
                    <img src="{{ $program->branding['logo_url'] }}" alt="{{ $program->name }}" class="mx-auto mb-4 max-h-16">
                @endif
                <h1 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $program->name }}</h1>
                <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('Sign up to start earning rewards.') }}</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <p id="loyalty-enroll-lookup-error" class="hidden mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></p>

            <form method="POST" action="{{ route('public.loyalty.enroll.store', $token) }}" class="flex flex-col gap-3">
                @csrf
                <div class="flex gap-3">
                    <flux:field class="w-44 shrink-0 [&>.hs-select]:max-w-[11rem]">
                        <flux:label>{{ __('Identification type') }}</flux:label>
                        <select id="loyalty-enroll-identification_type" name="identification_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                            @foreach ($identificationTypes as $code => $label)
                                <option value="{{ $code }}" @selected(old('identification_type', '13') == $code)>{{ $code }} - {{ $label }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                    <div class="flex-1 relative">
                        <flux:input id="loyalty-enroll-identificacion" name="identificacion" :label="__('Identification')" required value="{{ old('identificacion') }}" />
                    </div>
                    <div id="loyalty-enroll-dv_wrapper" class="hidden w-16 shrink-0">
                        <flux:input id="loyalty-enroll-dv" :label="__('DV')" maxlength="1" readonly />
                    </div>
                </div>

                <flux:button type="button" id="loyalty-enroll-continue-btn" variant="primary">{{ __('Continue') }}</flux:button>

                <div id="loyalty-enroll-step2" class="hidden flex flex-col gap-3">
                    <p class="text-sm text-zinc-600 dark:text-neutral-400">{{ __('No client found with that identification. Fill in the fields below to create a new one.') }}</p>

                    <flux:input name="name" :label="__('Name')" required value="{{ old('name') }}" />

                    <flux:field>
                        <flux:label>{{ __('Person type') }}</flux:label>
                        <select name="person_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                            <option value="2" @selected(old('person_type', '2') === '2')>{{ __('Natural person') }}</option>
                            <option value="1" @selected(old('person_type') === '1')>{{ __('Legal entity') }}</option>
                        </select>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Fiscal responsibilities') }}</flux:label>
                        @php $oldFiscalResponsibilities = old('fiscal_responsibilities', []); @endphp
                        <select name="fiscal_responsibilities[]" multiple data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                            @foreach ($fiscalResponsibilities as $responsibility)
                                <option value="{{ $responsibility->codigo }}" @selected(in_array($responsibility->codigo, $oldFiscalResponsibilities))>
                                    {{ $responsibility->codigo }} - {{ $responsibility->descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </flux:field>

                    <flux:input name="address" :label="__('Address')" value="{{ old('address') }}" />

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>{{ __('Department') }}</flux:label>
                            <select id="loyalty-enroll-department" name="department_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                                <option value="">{{ __('Select...') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->codigo }}" @selected(old('department_code') === $department->codigo)>{{ $department->descripcion }}</option>
                                @endforeach
                            </select>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('City') }}</flux:label>
                            <select id="loyalty-enroll-city" name="city_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                                <option value="">{{ __('Select...') }}</option>
                            </select>
                        </flux:field>
                    </div>

                    <flux:input name="phone" :label="__('Phone')" value="{{ old('phone') }}" />
                    <flux:input name="email" type="email" :label="__('Email')" value="{{ old('email') }}" />
                    <flux:button type="submit" variant="primary">{{ __('Sign up') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));

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

                function calculateDv(identification) {
                    const digits = (identification || '').replace(/\D/g, '').split('').reverse().map(Number);
                    const weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
                    const sum = digits.reduce((total, digit, i) => total + digit * (weights[i] || 0), 0);
                    const remainder = sum % 11;

                    return remainder > 1 ? 11 - remainder : remainder;
                }

                function refreshDv() {
                    const typeSelect = document.getElementById('loyalty-enroll-identification_type');
                    const numberInput = document.getElementById('loyalty-enroll-identificacion');
                    const dvWrapper = document.getElementById('loyalty-enroll-dv_wrapper');
                    const dvInput = document.getElementById('loyalty-enroll-dv');
                    if (! typeSelect || ! numberInput) return;

                    const isNit = typeSelect.value === '31';
                    dvWrapper.classList.toggle('hidden', ! isNit);
                    dvInput.value = isNit && numberInput.value ? calculateDv(numberInput.value) : '';
                }

                function init() {
                    const departmentSelect = document.getElementById('loyalty-enroll-department');
                    const citySelect = document.getElementById('loyalty-enroll-city');
                    if (! departmentSelect || departmentSelect.dataset.bound === 'true') return;
                    departmentSelect.dataset.bound = 'true';

                    rebuildCitySelect(citySelect, departmentSelect.value, '{{ old('city_code') }}');
                    departmentSelect.addEventListener('change', () => rebuildCitySelect(citySelect, departmentSelect.value));
                    departmentSelect.addEventListener('change.hs.select', () => rebuildCitySelect(citySelect, departmentSelect.value));

                    document.getElementById('loyalty-enroll-identificacion')?.addEventListener('input', refreshDv);
                    document.getElementById('loyalty-enroll-identification_type')?.addEventListener('change.hs.select', refreshDv);
                    refreshDv();

                    @if ($errors->any() || old('name'))
                        document.getElementById('loyalty-enroll-step2').classList.remove('hidden');
                    @endif

                    document.getElementById('loyalty-enroll-continue-btn').addEventListener('click', async function () {
                        const identificacion = document.getElementById('loyalty-enroll-identificacion').value.trim();
                        const errorEl = document.getElementById('loyalty-enroll-lookup-error');
                        errorEl.classList.add('hidden');

                        if (! identificacion) {
                            errorEl.textContent = '{{ __('Enter an identification.') }}';
                            errorEl.classList.remove('hidden');
                            return;
                        }

                        const response = await fetch('{{ route('public.loyalty.enroll.lookup', $token) }}?identificacion=' + encodeURIComponent(identificacion));
                        const data = await response.json();

                        if (data.found) {
                            window.location.href = data.url;
                            return;
                        }

                        document.getElementById('loyalty-enroll-step2').classList.remove('hidden');
                    });
                }

                document.addEventListener('DOMContentLoaded', init);
            })();
        </script>
    @endpush
</x-layouts.public>
