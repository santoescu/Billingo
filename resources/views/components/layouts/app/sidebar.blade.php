@php
    
    $myModules = session('selected_company.modules', []);
    $hasInvoicing = array_key_exists('invoicing', $myModules);
    $hasPos = array_key_exists('pos', $myModules);
    $hasCotizaciones = array_key_exists('cotizaciones', $myModules);

    $sections = [
        
        [
            'label' => null,
            'items' => [
                [
                    'name' => __('Dashboard'),
                    'icon' => 'home',
                    'url' => route('dashboard'),
                    'current' => request()->routeIs('dashboard'),
                ],
            ],
        ],

        [
            'label' => __('Document issuance'),
            'items' => $hasInvoicing ? [
                [
                    'name' => __('Issued documents'),
                    'icon' => 'document-text',
                    'url' => route('documents.index'),
                    'current' => request()->routeIs('documents.*'),
                ],
            ] : [],
        ],

        [
            'label' => __('Point of sale'),
            'items' => array_key_exists('pos', $myModules) ? [
                [
                    'name' => __('Sell'),
                    'icon' => 'shopping-cart',
                    'url' => route('pos.create'),
                    'current' => request()->routeIs('pos.create') || request()->routeIs('pos.checkout'),
                ],
                [
                    'name' => __('Sales'),
                    'icon' => 'receipt-percent',
                    'url' => route('pos.sales.index'),
                    'current' => request()->routeIs('pos.sales.*'),
                ],
                [
                    'name' => __('Payment methods'),
                    'icon' => 'credit-card',
                    'url' => route('pos.payment-methods.index'),
                    'current' => request()->routeIs('pos.payment-methods.*'),
                ],
            ] : [],
        ],

        [
            'label' => __('Quotations'),
            'items' => array_key_exists('cotizaciones', $myModules) ? [
                [
                    'name' => __('New quotation'),
                    'icon' => 'document-plus',
                    'url' => route('quotations.create'),
                    'current' => request()->routeIs('quotations.create') || request()->routeIs('quotations.store'),
                ],
                [
                    'name' => __('Quotations'),
                    'icon' => 'document-text',
                    'url' => route('quotations.index'),
                    'current' => request()->routeIs('quotations.index') || request()->routeIs('quotations.show'),
                ],
            ] : [],
        ],

        [
            'label' => __('Payroll'),
            'items' => array_key_exists('payroll', $myModules) ? [
                
            ] : [],
        ],

        [
            'label' => __('Document receiving'),
            'items' => array_key_exists('receiving', $myModules) ? [
                [
                    'name' => __('Providers'),
                    'icon' => 'truck',
                    'url' => route('providers.index'),
                    'current' => request()->routeIs('providers.*'),
                ],
            ] : [],
        ],

        [
            'label' => __('Company'),
            'items' => session('selected_company') ? array_values(array_filter([
                ($hasInvoicing || $hasPos || $hasCotizaciones) ? [
                    'name' => __('Clients'),
                    'icon' => 'user-group',
                    'url' => route('clients.index'),
                    'current' => request()->routeIs('clients.*'),
                ] : null,
                ($hasInvoicing || $hasPos || $hasCotizaciones) ? [
                    'name' => __('Inventory'),
                    'icon' => 'cube',
                    'url' => route('products.index'),
                    'current' => request()->routeIs('products.*') || request()->routeIs('warehouses.*'),
                ] : null,
                ($hasInvoicing || $hasPos || $hasCotizaciones) ? [
                    'name' => __('Resolutions'),
                    'icon' => 'document-check',
                    'url' => route('dian.resolutions.index'),
                    'current' => request()->routeIs('dian.resolutions.*'),
                ] : null,
                [
                    'name' => __('Members'),
                    'icon' => 'users',
                    'url' => route('companies.members.index'),
                    'current' => request()->routeIs('companies.members.*'),
                ],
            ])) : [],
        ],

        [
            'label' => __('Global administration'),
            'items' => auth()->user()?->isGlobalAdmin() ? [
                [
                    'name' => __('All companies'),
                    'icon' => 'building-office-2',
                    'url' => route('admin.companies'),
                    'current' => request()->routeIs('admin.companies'),
                ],
                [
                    'name' => __('All users'),
                    'icon' => 'shield-check',
                    'url' => route('admin.users'),
                    'current' => request()->routeIs('admin.users'),
                ],
                [
                    'name' => __('Send notification'),
                    'icon' => 'bell',
                    'url' => route('admin.notifications.create'),
                    'current' => request()->routeIs('admin.notifications.create'),
                ],
            ] : [],
        ],
    ];

    $sections = array_values(array_filter($sections, fn ($section) => ! empty($section['items'])));

    $appearance = session('appearance', 'light');
    $isDark = $appearance === 'dark';
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-neutral-800">
<script>
    // Esta app no es una SPA (cada página es una recarga completa), y Preline
    // no persiste solo si el sidebar quedó minimizado -- por defecto arranca
    // expandido en cada carga, aunque el usuario lo haya dejado cerrado. Este
    // bloque va lo más arriba posible del <body> (antes de pintar el
    // sidebar) para aplicar el estado guardado sin parpadeo.
    if (localStorage.getItem('sidebarMinified') === 'true') {
        document.body.classList.add('hs-overlay-minified');
    }
