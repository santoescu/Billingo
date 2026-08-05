<x-layouts.app :title="__('New company')">
    @include('partials.tittle', [
        'title' => __('New company'),
        'subheading' => __('Register a new company to issue invoices from.'),
    ])

    <form method="POST" action="{{ route('companies.store') }}" enctype="multipart/form-data" class="max-w-3xl mx-auto space-y-6">
        @csrf

        @php
            $basicSelectConfig = \App\Support\SelectConfig::basic();

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

        <div class="flex gap-3">
            <flux:field class="w-60 shrink-0 [&>.hs-select]:max-w-[15rem]">
                <flux:label>{{ __('Identification type') }}</flux:label>
                <select id="identification_type" name="identification_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach ($identificationTypes as $code => $label)
                        <option value="{{ $code }}" @selected(old('identification_type') === $code)>{{ $code }} - {{ $label }}</option>
                    @endforeach
                </select>
            </flux:field>
            <div class="flex-1">
                <flux:input id="identificacion" name="identificacion" :label="__('Identification')" value="{{ old('identificacion') }}" required />
            </div>
            <div id="dv_wrapper" class="hidden w-16 shrink-0">
                <flux:input id="dv" name="dv" :label="__('DV')" maxlength="1" value="{{ old('dv') }}" readonly />
            </div>
        </div>

        <div>
            <flux:input name="name" :label="__('Company name')" value="{{ old('name') }}" required />
        </div>

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Person type') }}</label>
            <select name="person_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                <option value="">{{ __('Select...') }}</option>
                <option value="1" @selected(old('person_type') === '1')>{{ __('Legal entity') }}</option>
                <option value="2" @selected(old('person_type') === '2')>{{ __('Natural person') }}</option>
            </select>
        </div>

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Fiscal responsibilities') }}</label>
            @php $oldFiscalResponsibilities = old('fiscal_responsibilities', []); @endphp
            <select
                name="fiscal_responsibilities[]"
                multiple
                data-hs-select='{!! $basicSelectConfig !!}'
                class="hidden"
            >
                @foreach ($fiscalResponsibilities as $responsibility)
                    <option value="{{ $responsibility->codigo }}" @selected(in_array($responsibility->codigo, $oldFiscalResponsibilities))>
                        {{ $responsibility->codigo }} - {{ $responsibility->descripcion }}
                    </option>
                @endforeach
            </select>
        </div>

        <flux:input name="address" :label="__('Address')" value="{{ old('address') }}" />

        @php
            $searchableSelectConfig = \App\Support\SelectConfig::searchable();
        @endphp

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Department') }}</label>
                <select id="department_code" name="department_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->codigo }}" @selected(old('department_code') === $department->codigo)>{{ $department->descripcion }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('City') }}</label>
                <select id="city_code" name="city_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                    <option value="">{{ __('Select...') }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="company-create-phone" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Phone') }}</label>
                <div class="relative">
                    <input type="text" id="company-create-phone" name="phone" value="{{ old('phone') }}" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    </div>
                </div>
            </div>
            <div>
                <label for="company-create-email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                <div class="relative">
                    <input type="email" id="company-create-email" name="email" value="{{ old('email') }}" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                    </div>
                </div>
            </div>
        </div>

        <flux:separator :text="__('DIAN configuration')" />

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('DIAN environment') }}</label>
            <select name="dian_environment" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                <option value="1" {{ old('dian_environment') === '1' ? 'selected' : '' }}>1 - {{ __('Production') }}</option>
                <option value="2" {{ old('dian_environment', '2') === '2' ? 'selected' : '' }}>2 - {{ __('Testing (habilitación)') }}</option>
            </select>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('All documents for this company (invoices, notes, test batches) go to whichever environment is selected here.') }}</p>
        </div>

        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('These codes are provided when the invoicing software is registered, and are used to sync resolutions automatically. The account code is already your NIT, no need to enter it again.') }}</p>

        <div class="grid grid-cols-2 gap-3">
            <flux:input name="dian_pin" :label="__('Pin')" value="{{ old('dian_pin') }}" />
            <flux:input name="dian_software_id" :label="__('Software ID')" value="{{ old('dian_software_id') }}" />
        </div>

        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('The digital certificate is used to sign documents for all three modules (issuance, receiving and payroll), not just this one.') }}</p>

        <div id="certificate-upload" data-hs-file-upload='{
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
                    {{ __('Drop your certificate here or') }} <span class="font-semibold text-accent">{{ __('browse') }}</span>
                </p>
            </div>

            <div class="mt-2 space-y-2 empty:mt-0" data-hs-file-upload-previews></div>
        </div>

        <input type="file" name="dian_certificate" id="dian_certificate_input" class="hidden" accept=".p12,.pfx">

        <div class="flex items-end gap-2">
            <div class="flex-1">
                <flux:input id="dian_certificate_password" type="password" name="dian_certificate_password" :label="__('Certificate password')" />
            </div>
            <flux:button type="button" id="dian_certificate_validate" variant="filled">{{ __('Validate') }}</flux:button>
        </div>
        <p id="dian_certificate_validation_message" class="hidden text-xs"></p>

        <div class="flex gap-3">
            <flux:spacer />
            <a href="{{ route('dashboard') }}">
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </a>
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));

                function rebuildCitySelect(citySelect, departmentCode, selectedCityCode = '') {
                    const instance = window.HSSelect && HSSelect.getInstance(citySelect);
                    if (instance && typeof instance.destroy === 'function') {
                        instance.destroy();
                        // Preline's destroy() prepends the original <select> to its grandparent,
                        // which puts it before the field's <label>. Move it back to the end.
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

                function refreshDv(identificationInput, dvInput, isNit) {
                    dvInput.value = isNit && identificationInput.value ? calculateDv(identificationInput.value) : '';
                }

                function toggleDvField(identificationType) {
                    const dvWrapper = document.getElementById('dv_wrapper');
                    const dvInput = document.getElementById('dv');
                    const identificationInput = document.getElementById('identificacion');
                    const isNit = identificationType === '31';

                    dvWrapper.classList.toggle('hidden', !isNit);
                    refreshDv(identificationInput, dvInput, isNit);
                }

                function init() {
                    const departmentSelect = document.getElementById('department_code');
                    const citySelect = document.getElementById('city_code');

                    if (!departmentSelect || !citySelect || departmentSelect.dataset.bound === 'true') {
                        return;
                    }
                    departmentSelect.dataset.bound = 'true';

                    rebuildCitySelect(citySelect, departmentSelect.value, '{{ old('city_code') }}');

                    departmentSelect.addEventListener('change', () => {
                        rebuildCitySelect(citySelect, departmentSelect.value);
                    });

                    const identificationTypeSelect = document.getElementById('identification_type');
                    const identificationInput = document.getElementById('identificacion');
                    const dvInput = document.getElementById('dv');

                    toggleDvField(identificationTypeSelect.value);
                    identificationTypeSelect.addEventListener('change', () => {
                        toggleDvField(identificationTypeSelect.value);
                    });
                    identificationInput.addEventListener('input', () => {
                        refreshDv(identificationInput, dvInput, identificationTypeSelect.value === '31');
                    });

                    // El dropzone no sube el archivo (autoProcessQueue: false): solo lo
                    // usamos para elegirlo. Lo copiamos al <input type="file"> real que
                    // sí viaja con el resto del formulario al guardar la empresa.
                    const certificateUploadEl = document.getElementById('certificate-upload');
                    const certificateInput = document.getElementById('dian_certificate_input');
                    const certificatePasswordInput = document.getElementById('dian_certificate_password');
                    const certificateValidateBtn = document.getElementById('dian_certificate_validate');
                    const certificateValidationMessage = document.getElementById('dian_certificate_validation_message');
                    let certificateValidated = false;

                    function markCertificateUnvalidated() {
                        certificateValidated = false;
                        certificateValidationMessage.classList.add('hidden');
                    }

                    function showCertificateValidationResult(valid, message) {
                        certificateValidated = valid;
                        certificateValidationMessage.textContent = message;
                        certificateValidationMessage.classList.remove('hidden', 'text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
                        certificateValidationMessage.classList.add(...(valid ? ['text-green-600', 'dark:text-green-400'] : ['text-red-600', 'dark:text-red-400']));
                    }

                    certificatePasswordInput.addEventListener('input', markCertificateUnvalidated);

                    certificateValidateBtn.addEventListener('click', function () {
                        if (!certificateInput.files.length) {
                            showCertificateValidationResult(false, @json(__('Choose a certificate file first.')));
                            return;
                        }
                        if (!certificatePasswordInput.value) {
                            showCertificateValidationResult(false, @json(__('Enter the certificate password first.')));
                            return;
                        }

                        const formData = new FormData();
                        formData.append('certificate', certificateInput.files[0]);
                        formData.append('certificate_password', certificatePasswordInput.value);

                        fetch(@json(route('certificate.validate')), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: formData,
                        })
                            .then((response) => response.json())
                            .then((data) => showCertificateValidationResult(!!data.valid, data.message))
                            .catch(() => showCertificateValidationResult(false, @json(__('Could not validate the certificate.'))));
                    });

                    if (window.HSFileUpload) {
                        HSFileUpload.autoInit();
                    }
                    const certificateInstance = window.HSFileUpload && HSFileUpload.getInstance(certificateUploadEl, true);
                    if (certificateInstance?.element?.dropzone) {
                        const dropzone = certificateInstance.element.dropzone;

                        dropzone.on('addedfile', function (file) {
                            const transfer = new DataTransfer();
                            transfer.items.add(file);
                            certificateInput.files = transfer.files;
                            markCertificateUnvalidated();

                            // No hay subida real (autoProcessQueue: false), así que la barra
                            // nunca se movería sola: la marcamos como completa apenas se elige
                            // el archivo, para que se vea igual que en "editar".
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

                        dropzone.on('removedfile', function () {
                            certificateInput.value = '';
                            markCertificateUnvalidated();
                        });
                    }

                    document.querySelector('form[action="{{ route('companies.store') }}"]').addEventListener('submit', function (event) {
                        if (certificateInput.files.length && !certificateValidated) {
                            event.preventDefault();
                            showCertificateValidationResult(false, @json(__('Validate the certificate password before saving.')));
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', init);
                document.addEventListener('livewire:navigated', init);
            })();
        </script>
    @endpush
</x-layouts.app>
