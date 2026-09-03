<x-layouts.app :title="__('Dashboard')">
    @include('partials.tittle', [
        'title' => __('Companies'),
        'subheading' => __('Select the company you want to work with'),
        'button' => ['route' => route('companies.create'), 'label' => __('New company'), 'id' => 'new-company-btn'],
    ])

    @if ($companies->isEmpty())
        <section class="flex min-h-[240px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
            <div class="max-w-md space-y-3">
                <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">{{ __('You have no registered companies yet.') }}</h2>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Create your first company to start issuing invoices.') }}
                </p>
            </div>
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($companies as $company)
                <div class="relative space-y-2 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600">
                    @if (\App\Models\User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []))
                        <button
                            type="button"
                            class="company-edit-btn absolute right-3 top-3 flex size-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                            aria-label="{{ __('Edit') }}"
                            onclick='openEditCompanyModal(@json($company))'
                        >
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                <path d="m15 5 4 4"></path>
                            </svg>
                        </button>
                    @endif

                    <form action="{{ route('dashboard.select-company') }}" method="POST">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->_id }}">

                        <button type="submit" class="w-full space-y-2 pr-8 text-left">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $company->name }}</h2>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Identification') }}: {{ $company->identificacion }}{{ $company->dv ? '-' . $company->dv : '' }}
                            </p>
                            @if (! empty($company->modules))
                                <div class="flex flex-wrap gap-1 pt-1">
                                    @foreach ($company->modules as $moduleKey)
                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ config("modules.$moduleKey.badge_classes", 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-200') }}">
                                            {{ config("modules.$moduleKey.name", $moduleKey) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </section>
    @endif

    <!-- Panel deslizante único para editar compañía -->
    <div id="edit-company" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="edit-company-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-5xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full max-h-[calc(100vh-3.5rem)] flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="edit-company-label" class="font-bold text-gray-800 dark:text-white">
                        {{ __('Edit :name', ['name' => __('Company')]) }}
                    </h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#edit-company">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    <form id="editCompanyForm" method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                @php
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

                <input type="hidden" id="edit-company-remove_logo" name="remove_logo" value="0">

                <div class="flex items-stretch gap-3">
                    <div class="shrink-0 relative w-32 aspect-square">
                        <button type="button" onclick="document.getElementById('edit-company-logo').click()" class="relative block size-full rounded-lg overflow-hidden focus:outline-hidden focus:ring-2 focus:ring-accent" title="{{ __('Logo') }}">
                            <img id="edit-company-logo-preview" src="" alt="" class="hidden size-full object-cover object-center">
                            <span id="edit-company-logo-placeholder" class="flex items-center justify-center size-full bg-accent/10 text-accent">
                                <svg class="shrink-0 size-11" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            </span>
                        </button>
                        <input type="file" id="edit-company-logo" name="logo" accept="image/*" class="hidden">
                        <button type="button" id="edit-company-logo-remove-btn" class="hidden absolute -top-1.5 -end-1.5 size-5 items-center justify-center rounded-full bg-white text-gray-500 border border-gray-200 shadow-sm hover:bg-red-50 hover:text-red-600 hover:border-red-200 focus:outline-hidden dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-400" onclick="removeEditCompanyLogo()" aria-label="{{ __('Remove image') }}" title="{{ __('Remove image') }}">
                            <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 flex flex-col gap-3">
                        <flux:field class="[&>.hs-select]:max-w-full">
                            <flux:label>{{ __('Identification type') }}</flux:label>
                            <select id="edit-company-identification_type" name="identification_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                <option value="">{{ __('Select...') }}</option>
                                @foreach ($identificationTypes as $code => $label)
                                    <option value="{{ $code }}">{{ $code }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </flux:field>
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <flux:input id="edit-company-identificacion" label="{{ __('Identification') }}" name="identificacion" />
                            </div>
                            <div id="edit-company-dv_wrapper" class="hidden w-16 shrink-0">
                                <flux:input id="edit-company-dv" label="{{ __('DV') }}" name="dv" maxlength="1" readonly />
                            </div>
                        </div>
                    </div>
                </div>

                <flux:input id="edit-company-name" label="{{ __('Company name') }}" name="name" />

                <div id="edit-company-person_type-field">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Person type') }}</label>
                    <select id="edit-company-person_type" name="person_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="">{{ __('Select...') }}</option>
                        <option value="1">{{ __('Legal entity') }}</option>
                        <option value="2">{{ __('Natural person') }}</option>
                    </select>
                </div>

                <div id="edit-company-fiscal_responsibilities-field">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Fiscal responsibilities') }}</label>
                    <select
                        id="edit-company-fiscal_responsibilities"
                        name="fiscal_responsibilities[]"
                        multiple
                        data-hs-select='{!! $basicSelectConfig !!}'
                        class="hidden"
                    >
                        @foreach ($fiscalResponsibilities as $responsibility)
                            <option value="{{ $responsibility->codigo }}">{{ $responsibility->codigo }} - {{ $responsibility->descripcion }}</option>
                        @endforeach
                    </select>
                </div>

                <flux:input id="edit-company-address" label="{{ __('Address') }}" name="address" />

                <div id="edit-company-department_code-field" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Department') }}</label>
                        <select id="edit-company-department_code" name="department_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->codigo }}">{{ $department->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('City') }}</label>
                        <select id="edit-company-city_code" name="city_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                            <option value="">{{ __('Select...') }}</option>
                        </select>
                    </div>
                </div>

                <div id="edit-company-phone-field" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-company-phone" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Phone') }}</label>
                        <div class="relative">
                            <input type="text" inputmode="numeric" data-numeric-only id="edit-company-phone" name="phone" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="edit-company-email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                        <div class="relative">
                            <input type="email" id="edit-company-email" name="email" class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="edit-company-status-field">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Status') }}</label>
                    <select id="edit-company-status" name="status" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                </div>

                <flux:separator :text="__('DIAN configuration')" />

                <div id="edit-company-dian_environment-field">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('DIAN environment') }}</label>
                    <select id="edit-company-dian_environment" name="dian_environment" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="1">1 - {{ __('Production') }}</option>
                        <option value="2">2 - {{ __('Testing (habilitación)') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('All documents for this company (invoices, notes, test batches) go to whichever environment is selected here.') }}</p>
                </div>

                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('These codes are provided when the invoicing software is registered, and are used to sync resolutions automatically. The account code is already your NIT, no need to enter it again.') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input id="edit-company-dian_pin" label="{{ __('Pin') }}" name="dian_pin" />
                    <flux:input id="edit-company-dian_software_id" label="{{ __('Software ID') }}" name="dian_software_id" />
                </div>

                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('The digital certificate is used to sign documents for all three modules (issuance, receiving and payroll), not just this one. When signing, the most recent certificate that has not expired yet is used.') }}</p>

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-neutral-700">
                    <p class="text-sm font-medium text-gray-800 dark:text-white">{{ __('Digital certificates') }}</p>
                    <flux:button type="button" variant="filled" icon="lock-closed" onclick="openCertificateManageModal()">{{ __('Digital certificates') }}</flux:button>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-neutral-700">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ __('DIAN enablement') }}</p>
                        <p id="edit-company-habilitacion-status" class="text-xs text-neutral-500 dark:text-neutral-400"></p>
                    </div>
                    <flux:button type="button" id="edit-company-habilitacion-btn" variant="filled" icon="shield-check" onclick="openHabilitacionModal()">{{ __('Enablement') }}</flux:button>
                </div>

                <div id="formErrors" class="text-red-500 text-sm"></div>

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" id="edit-company-submit-btn" variant="primary">{{ __('Save') }}</flux:button>
                </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="dian-habilitacion" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="dian-habilitacion-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full max-h-[calc(100vh-3.5rem)] flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="dian-habilitacion-label" class="font-bold text-gray-800 dark:text-white">{{ __('DIAN enablement') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#dian-habilitacion">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto p-4 space-y-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    <div id="dian-habilitacion-enabled-banner" class="hidden rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400">
                        {{ __('This company is already enabled before the DIAN.') }}
                    </div>
                    <div id="dian-habilitacion-pending-banner" class="hidden rounded-md bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                        {{ __('The test set is always sent to the DIAN testing environment, no matter the environment configured for this company.') }}
                    </div>

                    <div id="dian-habilitacion-toast" class="hidden rounded-md p-3 text-xs"></div>

                    <div id="dian-habilitacion-send-section" class="border border-gray-200 rounded-lg p-4 dark:border-neutral-700">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 mb-3">{{ __('Send test set') }}</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
                            {{ __('Builds and sends one invoice, one credit note and one debit note using generic test data, the fixed SETP test resolution, and this company as both issuer and receiver.') }}
                        </p>
                        <form id="dian-habilitacion-send-form" class="space-y-3">
                            <flux:input id="dian-habilitacion-test_set_id" name="dian_test_set_id" :label="__('Test set ID')" required />
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('The testSetId given by the DIAN when you start the enablement process on their portal.') }}</p>
                            <div class="flex items-center gap-3">
                                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send test set') }}</flux:button>
                                <flux:button type="button" id="dian-habilitacion-check-status-button" variant="filled" icon="arrow-path">{{ __('Check status') }}</flux:button>
                            </div>
                        </form>

                        <div id="dian-habilitacion-tracking" class="hidden mt-4 rounded-md bg-neutral-50 p-3 text-xs text-neutral-700 dark:bg-neutral-700/40 dark:text-neutral-300"></div>
                        <div id="dian-habilitacion-send-errors" class="mt-4 space-y-1"></div>
                        <div id="dian-habilitacion-status-results" class="mt-4 space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="certificate-manage" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="certificate-manage-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full max-h-[calc(100vh-3.5rem)] flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="certificate-manage-label" class="font-bold text-gray-800 dark:text-white">{{ __('Digital certificates') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#certificate-manage">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto p-4 space-y-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-neutral-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Certificate') }}</th>
                                    <th scope="col" class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Start date') }}</th>
                                    <th scope="col" class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('End date') }}</th>
                                    <th scope="col" class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody id="certificate-manage-list" class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 pt-4 dark:border-neutral-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 mb-3">{{ __('Add digital certificate') }}</h4>

                        <div id="certificate-manage-upload" data-hs-file-upload='{
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

                        <input type="file" id="certificate-manage-file-input" class="hidden" accept=".p12,.pfx">

                        <div class="flex items-end gap-2 mt-3">
                            <div class="flex-1">
                                <flux:input id="certificate-manage-password" type="password" :label="__('Certificate password')" />
                            </div>
                            <flux:button type="button" id="certificate-manage-validate" variant="filled">{{ __('Validate') }}</flux:button>
                        </div>
                        <p id="certificate-manage-validation-message" class="hidden text-xs mt-1"></p>

                        <div class="flex gap-3 mt-3">
                            <flux:spacer />
                            <flux:button type="button" id="certificate-manage-submit" variant="primary">{{ __('Add') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));

            function renderCertificatesTable(certificates) {
                const tbody = document.getElementById('certificate-manage-list');

                if (!certificates.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-neutral-500 dark:text-neutral-400">${@json(__('No certificates uploaded yet.'))}</td></tr>`;
                    return;
                }

                tbody.innerHTML = certificates.map((certificate) => {
                    const badgeClasses = certificate.is_expired
                        ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                        : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400';
                    const badgeText = certificate.is_expired ? @json(__('Expired')) : @json(__('Valid'));

                    return `
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-800 dark:text-neutral-200 break-words">${certificate.original_name ?? @json(__('Certificate'))}</td>
                            <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${certificate.valid_from ?? '—'}</td>
                            <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${certificate.valid_to ?? '—'}</td>
                            <td class="px-3 py-2 text-sm">
                                <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium ${badgeClasses}">${badgeText}</span>
                            </td>
                            <td class="px-3 py-2 text-end text-sm">
                                <div class="flex justify-end items-center gap-1">
                                    <a href="/companies/${window.currentEditCompany.id}/certificates/${certificate.id}/download" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" title="${@json(__('Download'))}">
                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                                    </a>
                                    <button type="button" class="certificate-delete-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" data-certificate-id="${certificate.id}" title="${@json(__('Remove'))}">
                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                tbody.querySelectorAll('.certificate-delete-btn').forEach((button) => {
                    button.addEventListener('click', async function () {
                        if (!(await window.appConfirmDialog.ask(@json(__('This action cannot be undone.'))))) {
                            return;
                        }

                        fetch(`/companies/${window.currentEditCompany.id}/certificates/${this.dataset.certificateId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        }).then(() => loadCertificatesList());
                    });
                });
            }

            function loadCertificatesList() {
                const tbody = document.getElementById('certificate-manage-list');
                tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-neutral-500 dark:text-neutral-400">${@json(__('Loading...'))}</td></tr>`;

                fetch(`/companies/${window.currentEditCompany.id}/certificates`, {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => renderCertificatesTable(data.certificates ?? []));
            }

            window.openCertificateManageModal = function () {
                document.getElementById('certificate-manage-file-input').value = '';
                document.getElementById('certificate-manage-password').value = '';
                document.getElementById('certificate-manage-validation-message').classList.add('hidden');

                const uploadEl = document.getElementById('certificate-manage-upload');
                if (window.HSFileUpload) {
                    HSFileUpload.autoInit();
                }
                const uploadInstance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
                if (uploadInstance?.element?.dropzone) {
                    uploadInstance.element.dropzone.removeAllFiles(true);
                }

                loadCertificatesList();

                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#certificate-manage');
                }
            };

            function initCertificateManageUpload() {
                const uploadEl = document.getElementById('certificate-manage-upload');
                if (!uploadEl || uploadEl.dataset.bound === 'true') {
                    return;
                }
                uploadEl.dataset.bound = 'true';

                const fileInput = document.getElementById('certificate-manage-file-input');
                const passwordInput = document.getElementById('certificate-manage-password');
                const validateBtn = document.getElementById('certificate-manage-validate');
                const submitBtn = document.getElementById('certificate-manage-submit');
                const messageEl = document.getElementById('certificate-manage-validation-message');
                let certificateValidated = false;

                function markUnvalidated() {
                    certificateValidated = false;
                    messageEl.classList.add('hidden');
                }

                function showValidationResult(valid, message) {
                    certificateValidated = valid;
                    messageEl.textContent = message;
                    messageEl.classList.remove('hidden', 'text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
                    messageEl.classList.add(...(valid ? ['text-green-600', 'dark:text-green-400'] : ['text-red-600', 'dark:text-red-400']));
                }

                passwordInput.addEventListener('input', markUnvalidated);

                validateBtn.addEventListener('click', function () {
                    if (!fileInput.files.length) {
                        showValidationResult(false, @json(__('Choose a certificate file first.')));
                        return;
                    }
                    if (!passwordInput.value) {
                        showValidationResult(false, @json(__('Enter the certificate password first.')));
                        return;
                    }

                    const formData = new FormData();
                    formData.append('certificate', fileInput.files[0]);
                    formData.append('certificate_password', passwordInput.value);

                    fetch(@json(route('certificate.validate')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                        .then((response) => response.json())
                        .then((data) => showValidationResult(!!data.valid, data.message))
                        .catch(() => showValidationResult(false, @json(__('Could not validate the certificate.'))));
                });

                submitBtn.addEventListener('click', function () {
                    if (!fileInput.files.length || !passwordInput.value) {
                        showValidationResult(false, @json(__('Choose a certificate file and password first.')));
                        return;
                    }
                    if (!certificateValidated) {
                        showValidationResult(false, @json(__('Validate the certificate password before saving.')));
                        return;
                    }

                    submitBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('certificate', fileInput.files[0]);
                    formData.append('certificate_password', passwordInput.value);

                    fetch(`/companies/${window.currentEditCompany.id}/certificates`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                        .then(async (response) => {
                            const data = await response.json();
                            if (!response.ok) {
                                throw new Error(data.message || @json(__('Could not upload the certificate.')));
                            }

                            fileInput.value = '';
                            passwordInput.value = '';
                            markUnvalidated();
                            const uploadInstance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
                            if (uploadInstance?.element?.dropzone) {
                                uploadInstance.element.dropzone.removeAllFiles(true);
                            }
                            loadCertificatesList();
                        })
                        .catch((error) => showValidationResult(false, error.message))
                        .finally(() => {
                            submitBtn.disabled = false;
                        });
                });

                if (window.HSFileUpload) {
                    HSFileUpload.autoInit();
                }
                const uploadInstance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
                if (uploadInstance?.element?.dropzone) {
                    const dropzone = uploadInstance.element.dropzone;

                    dropzone.on('addedfile', function (file) {
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        fileInput.files = transfer.files;
                        markUnvalidated();

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
                        fileInput.value = '';
                        markUnvalidated();
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', initCertificateManageUpload);
            document.addEventListener('livewire:navigated', initCertificateManageUpload);

            function setSelectValue(selectId, value) {
                const el = document.getElementById(selectId);
                const instance = window.HSSelect && HSSelect.getInstance(el);
                if (instance) {
                    instance.setValue(value ?? '');
                } else {
                    el.value = value ?? '';
                }
            }

            /**
             * Reconstruye el <select> de ciudad para un departamento (Preline's
             * destroy() prepends the original <select> to its grandparent, which
             * puts it before the field's <label> -- move it back to the end).
             * @param {HTMLSelectElement} citySelect
             * @param {string} departmentCode
             * @param {string} [selectedCityCode='']
             * @returns {void}
             */
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

            function refreshDv(identificationInput, dvInput, isNit) {
                dvInput.value = isNit && identificationInput.value ? calculateDv(identificationInput.value) : '';
            }

            function toggleDvField(identificationType) {
                const dvWrapper = document.getElementById('edit-company-dv_wrapper');
                const dvInput = document.getElementById('edit-company-dv');
                const identificationInput = document.getElementById('edit-company-identificacion');
                const isNit = identificationType === '31';

                dvWrapper.classList.toggle('hidden', !isNit);
                refreshDv(identificationInput, dvInput, isNit);
            }

            function setEditCompanyLogoPreview(url) {
                const img = document.getElementById('edit-company-logo-preview');
                const placeholder = document.getElementById('edit-company-logo-placeholder');
                const removeBtn = document.getElementById('edit-company-logo-remove-btn');

                if (url) {
                    img.src = url;
                    img.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                    removeBtn.classList.remove('hidden');
                    removeBtn.classList.add('flex');
                } else {
                    img.src = '';
                    img.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                    removeBtn.classList.add('hidden');
                    removeBtn.classList.remove('flex');
                }
            }

            window.removeEditCompanyLogo = function () {
                document.getElementById('edit-company-logo').value = '';
                document.getElementById('edit-company-remove_logo').value = '1';
                setEditCompanyLogoPreview(null);
            };

            function init() {
                const departmentSelect = document.getElementById('edit-company-department_code');

                if (!departmentSelect || departmentSelect.dataset.bound === 'true') {
                    return;
                }
                departmentSelect.dataset.bound = 'true';

                departmentSelect.addEventListener('change', () => {
                    rebuildCitySelect(document.getElementById('edit-company-city_code'), departmentSelect.value);
                });

                const identificationTypeSelect = document.getElementById('edit-company-identification_type');
                const identificationInput = document.getElementById('edit-company-identificacion');
                const dvInput = document.getElementById('edit-company-dv');

                identificationTypeSelect.addEventListener('change', () => {
                    toggleDvField(identificationTypeSelect.value);
                });
                identificationInput.addEventListener('input', () => {
                    refreshDv(identificationInput, dvInput, identificationTypeSelect.value === '31');
                });

                document.getElementById('edit-company-logo').addEventListener('change', (event) => {
                    const file = event.target.files?.[0];
                    document.getElementById('edit-company-remove_logo').value = '0';
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => setEditCompanyLogoPreview(reader.result);
                    reader.readAsDataURL(file);
                });

            }

            document.addEventListener('DOMContentLoaded', init);
            document.addEventListener('livewire:navigated', init);

            window.openEditCompanyModal = function (company) {
                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#edit-company');
                }

                document.getElementById('edit-company-logo').value = '';
                document.getElementById('edit-company-remove_logo').value = '0';
                setEditCompanyLogoPreview(company.logo_url ?? null);

                document.getElementById('edit-company-name').value = company.name ?? '';
                setSelectValue('edit-company-identification_type', company.identification_type);
                document.getElementById('edit-company-identificacion').value = company.identificacion ?? '';
                toggleDvField(company.identification_type ?? '');
                setSelectValue('edit-company-person_type', company.person_type);

                const fiscalResponsibilitiesCodes = (company.fiscal_responsibilities ?? '').split(';').filter(Boolean);
                const fiscalResponsibilitiesSelect = document.getElementById('edit-company-fiscal_responsibilities');
                const fiscalResponsibilitiesInstance = window.HSSelect && HSSelect.getInstance(fiscalResponsibilitiesSelect);
                if (fiscalResponsibilitiesInstance) {
                    fiscalResponsibilitiesInstance.setValue(fiscalResponsibilitiesCodes);
                }

                document.getElementById('edit-company-address').value = company.address ?? '';
                setSelectValue('edit-company-department_code', company.department_code);
                rebuildCitySelect(document.getElementById('edit-company-city_code'), company.department_code ?? '', company.city_code ?? '');
                document.getElementById('edit-company-phone').value = company.phone ?? '';
                document.getElementById('edit-company-email').value = company.email ?? '';
                setSelectValue('edit-company-status', company.status || 'active');
                setSelectValue('edit-company-dian_environment', company.dian_environment || '2');
                document.getElementById('edit-company-dian_pin').value = company.dian_pin ?? '';
                document.getElementById('edit-company-dian_software_id').value = company.dian_software_id ?? '';
                document.getElementById('editCompanyForm').action = `/companies/${company.id}`;

                window.currentEditCompany = company;
                loadCertificatesList();

                document.getElementById('edit-company-habilitacion-status').textContent = company.dian_habilitado
                    ? @json(__('Already enabled.'))
                    : @json(__('Not enabled yet.'));
            };

            function habilitacionToggleEnabled(habilitado) {
                document.getElementById('dian-habilitacion-enabled-banner').classList.toggle('hidden', !habilitado);
                document.getElementById('dian-habilitacion-pending-banner').classList.toggle('hidden', !!habilitado);
                document.getElementById('dian-habilitacion-send-section').classList.toggle('hidden', !!habilitado);
                document.getElementById('edit-company-habilitacion-status').textContent = habilitado
                    ? @json(__('Already enabled.'))
                    : @json(__('Not enabled yet.'));
            }

            function showHabilitacionToast(type, message) {
                const el = document.getElementById('dian-habilitacion-toast');
                el.textContent = message;
                el.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'dark:bg-green-900/20', 'dark:text-green-400', 'bg-red-50', 'text-red-800', 'dark:bg-red-900/20', 'dark:text-red-400');
                el.classList.add(...(type === 'success'
                    ? ['bg-green-50', 'text-green-800', 'dark:bg-green-900/20', 'dark:text-green-400']
                    : ['bg-red-50', 'text-red-800', 'dark:bg-red-900/20', 'dark:text-red-400']));
            }

            function renderTestSetErrors(errors) {
                const container = document.getElementById('dian-habilitacion-send-errors');
                container.innerHTML = '';
                (errors ?? []).forEach((error) => {
                    const div = document.createElement('div');
                    div.className = 'rounded-md bg-red-50 p-3 text-xs text-red-700 dark:bg-red-900/20 dark:text-red-400';
                    div.innerHTML = `<span class="font-medium"></span> — `;
                    div.querySelector('span').textContent = error.xml_file_name;
                    div.append(document.createTextNode(error.processed_message ?? ''));
                    container.appendChild(div);
                });
            }

            function renderTestSetStatus(results) {
                const container = document.getElementById('dian-habilitacion-status-results');
                container.innerHTML = '';
                (results ?? []).forEach((item) => {
                    const div = document.createElement('div');
                    div.className = 'rounded-md border border-gray-200 p-3 text-xs dark:border-neutral-700';

                    const validLine = document.createElement('p');
                    validLine.innerHTML = `<span class="font-medium">${@json(__('Is valid'))}:</span> `;
                    validLine.append(document.createTextNode(item.is_valid ? @json(__('Yes')) : @json(__('No'))));
                    div.appendChild(validLine);

                    const statusLine = document.createElement('p');
                    statusLine.innerHTML = `<span class="font-medium">${@json(__('Status'))}:</span> `;
                    statusLine.append(document.createTextNode(`${item.status_code ?? ''} — ${item.status_description ?? ''}`));
                    div.appendChild(statusLine);

                    if (item.error_messages?.length) {
                        const ul = document.createElement('ul');
                        ul.className = 'mt-1 list-disc list-inside text-red-600 dark:text-red-400';
                        item.error_messages.forEach((message) => {
                            const li = document.createElement('li');
                            li.textContent = message;
                            ul.appendChild(li);
                        });
                        div.appendChild(ul);
                    }

                    container.appendChild(div);
                });
            }

            async function postHabilitacionJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        ...(body ? { 'Content-Type': 'application/json' } : {}),
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await response.json().catch(() => ({}));
                return { ok: response.ok, data };
            }

            function showHabilitacionTracking(zipKey) {
                const el = document.getElementById('dian-habilitacion-tracking');
                if (! zipKey) {
                    el.classList.add('hidden');
                    el.textContent = '';
                    return;
                }
                el.textContent = `${@json(__('Already sent. DIAN track ID'))}: ${zipKey}`;
                el.classList.remove('hidden');
            }

            document.getElementById('dian-habilitacion-send-form').addEventListener('submit', async function (event) {
                event.preventDefault();
                const company = window.currentEditCompany;
                if (! company) {
                    return;
                }
                const testSetId = document.getElementById('dian-habilitacion-test_set_id').value;
                const { ok, data } = await postHabilitacionJson(`/companies/${company.id}/dian/send-test-set`, { dian_test_set_id: testSetId });
                showHabilitacionToast(ok ? 'success' : 'error', data.message ?? '');
                renderTestSetErrors(data.errors);
                if (ok) {
                    company.dian_test_set_id = testSetId;
                }
                if (ok && data.zip_key) {
                    company.dian_test_set_zip_key = data.zip_key;
                    showHabilitacionTracking(data.zip_key);
                }
            });

            document.getElementById('dian-habilitacion-check-status-button').addEventListener('click', async function () {
                const company = window.currentEditCompany;
                if (! company) {
                    return;
                }
                const { ok, data } = await postHabilitacionJson(`/companies/${company.id}/dian/test-set-status`);
                if (! ok) {
                    showHabilitacionToast('error', data.message ?? '');
                    return;
                }
                renderTestSetStatus(data.results);
                if (data.dian_habilitado) {
                    company.dian_habilitado = true;
                    habilitacionToggleEnabled(true);
                }
            });

            window.openHabilitacionModal = function () {
                const company = window.currentEditCompany;
                if (! company) {
                    return;
                }

                document.getElementById('dian-habilitacion-test_set_id').value = company.dian_test_set_id ?? '';
                document.getElementById('dian-habilitacion-toast').classList.add('hidden');
                document.getElementById('dian-habilitacion-send-errors').innerHTML = '';
                document.getElementById('dian-habilitacion-status-results').innerHTML = '';
                showHabilitacionTracking(company.dian_test_set_zip_key);

                habilitacionToggleEnabled(company.dian_habilitado);

                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#dian-habilitacion');
                }
            };
        })();
    </script>
</x-layouts.app>