</script>
@include('components.toast')
@include('components.confirm-dialog')

<div class="fixed z-[100] bottom-4 start-2 lg:start-[272px] hs-overlay-minified:lg:start-[68px] transition-[inset-inline-start] duration-300 ease-in-out">
    @include('partials.notifications-bell')
</div>

<!-- Navigation Toggle (Mobile) -->
<div class="lg:hidden p-3">
    <button
        type="button"
        class="flex justify-center items-center flex-none size-9 text-sm text-gray-600 hover:bg-gray-100 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:hover:text-neutral-200 dark:focus:text-neutral-200"
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-controls="hs-sidebar-content-push-to-mini-sidebar"
        aria-label="{{ __('Toggle navigation') }}"
        data-hs-overlay="#hs-sidebar-content-push-to-mini-sidebar"
    >
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M15 3v18"></path><path d="m10 15-3-3 3-3"></path>
        </svg>
    </button>
</div>
<!-- End Navigation Toggle -->

<!-- Sidebar -->
<div
    id="hs-sidebar-content-push-to-mini-sidebar"
    class="hs-overlay [--auto-close:lg] hs-overlay-minified:w-13 lg:block lg:translate-x-0 lg:end-auto lg:bottom-0 w-64
               hs-overlay-open:translate-x-0 -translate-x-full transition-all duration-300 transform
               h-full hidden fixed top-0 start-0 bottom-0 z-60
               bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700"
    role="dialog"
    tabindex="-1"
    aria-label="Sidebar"
