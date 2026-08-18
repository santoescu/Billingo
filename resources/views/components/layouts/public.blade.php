@php
    $appearance = session('appearance', 'light');
    $isDark = $appearance === 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-neutral-900">
        @include('components.toast')

        <div class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>

        @stack('scripts')

        @fluxScripts
    </body>
</html>
