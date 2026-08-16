@php
    // Panel compartido entre la pantalla de Clientes/Proveedores y el modal
    // rápido de "nuevo cliente" del POS -- mismos campos en los dos lados
    // para no tener un formulario "pobre" en el punto de venta y otro
    // completo en Clientes. $identificationTypes, $departments y
    // $fiscalResponsibilities los define cada vista que lo incluye.
    $panelLabel ??= __('Client');
    $basicSelectConfig = \App\Support\SelectConfig::basic();
    $searchableSelectConfig = \App\Support\SelectConfig::searchable();
@endphp

<div id="third-party-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-lg w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="third-party-panel-label">
    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="third-party-panel-label" class="font-bold text-gray-800 dark:text-white">{{ $panelLabel }}</h3>
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

            {{-- Oculto por defecto: lo usa el submit por AJAX (POS) para
                 mostrar errores sin salir del modal. El bloque de abajo
                 (@if $errors->any()) sigue cubriendo el submit normal de la
                 pantalla de Clientes/Proveedores, que sí recarga la página. --}}
            <div id="tp-panel-error" class="hidden rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>

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
                            <option value="{{ $code }}" @selected(old('identification_type', '13') == $code)>{{ $code }} - {{ $label }}</option>
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