>
    <div class="relative flex flex-col h-full max-h-full">
        <!-- Header -->
        <header class="py-4 px-2 flex justify-between items-center gap-x-2">
            <a
                class="flex items-center gap-x-2 flex-none font-semibold text-xl text-black focus:outline-hidden focus:opacity-80 dark:text-white hs-overlay-minified:hidden"
                href="{{ route('dashboard') }}"
                aria-label="Brand"
            >
                <x-app-logo />
            </a>

            <div class="lg:hidden">
                <!-- Close Button -->
                <button
                    type="button"
                    class="flex justify-center items-center gap-x-3 size-6 bg-white border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:hover:text-neutral-200 dark:focus:text-neutral-200"
                    data-hs-overlay="#hs-sidebar-content-push-to-mini-sidebar"
                >
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                    <span class="sr-only">Close</span>
                </button>
                <!-- End Close Button -->
            </div>

            <div class="hidden lg:block">
                <!-- Minify Toggle Button -->
                <button
                    type="button"
                    class="flex justify-center items-center flex-none gap-x-3 size-9 text-sm text-gray-600 hover:bg-gray-100 rounded-full disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:hover:text-neutral-200 dark:focus:text-neutral-200"
                    aria-haspopup="dialog"
                    aria-expanded="false"
                    aria-controls="hs-sidebar-content-push-to-mini-sidebar"
                    aria-label="Minify navigation"
                    data-hs-overlay-minifier="#hs-sidebar-content-push-to-mini-sidebar"
                >
                    <svg class="hidden hs-overlay-minified:block shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M15 3v18"></path><path d="m8 9 3 3-3 3"></path>
                    </svg>
                    <svg class="hs-overlay-minified:hidden shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M15 3v18"></path><path d="m10 15-3-3 3-3"></path>
                    </svg>
                    <span class="sr-only">Navigation Toggle</span>
                </button>
                <!-- End Toggle Button -->
            </div>
        </header>
        <!-- End Header -->

        <!-- Body -->
        <nav class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <div class="px-2 w-full flex flex-col">
                <ul class="space-y-1">

                    @foreach($sections as $index => $section)
                        @if($index > 0)
                            <li class="my-2 border-t border-gray-200 dark:border-neutral-700"></li>
                        @endif

                        @if($section['label'])
                            <li class="px-2.5 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 hs-overlay-minified:hidden dark:text-neutral-500">
                                {{ $section['label'] }}
                            </li>
                        @endif

                        @foreach($section['items'] as $link)
                            <li>
                                <a
                                    href="{{ $link['url'] }}"
                                    wire:navigate
                                    @class([
                                        'min-h-[36px] w-full flex items-center gap-x-3.5 py-2 px-2.5 text-sm rounded-lg focus:outline-hidden',
                                        'bg-gray-100 text-accent font-semibold hover:bg-gray-100 focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700' => $link['current'],
                                        'text-gray-800 hover:bg-gray-100 focus:bg-gray-100 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700' => !$link['current'],
                                    ])
                                >
                                    
                                    @if(!empty($link['icon']))
                                        <x-dynamic-component :component="'heroicon-o-'.$link['icon']" class="size-4 shrink-0" />
                                    @endif

                                    <span class="hs-overlay-minified:hidden">
                                        {{ $link['name'] }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    @endforeach

                </ul>
            </div>
        </nav>
        <!-- End Body -->

        <!-- Footer: Profile + dropdown -->
        <div class="mt-auto p-2 border-t border-gray-200 dark:border-neutral-700">

            <div class="hs-dropdown [--strategy:absolute] [--auto-close:inside] relative w-full inline-flex" wire:ignore>
                <button id="hs-sidebar-account"
                        type="button"
                        class="w-full inline-flex shrink-0 items-center hs-overlay-minified:justify-center gap-x-2 p-2 text-start text-sm text-gray-800 rounded-md hover:bg-gray-100
                       focus:outline-hidden focus:bg-gray-100
                       dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700"
                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">

                    <div class="shrink-0 size-8 rounded-full bg-gray-200 text-gray-800 flex items-center justify-center font-semibold
                      dark:bg-neutral-700 dark:text-neutral-100">
                        {{ $user->initials() ?? strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </div>

                    <div class="min-w-0 flex-1 hs-overlay-minified:hidden">
                        <div class="truncate font-medium">{{ $user->name }}</div>
                        <div class="truncate text-xs text-gray-500 dark:text-neutral-400">{{ $user->email }}</div>
                    </div>

                    <svg class="shrink-0 size-3.5 ms-auto hs-overlay-minified:hidden" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m7 15 5 5 5-5"></path><path d="m7 9 5-5 5 5"></path>
                    </svg>
                </button>

                <!-- Dropdown -->
                <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 w-60 transition-[opacity,margin] duration opacity-0 hidden z-20
                    bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-neutral-900 dark:border-neutral-700"
                     role="menu" aria-orientation="vertical" aria-labelledby="hs-sidebar-account">
                    <div class="p-1">
                        <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100
                      focus:outline-hidden focus:bg-gray-100
                      dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                           href="{{ route('settings.profile') }}">
                            <x-dynamic-component component="heroicon-o-cog-6-tooth" class="size-4 shrink-0" />
                            {{ __('Settings') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 text-start
                             focus:outline-hidden focus:bg-gray-100
                             dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
                                <x-dynamic-component component="heroicon-o-arrow-right-start-on-rectangle" class="size-4 shrink-0" />
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </div>
                </div>
                <!-- End Dropdown -->
            </div>

        </div>
        <!-- End Footer -->

    </div>
</div>
<script>
    // El sidebar (con este bloque) se vuelve a insertar en cada navegación
    // Livewire (wire:navigate), y el navegador re-ejecuta el <script> tal
    // cual sin limpiar el scope global del anterior -- sin este IIFE,
    // "let sidebarObserverStarted" tronaba con "already declared" al volver
    // a correr el mismo bloque en la página siguiente.
    (function () {
    function initPreline() {
        if (window.HSStaticMethods && typeof window.HSStaticMethods.autoInit === 'function') {
            window.HSStaticMethods.autoInit();
        }

        if (window.HSDropdown && typeof window.HSDropdown.autoInit === 'function') window.HSDropdown.autoInit();
        if (window.HSOverlay && typeof window.HSOverlay.autoInit === 'function') window.HSOverlay.autoInit();
        if (window.HSAccordion && typeof window.HSAccordion.autoInit === 'function') window.HSAccordion.autoInit();
    }

    /**
     * El bloque al inicio del <body> ya le puso la clase a <body> antes del
     * primer pintado (evita el parpadeo). Acá se sincroniza el propio div
     * del sidebar (Preline se fija en ESA clase, no en la de <body>, para
     * saber si el próximo click debe minimizar o restaurar).
     * @returns {void}
     */
    function applyStoredSidebarState() {
        const sidebar = document.getElementById('hs-sidebar-content-push-to-mini-sidebar');
        if (! sidebar) return;

        const shouldBeMinified = localStorage.getItem('sidebarMinified') === 'true';
        sidebar.classList.toggle('minified', shouldBeMinified);
        document.body.classList.toggle('hs-overlay-minified', shouldBeMinified);
    }

    let sidebarObserverStarted = false;

    /**
     * Deja un observer que guarda en localStorage cualquier cambio futuro
     * del estado minimizado/expandido del sidebar (clicks en el botón de
     * minimizar), para poder aplicarlo de nuevo en la próxima carga de
     * página (ver applyStoredSidebarState()).
     * @returns {void}
     */
    function watchSidebarState() {
        if (sidebarObserverStarted) return;
        sidebarObserverStarted = true;

        new MutationObserver(() => {
            localStorage.setItem('sidebarMinified', document.body.classList.contains('hs-overlay-minified') ? 'true' : 'false');
        }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPreline();
        applyStoredSidebarState();
        watchSidebarState();
    });

    document.addEventListener('livewire:navigated', () => {
        initPreline();
        applyStoredSidebarState();

        try {
            if (window.HSOverlay && typeof window.HSOverlay.close === 'function') {
                window.HSOverlay.close('#hs-sidebar-content-push-to-mini-sidebar');
            }
        } catch (e) {}
    });
    })();
</script>
<!-- End Sidebar -->

<!-- Main content wrapper (deja espacio para el sidebar en desktop) -->
<div class="transition-all duration-300 lg:ps-64 hs-overlay-minified:lg:ps-13">
    {{ $slot }}
</div>

@fluxScripts
</body>
</html>
