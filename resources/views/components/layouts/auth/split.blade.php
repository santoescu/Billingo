@php
    $appearance = session('appearance', 'light');
    $isDark = $appearance === 'dark';

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

    $plans = [
        [
            'name' => __('Basic'),
            'price' => __('From $990,000/year'),
            'tagline' => __('To start invoicing'),
            'features' => [__('1 module of your choice'), __('1 user'), __('Email support')],
        ],
        [
            'name' => __('Pro'),
            'price' => __('From $2,490,000/year'),
            'tagline' => __('Most popular'),
            'features' => [__('All modules'), __('Unlimited users'), __('Priority support')],
            'highlighted' => true,
        ],
        [
            'name' => __('Enterprise'),
            'price' => __('Custom quote'),
            'tagline' => __('Several companies, one shared quota'),
            'features' => [__('Contract shared across companies'), __('Custom-fit quotas'), __('Dedicated support')],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-5 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col overflow-y-auto p-10 text-white lg:col-span-3 lg:flex dark:border-r dark:border-neutral-800">
                <div class="absolute inset-0 bg-neutral-900"></div>
                <div class="relative z-20">
                    <a href="{{ route('home') }}" class="flex items-center text-lg font-medium" wire:navigate>
                        <span class="flex h-10 w-10 items-center justify-center rounded-md">
                            <x-app-logo-icon class="mr-2 h-7 fill-current text-white" />
                        </span>
                        {{ config('app.name', 'Laravel') }}
                    </a>

                    <p class="mt-8 max-w-xl text-2xl font-semibold leading-snug">{{ __('Electronic invoicing, point of sale and payroll, all in one place.') }}</p>
                    <p class="mt-3 max-w-xl text-sm text-neutral-300">{{ __('A platform built for Colombian businesses: issue electronic documents valid before the DIAN, sell over the counter and quote, without switching systems.') }}</p>

                    <div class="mt-10">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Modules') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($modules as $module)
                                <div class="flex items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md {{ $module['color'] }}">
                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $module['icon'] !!}</svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ $module['name'] }}</p>
                                        <p class="mt-0.5 text-xs text-neutral-400">{{ $module['description'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-10 mb-2">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Plans and pricing') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
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
                    </div>
                </div>
            </div>
            <div class="w-full lg:col-span-2 lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
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
        @fluxScripts
    </body>
</html>
