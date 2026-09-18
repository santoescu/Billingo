@php
    $appearance = session('appearance', 'light');
    $isDark = $appearance === 'dark';

    // El panel del formulario arranca cerrado (ancho 0) en desktop -- el
    // usuario lo abre con la flecha. En /register eso significa que, si
    // venía del login con el panel ya abierto y le da "Inscribirme", la
    // página nueva vuelve a nacer cerrada (esta clase sale directo del
    // HTML server-side, no depende de que ningún JS corra a tiempo) --
    // para esa ruta arranca abierto desde el primer render.
    $authShellOpenByDefault = request()->routeIs('register');

    $moduleDescriptions = [
        'invoicing' => __('DIAN-certified electronic invoicing, with online validation and automatic delivery to your clients.'),
        'receiving' => __('Centralized reception of your suppliers\' electronic documents, with full history and traceability.'),
        'payroll' => __('Electronic payroll settlement and issuance, compliant with current regulations.'),
        'pos' => __('An agile point of sale for physical businesses, with cash register, warehouse and multiple payment methods control.'),
        'cotizaciones' => __('Professional quotations your clients can turn into an invoice or sale with one click.'),
    ];

    $moduleIcons = [
        'invoicing' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><path d="M14 2v6h6"></path><path d="M9 15h6"></path><path d="M9 11h1"></path>',
        'receiving' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"></path><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>',
        'payroll' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'pos' => '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>',
        'cotizaciones' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><path d="M14 2v6h6"></path><circle cx="11.5" cy="14.5" r="2.5"></circle><path d="m13.3 16.3 1.7 1.7"></path>',
    ];

    $moduleColors = [
        'invoicing' => 'bg-amber-500/15 text-amber-400',
        'pos' => 'bg-blue-500/15 text-blue-400',
        'cotizaciones' => 'bg-emerald-500/15 text-emerald-400',
        'receiving' => 'bg-purple-500/15 text-purple-400',
        'payroll' => 'bg-pink-500/15 text-pink-400',
    ];

    $modules = collect(config('modules'))
        ->only(array_keys($moduleDescriptions))
        ->map(fn ($module, $key) => [
            'name' => $module['name'],
            'description' => $moduleDescriptions[$key],
            'icon' => $moduleIcons[$key],
            'color' => $moduleColors[$key],
        ]);

    $comparisons = [
        [
            'without' => __('Invoicing, POS and quotations in separate tools that don\'t talk to each other.'),
            'with' => __('Everything in one place, sharing the same clients, products and stock.'),
        ],
        [
            'without' => __('Finding out a document was rejected by the DIAN only after sending it.'),
            'with' => __('Validation before it ever reaches the DIAN, so you catch mistakes first.'),
        ],
        [
            'without' => __('Retyping a quotation\'s lines by hand into an invoice or a sale.'),
            'with' => __('Convert a quotation into a sale or an invoice with one click.'),
        ],
        [
            'without' => __('No real visibility into what\'s in stock at each warehouse.'),
            'with' => __('Stock and cash register updated live as you sell.'),
        ],
    ];

    $aboutPlatform = [
        __('Invoicing, POS, quotations, receiving and payroll are all part of the same platform, sharing the same clients, products and stock. Not separate products bolted together.'),
        __('Every electronic document follows the UBL format the DIAN requires, and gets validated before it\'s sent, not after.'),
        __('You only turn on the modules your business actually needs, and can add more later without migrating anything.'),
    ];

    $aboutTeam = [
        __('We built Billingo because we ran into the same problem ourselves: juggling separate tools to invoice, sell and keep up with the DIAN.'),
        __('We\'re a small team, so every conversation with a client actually reaches the people who build the product.'),
        __('We keep adding modules based on what businesses using Billingo actually ask for, not a fixed roadmap.'),
    ];

    // Solo tenemos una recomendación real por ahora (la nuestra) -- se deja
    // como array para poder ir agregando reseñas de clientes reales más
    // adelante sin tocar el carrusel.
    $recommendations = [
        [
            'name' => __('The Billingo team'),
            'role' => __('We use it every day'),
            'quote' => __('We use Billingo ourselves to invoice, sell and quote every day. If it didn\'t work for our own business, we wouldn\'t be asking you to trust it with yours.'),
        ],
    ];

    $plans = [
        [
            'name' => __('Basic'),
            'price' => __('From $300,000/year'),
            'tagline' => __('To start invoicing'),
            'features' => [__('Unlimited users'), __('1 module of your choice'), __('Up to 400 documents/year'), __('Email support')],
        ],
        [
            'name' => __('Pro'),
            'price' => __('From $780,000/year'),
            'tagline' => __('Most popular'),
            'features' => [__('Unlimited users'), __('2 modules of your choice'), __('Up to 2,000 documents/year'), __('Priority support')],
            'highlighted' => true,
        ],
        [
            'name' => __('Enterprise'),
            'price' => __('Custom quote'),
            'tagline' => __('High volume and advanced needs'),
            'features' => [__('Unlimited users'), __('More than 3 modules of your choice'), __('Unlimited documents/year'), __('Dedicated support')],
        ],
    ];

    // Combos armados alrededor de los casos de uso más comunes (ver
    // .agents/product-marketing.md) -- salen más baratos que armar la misma
    // combinación a mano en la calculadora de abajo, como incentivo para
    // elegir el combo ya armado en vez de "Pro" genérico cuando el negocio
    // encaja en uno de estos dos patrones.
    $comboPlans = [
        [
            'name' => __('Storefront'),
            'price' => __('$650,000/year'),
            'tagline' => __('POS + Invoicing'),
            'features' => [__('Unlimited users'), __('Point of sale + Invoicing'), __('Up to 1,200 documents/year'), __('Email support')],
        ],
        [
            'name' => __('Quote to Sale'),
            'price' => __('$485,000/year'),
            'tagline' => __('Quotations + Invoicing'),
            'features' => [__('Unlimited users'), __('Quotations + Invoicing'), __('Up to 1,200 documents/year'), __('Email support')],
        ],
        [
            'name' => __('Full Ops'),
            'price' => __('$1,785,000/year'),
            'tagline' => __('All 5 modules'),
            'features' => [__('Unlimited users'), __('All 5 modules included'), __('Up to 6,000 documents/year'), __('Priority support')],
        ],
    ];

    // Calculadora "arma tu propio combo" -- mismos números que los planes de
    // arriba, para que el resultado no contradiga lo que ya se cotiza a
    // mano. MODULE_BASE_PRICES es el costo de tener acceso a cada módulo
    // (independiente del volumen) -- "cotizaciones" vale una fracción del
    // resto (55,000 en vez de 220,000) porque el módulo pesa menos que
    // facturación/POS/nómina/recepción; VOLUME_ANCHORS son puntos
    // [documentos, cargo por volumen] -- el cargo real de cualquier
    // cantidad de documentos que el usuario deslice en el rango se
    // interpola en línea recta entre los dos puntos que lo rodean, así el
    // precio va cambiando suave mientras arrastra en vez de saltar entre
    // escalones fijos. Los puntos están puestos a propósito para que el
    // costo marginal por documento baje entre tramo y tramo (descuento por
    // volumen), aunque el total siempre suba con más documentos.
    $calculatorDefaultModulePrice = 220000;
    $calculatorModuleBasePrices = [
        'cotizaciones' => 55000,
    ];
    $calculatorVolumeMin = 100;
    $calculatorVolumeMax = 50000;
    $calculatorVolumeStep = 100;
    // Extendido más allá de 6,000 documentos (antes el tope del slider) para
    // cubrir negocios de mayor volumen -- misma lógica de antes, el costo
    // marginal por documento sigue bajando entre tramo y tramo, sin que el
    // total deje de subir.
    $calculatorVolumeAnchors = [
        [100, 90000],
        [500, 200000],
        [1200, 280000],
        [2000, 340000],
        [3500, 460000],
        [6000, 620000],
        [10000, 820000],
        [20000, 1150000],
        [35000, 1500000],
        [50000, 1800000],
    ];
    // Curva de precio TOTAL (no un cargo que se suma a un moduleBase) para
    // cuando el único módulo elegido es "invoicing" puro -- calibrada contra
    // los planes anuales de Factus (proveedor de la API de facturación
    // electrónica DIAN, sin POS/cotizaciones/nómina/nada más encima) a un
    // margen de ~1.6x sobre su precio, más bajo que el margen implícito de
    // ~2.1x que queda para el resto de módulos/combinaciones (que sí
    // incluyen plataforma completa, no solo la conexión DIAN). Este
    // descuento aplica SOLO cuando "invoicing" es el único módulo
    // seleccionado -- cualquier otra combinación sigue usando
    // MODULE_BASE_PRICES + VOLUME_ANCHORS sin cambios.
    $calculatorInvoicingOnlyVolumeAnchors = [
        [100, 250000],
        [150, 270000],
        [400, 300000],
        [1600, 350000],
        [2500, 420000],
        [5000, 460000],
        [10000, 620000],
        [15000, 700000],
        [20000, 780000],
        [35000, 1300000],
        [50000, 1750000],
    ];
    // Los combos ya armados (Storefront/Quote to Sale/Full Ops) no cambian
    // el cargo por volumen (sigue interpolando igual que cualquier otra
    // combinación) -- lo que cambia es "moduleBase", que REEMPLAZA a
    // MODULE_BASE_PRICE * cantidad de módulos para ese conjunto exacto de
    // módulos, aplicado en TODO el rango del slider, no solo en un punto.
    // Antes esto se hacía con un precio fijo solo en el volumen exacto para
    // el que el combo fue calibrado, lo que generaba un hueco raro en la
    // curva (el precio bajaba de golpe justo en ese punto y volvía a subir
    // un documento más allá). Con el ajuste aplicado siempre, la curva
    // queda continua y el combo se reconoce sin importar qué volumen se
    // elija dentro del rango. Storefront/Quote to Sale usan un moduleBase
    // 70,000 más barato que la suma genérica de sus módulos porque son
    // combinaciones comunes que Billingo quiere incentivar; Full Ops usa
    // uno 230,000 más caro que la suma genérica porque incluye soporte
    // prioritario. La suma genérica de cada combo (con "cotizaciones" a
    // 55,000): Storefront 220,000+220,000=440,000; Quote to Sale
    // 55,000+220,000=275,000; Full Ops 220,000*4+55,000=935,000.
    $calculatorNamedCombos = [
        ['modules' => ['pos', 'invoicing'], 'moduleBase' => 370000, 'name' => __('Storefront')],
        ['modules' => ['cotizaciones', 'invoicing'], 'moduleBase' => 205000, 'name' => __('Quote to Sale')],
        ['modules' => ['invoicing', 'receiving', 'payroll', 'pos', 'cotizaciones'], 'moduleBase' => 1165000, 'name' => __('Full Ops')],
    ];

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-x-hidden bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        {{-- Una sola vista (nada duplicado) -- las dos columnas viven en el
             mismo grid de siempre, pero en vez de anchos en porcentaje
             (flex-basis/width, que terminaban desbordando la página por el
             min-width:auto por defecto de los ítems) se anima
             "grid-template-columns" con fracciones "fr": como siempre suman
             el total disponible sin importar el contenido, no hay forma de
             que se desborden. Arranca en "1fr 0fr" (panel oscuro ocupa
             todo, formulario en 0) y el botón lo cambia a "3fr 2fr" (la
             proporción original), animado. JS plano, sin Alpine, para no
             depender de que ya esté hidratado al momento del clic.

             En mobile no hay espacio para deslizar dos columnas lado a
             lado -- ahí, en vez de animar el grid, se alterna cuál de los
             dos paneles está presente (display:none/flex vía JS) con
             "toggleMobilePanel()": arranca mostrando el panel de info
             (módulos/planes/nosotros) como landing, con un botón "Iniciar
             sesión" que revela el formulario, y el formulario tiene un
             botón para volver. Las clases "lg:flex" de cada panel siempre
             ganan sobre lo que JS le haya puesto (hidden/flex sin prefijo)
             a partir de "lg" -- por eso el estado mobile no puede filtrarse
             a desktop aunque no se resetee al cambiar de tamaño. --}}
        <div id="auth-shell" class="relative grid h-dvh grid-cols-[minmax(0,1fr)] items-stretch justify-center lg:max-w-none {{ $authShellOpenByDefault ? 'lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]' : 'lg:grid-cols-[minmax(0,1fr)_minmax(0,0fr)]' }} lg:justify-normal lg:transition-[grid-template-columns] lg:duration-500 lg:ease-in-out">
            <div id="auth-promo-panel" class="bg-muted relative flex h-full min-w-0 flex-col overflow-x-hidden overflow-y-auto bg-neutral-900 p-6 text-white sm:p-10 lg:flex dark:border-r dark:border-neutral-800 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-neutral-700 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-neutral-500">
                <button type="button" id="auth-login-toggle-btn" aria-label="{{ __('Log in') }}" title="{{ __('Log in') }}"
                    onclick="window.toggleAuthLoginPanel()"
                    class="absolute end-6 top-6 z-30 hidden size-10 items-center justify-center rounded-full text-neutral-300 hover:bg-white/10 hover:text-white focus:outline-hidden lg:flex">
                    <svg id="auth-login-toggle-icon" class="size-5 shrink-0 transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="{{ $authShellOpenByDefault ? 'transform: rotate(180deg)' : '' }}"><path d="m15 18-6-6 6-6"></path></svg>
                </button>

                <div class="relative z-20">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('home') }}" class="flex items-center text-lg font-medium" wire:navigate>
                            <span class="flex h-10 w-10 items-center justify-center rounded-md">
                                <x-app-logo-icon class="mr-2 h-7 fill-current text-white" />
                            </span>
                            {{ config('app.name', 'Laravel') }}
                        </a>

                        <button type="button" onclick="window.toggleMobilePanel()" class="shrink-0 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-neutral-900 hover:bg-neutral-100 lg:hidden">
                            {{ __('Log in') }}
                        </button>
                    </div>

                    <p class="mt-8 max-w-xl text-2xl font-semibold leading-snug">{{ __('Electronic invoicing, point of sale and payroll, all in one place.') }}</p>
                    <p class="mt-3 max-w-xl text-sm text-neutral-300">{{ __('A platform built for Colombian businesses: issue electronic documents valid before the DIAN, sell over the counter and quote, without switching systems.') }}</p>

                    <div class="mt-10 border-b border-white/10">
                        {{-- overflow-x-auto + shrink-0 en cada botón: con 6
                             pestañas ya no entran todas en una pantalla
                             angosta -- sin esto, "Contáctanos" quedaba
                             cortada e invisible por el overflow-x-hidden
                             del panel (nunca hacía scroll, solo se veía
                             recortada). Scrollbar oculta porque es un tab
                             bar, no un área de contenido con scroll obvio. --}}
                        <nav class="flex gap-4 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <button type="button" id="auth-promo-tab-modules-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-white py-2 text-sm font-medium whitespace-nowrap text-white" onclick="window.showAuthPromoTab('modules')">
                                {{ __('Modules') }}
                            </button>
                            <button type="button" id="auth-promo-tab-comparison-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white" onclick="window.showAuthPromoTab('comparison')">
                                {{ __('Why Billingo') }}
                            </button>
                            <button type="button" id="auth-promo-tab-plans-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white" onclick="window.showAuthPromoTab('plans')">
                                {{ __('Plans and pricing') }}
                            </button>
                            <button type="button" id="auth-promo-tab-about-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white" onclick="window.showAuthPromoTab('about')">
                                {{ __('About us') }}
                            </button>
                            <button type="button" id="auth-promo-tab-recommendation-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white" onclick="window.showAuthPromoTab('recommendation')">
                                {{ __('We recommend it') }}
                            </button>
                            <button type="button" id="auth-promo-tab-contact-btn" class="auth-promo-tab-btn shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white" onclick="window.showAuthPromoTab('contact')">
                                {{ __('Contact us') }}
                            </button>
                            {{-- Igual que "Documentation" más abajo: no es una
                                 pestaña de panel (no tiene showAuthPromoTab ni
                                 div asociado) -- abre el blog público en una
                                 pestaña nueva del navegador. --}}
                            <a href="{{ route('blog.index') }}" target="_blank" rel="noopener" class="shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white">
                                {{ __('Blog') }}
                            </a>
                            {{-- No es una pestaña de panel como las demás (no
                                 tiene showAuthPromoTab ni div asociado) --
                                 abre la documentación pública de la API en
                                 una pestaña nueva del navegador. --}}
                            <a href="{{ route('api-docs.index') }}" target="_blank" rel="noopener" class="shrink-0 border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-neutral-400 hover:text-white">
                                {{ __('Documentation') }}
                            </a>
                        </nav>
                    </div>

                    <div id="auth-promo-tab-modules" class="auth-promo-tab mt-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($modules as $module)
                                <div class="flex items-start gap-4 rounded-lg border border-white/10 bg-white/5 p-5">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-md {{ $module['color'] }}">
                                        <svg class="size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $module['icon'] !!}</svg>
                                    </span>
                                    <div>
                                        <p class="text-base font-medium text-white">{{ $module['name'] }}</p>
                                        <p class="mt-1 text-sm text-neutral-400">{{ $module['description'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="auth-promo-tab-comparison" class="auth-promo-tab mt-6 hidden">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($comparisons as $comparison)
                                <div class="flex items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-5">
                                    <svg class="mt-0.5 size-5 shrink-0 text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                                    <p class="text-sm text-neutral-400 line-through decoration-neutral-600">{{ $comparison['without'] }}</p>
                                </div>
                                <div class="flex items-start gap-3 rounded-lg border border-accent/30 bg-accent/10 p-5">
                                    <svg class="mt-0.5 size-5 shrink-0 text-accent" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                    <p class="text-sm text-neutral-200">{{ $comparison['with'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="auth-promo-tab-plans" class="auth-promo-tab mt-6 hidden">
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($plans as $plan)
                                <div class="relative rounded-lg border p-4 {{ ($plan['highlighted'] ?? false) ? 'border-accent bg-accent/10' : 'border-white/10 bg-white/5' }}">
                                    @if ($plan['highlighted'] ?? false)
                                        <span class="absolute -top-2.5 right-3 rounded-full bg-accent px-2 py-0.5 text-[10px] font-semibold text-white">{{ __('Popular') }}</span>
                                    @endif
                                    <p class="font-semibold text-white">{{ $plan['name'] }}</p>
                                    <p class="text-xs text-neutral-400">{{ $plan['tagline'] }}</p>
                                    <p class="mt-2 text-sm font-medium text-white">{{ $plan['price'] }}</p>
                                    <ul class="mt-3 space-y-1.5">
                                        @foreach ($plan['features'] as $feature)
                                            <li class="flex items-start gap-1.5 text-xs text-neutral-300">
                                                <svg class="mt-0.5 size-3.5 shrink-0 text-accent" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                {{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Popular combos') }}</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            @foreach ($comboPlans as $plan)
                                <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                    <p class="font-semibold text-white">{{ $plan['name'] }}</p>
                                    <p class="text-xs text-neutral-400">{{ $plan['tagline'] }}</p>
                                    <p class="mt-2 text-sm font-medium text-white">{{ $plan['price'] }}</p>
                                    <ul class="mt-3 space-y-1.5">
                                        @foreach ($plan['features'] as $feature)
                                            <li class="flex items-start gap-1.5 text-xs text-neutral-300">
                                                <svg class="mt-0.5 size-3.5 shrink-0 text-accent" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                {{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8 rounded-lg border border-white/10 bg-white/5 p-5">
                            <p class="font-semibold text-white">{{ __('Build your own combo') }}</p>
                            <p class="mt-1 text-sm text-neutral-400">{{ __('Not sure which plan fits? Pick your modules and expected volume and get an instant estimate.') }}</p>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach ($modules as $key => $module)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-neutral-200 has-checked:border-accent has-checked:bg-accent/10">
                                        <input type="checkbox" class="js-calculator-module shrink-0 size-4 rounded-sm border-white/20 bg-transparent accent-accent focus:ring-accent" value="{{ $key }}">
                                        {{ $module['name'] }}
                                    </label>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label for="calculator-document-volume" class="text-sm font-medium text-neutral-200">{{ __('Expected documents per year') }}</label>
                                    <span id="calculator-document-volume-value" class="text-sm font-semibold text-white"></span>
                                </div>
                                <input type="range" id="calculator-document-volume" class="js-calculator-volume h-2 w-full cursor-pointer appearance-none rounded-lg bg-white/10 accent-accent"
                                    min="{{ $calculatorVolumeMin }}" max="{{ $calculatorVolumeMax }}" step="{{ $calculatorVolumeStep }}" value="{{ $calculatorVolumeAnchors[1][0] }}">
                                <div class="mt-1 flex justify-between text-xs text-neutral-500">
                                    <span>{{ number_format($calculatorVolumeMin) }}</span>
                                    <span>{{ number_format($calculatorVolumeMax) }}+</span>
                                </div>
                            </div>

                            <div id="calculator-result" class="mt-4 rounded-lg border border-accent/30 bg-accent/10 p-4 text-sm text-neutral-200">
                                {{ __('Select at least one module to see an estimate.') }}
                            </div>

                            <button type="button" id="calculator-send-quote" class="mt-4 hidden rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-accent/90">
                                {{ __('Send me this quote') }}
                            </button>
                        </div>
                    </div>

                    <div id="auth-promo-tab-about" class="auth-promo-tab mt-6 hidden">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('The platform') }}</h3>
                            <div class="mt-3 space-y-3">
                                @foreach ($aboutPlatform as $point)
                                    <div class="flex items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-5">
                                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-accent/15 text-xs font-semibold text-accent">{{ $loop->iteration }}</span>
                                        <p class="text-sm text-neutral-300">{{ $point }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-8">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('How we work') }}</h3>
                            <div class="mt-3 space-y-3">
                                @foreach ($aboutTeam as $point)
                                    <div class="flex items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-5">
                                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-accent/15 text-xs font-semibold text-accent">{{ $loop->iteration }}</span>
                                        <p class="text-sm text-neutral-300">{{ $point }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Carrusel horizontal en loop (misma técnica que un
                         marquee de Preline): el track se duplica una vez y
                         se anima con "translateX(-50%)" -- como las dos
                         mitades son idénticas, al llegar a -50% el corte es
                         invisible y arranca de nuevo. Con una sola reseña
                         real (el caso de ahora) no tiene sentido duplicarla
                         ni animarla -- se ve fija, sin repetirse -- el
                         carrusel se activa solo cuando $recommendations
                         tenga 2 o más. La segunda copia lleva aria-hidden
                         porque es puramente visual, el contenido real ya lo
                         lee el lector de pantalla en la primera. --}}
                    <div id="auth-promo-tab-recommendation" class="auth-promo-tab mt-6 hidden">
                        @php $recommendationCopies = count($recommendations) > 1 ? 2 : 1; @endphp
                        <div @class([
                            'relative overflow-hidden' => $recommendationCopies > 1,
                            'before:pointer-events-none before:absolute before:inset-y-0 before:start-0 before:z-10 before:w-10 before:bg-linear-to-r before:from-neutral-900 before:to-transparent after:pointer-events-none after:absolute after:inset-y-0 after:end-0 after:z-10 after:w-10 after:bg-linear-to-l after:from-neutral-900 after:to-transparent' => $recommendationCopies > 1,
                        ])>
                            <div @class([
                                'flex w-max gap-4' => true,
                                '[animation:auth-marquee-x_30s_linear_infinite] hover:[animation-play-state:paused]' => $recommendationCopies > 1,
                            ])>
                                @for ($copy = 0; $copy < $recommendationCopies; $copy++)
                                    <div class="flex shrink-0 gap-4" @if ($copy === 1) aria-hidden="true" @endif>
                                        @foreach ($recommendations as $recommendation)
                                            <figure class="w-72 shrink-0 rounded-lg border border-white/10 bg-white/5 p-5">
                                                <div class="flex items-center gap-3">
                                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent/15 text-sm font-semibold text-accent">{{ Str::substr($recommendation['name'], 0, 1) }}</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-white">{{ $recommendation['name'] }}</p>
                                                        <p class="truncate text-xs text-neutral-400">{{ $recommendation['role'] }}</p>
                                                    </div>
                                                </div>
                                                <blockquote class="mt-3 text-sm text-neutral-300">{{ $recommendation['quote'] }}</blockquote>
                                            </figure>
                                        @endforeach
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div id="auth-promo-tab-contact" class="auth-promo-tab mt-6 hidden">
                        <p class="max-w-md text-sm text-neutral-300">{{ __('Tell us a bit about your business and we\'ll get back to you.') }}</p>

                        <form action="{{ route('public.contact.store') }}" method="POST" class="mt-4 max-w-md space-y-4">
                            @csrf

                            @if ($errors->any())
                                <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div>
                                <label for="contact_name" class="mb-1.5 block text-sm font-medium text-neutral-200">{{ __('Company name') }}</label>
                                <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name') }}" required
                                    class="block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-accent focus:outline-hidden focus:ring-1 focus:ring-accent">
                            </div>

                            <div>
                                <label for="contact_email" class="mb-1.5 block text-sm font-medium text-neutral-200">{{ __('Email address') }}</label>
                                <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email') }}" required
                                    class="block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-accent focus:outline-hidden focus:ring-1 focus:ring-accent">
                            </div>

                            <div>
                                <label for="contact_phone" class="mb-1.5 block text-sm font-medium text-neutral-200">{{ __('Phone (optional)') }}</label>
                                <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone') }}"
                                    class="block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-accent focus:outline-hidden focus:ring-1 focus:ring-accent">
                            </div>

                            <div>
                                <label for="contact_body" class="mb-1.5 block text-sm font-medium text-neutral-200">{{ __('Message') }}</label>
                                <textarea name="body" id="contact_body" rows="3" required
                                    class="block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-accent focus:outline-hidden focus:ring-1 focus:ring-accent">{{ old('body') }}</textarea>
                            </div>

                            <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-accent/90">
                                {{ __('Send message') }}
                            </button>
                        </form>

                        <p class="mt-4 max-w-md text-sm text-neutral-400">
                            {{ __('Already know what you need?') }}
                            <a href="{{ route('register') }}" class="font-semibold text-white underline underline-offset-2 hover:text-accent" wire:navigate>{{ __('Sign up and create your company') }}</a>
                        </p>
                    </div>
                </div>
            </div>
            <div id="auth-login-panel" class="relative hidden h-full w-full min-w-0 items-center overflow-hidden px-8 sm:px-0 lg:flex lg:p-8">
                <button type="button" onclick="window.toggleMobilePanel()" class="absolute start-4 top-4 z-20 inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-accent lg:hidden dark:text-neutral-400">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                    {{ __('Back') }}
                </button>

                <div class="mx-auto flex w-full max-w-[350px] flex-col justify-center space-y-6">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        <script>
        {{-- wire:navigate no recarga la página completa -- vuelve a
             ejecutar este <script> cada vez que se navega aquí, y un
             "const"/"let" de nivel superior explota la segunda vez
             ("ya declarado"). Todo va dentro de un IIFE para que quede
             aislado en su propio scope, igual que el resto de la app. --}}
        (function () {
            const AUTH_SHELL_CLOSED = 'lg:grid-cols-[minmax(0,1fr)_minmax(0,0fr)]';
            const AUTH_SHELL_OPEN = 'lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]';
            const AUTH_PROMO_TABS = ['modules', 'comparison', 'plans', 'about', 'recommendation', 'contact'];

            /**
             * Pestañas del panel oscuro (Módulos / Por qué Billingo /
             * Planes) para no tener que hacer scroll por todo de una vez.
             * @param {string} tab
             * @returns {void}
             */
            window.showAuthPromoTab = function (tab) {
                AUTH_PROMO_TABS.forEach((name) => {
                    const isActive = name === tab;
                    document.getElementById(`auth-promo-tab-${name}`).classList.toggle('hidden', !isActive);

                    const btn = document.getElementById(`auth-promo-tab-${name}-btn`);
                    btn.classList.toggle('border-white', isActive);
                    btn.classList.toggle('text-white', isActive);
                    btn.classList.toggle('border-transparent', !isActive);
                    btn.classList.toggle('text-neutral-400', !isActive);
                });
            };

            /**
             * Un solo botón (la flecha) abre y cierra el panel de login --
             * la flecha gira 180° para indicar la dirección en la que
             * deslizaría al volver a hacerle clic.
             * @returns {void}
             */
            window.toggleAuthLoginPanel = function () {
                const shell = document.getElementById('auth-shell');
                const icon = document.getElementById('auth-login-toggle-icon');
                const isOpen = shell.classList.contains(AUTH_SHELL_OPEN);

                shell.classList.replace(isOpen ? AUTH_SHELL_OPEN : AUTH_SHELL_CLOSED, isOpen ? AUTH_SHELL_CLOSED : AUTH_SHELL_OPEN);
                icon.style.transform = isOpen ? '' : 'rotate(180deg)';
            };

            /**
             * Alterna qué panel se ve en mobile (no hay espacio para los
             * dos lado a lado): arranca mostrando el de info, el botón
             * "Iniciar sesión" revela el formulario, "Volver" regresa. Las
             * clases "lg:flex" de cada panel siempre ganan sobre lo que
             * esto le ponga a partir de "lg" (no hace falta resetear nada
             * al agrandar la ventana).
             * @returns {void}
             */
            window.toggleMobilePanel = function () {
                const login = document.getElementById('auth-login-panel');
                setMobilePanel(! login.classList.contains('flex'));
            };

            /**
             * Fija (no alterna) cuál panel se ve en mobile -- a diferencia
             * de toggleMobilePanel(), esta se puede llamar varias veces
             * seguidas sin quedar en el estado contrario por error (pasa
             * en /register, donde tanto el script en línea como el listener
             * de "livewire:navigated" de más abajo pueden terminar
             * corriendo los dos para la misma navegación).
             * @param {boolean} showLogin
             * @returns {void}
             */
            function setMobilePanel(showLogin) {
                const promo = document.getElementById('auth-promo-panel');
                const login = document.getElementById('auth-login-panel');
                if (! promo || ! login) return;

                promo.classList.toggle('hidden', showLogin);
                promo.classList.toggle('flex', ! showLogin);
                login.classList.toggle('hidden', ! showLogin);
                login.classList.toggle('flex', showLogin);

                if (showLogin) {
                    promo.scrollTop = 0;
                }
            }

            /**
             * En la primera pintura, algunos navegadores calculan mal el
             * ancho de las columnas "fr" del grid (queda una franja blanca
             * hasta que algo fuerza un recálculo -- hacer zoom y volver a
             * 100% lo arregla a mano). Forzarlo una vez apenas carga evita
             * que el usuario tenga que hacerlo.
             * @returns {void}
             */
            function forceAuthShellReflow() {
                const shell = document.getElementById('auth-shell');
                if (!shell) return;

                shell.style.display = 'none';
                void shell.offsetHeight;
                shell.style.display = '';
            }

            requestAnimationFrame(forceAuthShellReflow);

            /**
             * Decide con qué pestaña/panel debe arrancar la página --
             * corre tanto en línea (primera carga real) como otra vez en
             * "livewire:navigated" (wire:navigate no recarga la página,
             * pero re-ejecuta este <script> igual; el listener de acá es
             * un respaldo por si esa re-ejecución no alcanza a correr
             * antes de que el usuario ya esté viendo la página -- usa
             * setMobilePanel(), no toggleMobilePanel(), justamente para no
             * quedar en el estado contrario si las dos formas terminan
             * corriendo para la misma navegación).
             * @returns {void}
             */
            function initAuthPanelState() {
                @if ($errors->any())
                    {{-- Si el formulario "Contáctanos" volvió con errores, hay
                         que dejar esa pestaña abierta (con los datos que ya
                         había escrito) en vez de la de "Módulos" por defecto. --}}
                    window.showAuthPromoTab('contact');
                @elseif (session('status') || request()->routeIs('register'))
                    {{-- El mensaje de éxito (u otro "status" de sesión, como el
                         de restablecer contraseña) vive en el panel del
                         formulario -- en mobile hay que revelarlo a mano, si no
                         queda escondido detrás del panel de info. Lo mismo para
                         "/register": si el usuario venía del login con el panel
                         de info abierto y le da "Inscribirme" (wire:navigate),
                         sin esto aterrizaba de nuevo en el panel de info en vez
                         de seguir directo al formulario que acababa de pedir. --}}
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        setMobilePanel(true);
                    }
                @endif
            }

            const CALCULATOR_DEFAULT_MODULE_PRICE = @json($calculatorDefaultModulePrice);
            const CALCULATOR_MODULE_BASE_PRICES = @json($calculatorModuleBasePrices);
            const CALCULATOR_VOLUME_ANCHORS = @json($calculatorVolumeAnchors);
            const CALCULATOR_INVOICING_ONLY_VOLUME_ANCHORS = @json($calculatorInvoicingOnlyVolumeAnchors);
            const CALCULATOR_VOLUME_MAX = @json($calculatorVolumeMax);
            const CALCULATOR_NAMED_COMBOS = @json($calculatorNamedCombos);
            const CALCULATOR_MODULE_NAMES = @json($modules->map(fn ($module) => $module['name'])->all());

            /**
             * Interpola en línea recta entre los dos puntos [documentos,
             * precio] de "anchors" que rodean "documents" -- así el precio
             * cambia suave mientras se arrastra el slider en vez de saltar
             * entre escalones fijos. Por fuera del rango de los anchors se
             * usa el punto extremo más cercano tal cual (sin extrapolar).
             * @param {Array<[number, number]>} anchors
             * @param {number} documents
             * @returns {number}
             */
            function interpolate(anchors, documents) {
                if (documents <= anchors[0][0]) return anchors[0][1];
                if (documents >= anchors[anchors.length - 1][0]) return anchors[anchors.length - 1][1];

                for (let i = 0; i < anchors.length - 1; i++) {
                    const [fromDocs, fromCharge] = anchors[i];
                    const [toDocs, toCharge] = anchors[i + 1];

                    if (documents >= fromDocs && documents <= toDocs) {
                        const progress = (documents - fromDocs) / (toDocs - fromDocs);
                        return Math.round(fromCharge + (toCharge - fromCharge) * progress);
                    }
                }

                return anchors[anchors.length - 1][1];
            }

            /**
             * Calculadora "arma tu propio combo" (pestaña Planes) -- calcula
             * un estimado con la misma fórmula documentada arriba en PHP
             * (base por módulo + cargo por volumen interpolado, salvo
             * "invoicing" solo, que usa su propia curva de precio total),
             * recalcula en vivo mientras se arrastra el slider ("input", no
             * "change"), y reconoce cuando la selección coincide exactamente
             * con uno de los combos ya armados para mostrar ese precio fijo
             * en vez de la fórmula genérica.
             * @returns {void}
             */
            function bindPriceCalculator() {
                const moduleInputs = document.querySelectorAll('.js-calculator-module');
                const volumeInput = document.querySelector('.js-calculator-volume');
                const volumeValueLabel = document.getElementById('calculator-document-volume-value');
                const result = document.getElementById('calculator-result');
                const sendButton = document.getElementById('calculator-send-quote');
                if (! volumeInput || ! volumeValueLabel || ! result || ! sendButton) return;

                let lastQuoteSummary = '';

                function formatCurrency(amount) {
                    return '$' + amount.toLocaleString('es-CO');
                }

                function buildSummary(selectedModules, volumeLabel, price) {
                    const moduleNames = selectedModules.map((key) => CALCULATOR_MODULE_NAMES[key] || key).join(', ');
                    const priceLine = price ? formatCurrency(price) + @json(__('/year')) : @json(__('custom quote'));

                    return @json(__('Modules: ')) + moduleNames + '. ' + @json(__('Volume: ')) + volumeLabel + '. ' + @json(__('Estimated price: ')) + priceLine;
                }

                function updateCalculator() {
                    const selectedModules = Array.from(moduleInputs).filter((input) => input.checked).map((input) => input.value);
                    const documents = parseInt(volumeInput.value, 10);
                    const atMax = documents >= CALCULATOR_VOLUME_MAX;

                    volumeValueLabel.textContent = atMax
                        ? documents.toLocaleString('es-CO') + '+'
                        : documents.toLocaleString('es-CO');

                    if (selectedModules.length === 0) {
                        result.textContent = @json(__('Select at least one module to see an estimate.'));
                        sendButton.classList.add('hidden');
                        return;
                    }

                    // "invoicing" solo (sin ningún otro módulo) tiene su propia curva de
                    // precio TOTAL, calibrada con un margen más bajo sobre el costo de la
                    // API de facturación electrónica pura (ver comentario en PHP) -- para
                    // cualquier otro módulo o combinación se usa la fórmula genérica de
                    // siempre (moduleBase + cargo por volumen).
                    const isInvoicingOnly = selectedModules.length === 1 && selectedModules[0] === 'invoicing';

                    const namedCombo = CALCULATOR_NAMED_COMBOS.find((combo) =>
                        combo.modules.length === selectedModules.length
                        && combo.modules.every((module) => selectedModules.includes(module))
                    );

                    let price;

                    if (isInvoicingOnly) {
                        price = interpolate(CALCULATOR_INVOICING_ONLY_VOLUME_ANCHORS, documents);
                    } else {
                        const genericModuleBase = selectedModules.reduce(
                            (total, module) => total + (CALCULATOR_MODULE_BASE_PRICES[module] ?? CALCULATOR_DEFAULT_MODULE_PRICE),
                            0
                        );
                        const moduleBase = namedCombo ? namedCombo.moduleBase : genericModuleBase;
                        price = moduleBase + interpolate(CALCULATOR_VOLUME_ANCHORS, documents);
                    }

                    const volumeLabel = documents.toLocaleString('es-CO') + ' ' + @json(__('documents/year'));

                    result.innerHTML = (namedCombo
                        ? @json(__('This matches our ":name" combo: ')).replace(':name', namedCombo.name)
                        : '') + '<span class="text-lg font-semibold text-white">' + formatCurrency(price) + '</span> ' + @json(__('/year'))
                        + (atMax ? '<div class="mt-1 text-xs text-neutral-400">' + @json(__('This is an estimate for the top of the range. For more, send us your numbers.')) + '</div>' : '');

                    lastQuoteSummary = buildSummary(selectedModules, volumeLabel, price);
                    sendButton.classList.remove('hidden');
                }

                moduleInputs.forEach((input) => input.addEventListener('change', updateCalculator));
                volumeInput.addEventListener('input', updateCalculator);

                sendButton.addEventListener('click', () => {
                    window.showAuthPromoTab('contact');

                    const messageField = document.getElementById('contact_body');
                    if (messageField) {
                        messageField.value = lastQuoteSummary;
                    }

                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        setMobilePanel(true);
                    }
                });

                updateCalculator();
            }

            bindPriceCalculator();

            initAuthPanelState();

            {{-- El propio <script> se re-ejecuta en cada wire:navigate (ver
                 el comentario del inicio del IIFE), así que sin este guard
                 se iría acumulando un listener de "livewire:navigated" por
                 cada navegación dentro de esta pantalla (login <-> register
                 <-> forgot-password...), ya que "document" no se reemplaza
                 entre una navegación y otra. --}}
            if (! window.__authPanelNavigateBound) {
                window.__authPanelNavigateBound = true;
                document.addEventListener('livewire:navigated', initAuthPanelState);
            }
        })();
        </script>
        @fluxScripts
    </body>
</html>
