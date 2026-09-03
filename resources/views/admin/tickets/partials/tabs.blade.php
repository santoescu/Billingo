{{--
    Nav de la sección "Soporte" (staff de Billingo): agrupa el dashboard de
    métricas, el listado de tickets y las plantillas de respuesta rápida
    bajo un mismo lugar en vez de tenerlos sueltos en el sidebar -- mismo
    patrón que pos/partials/tabs.blade.php.
--}}
@php
    $activeTab = $activeTab ?? 'dashboard';
@endphp

<div class="mb-6 border-b border-gray-200 dark:border-neutral-700">
    <nav class="flex gap-4" aria-label="Tabs">
        <a href="{{ route('admin.tickets.dashboard') }}" wire:navigate id="support-tab-dashboard"
            class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'dashboard' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
            {{ __('Dashboard') }}
        </a>
        <a href="{{ route('admin.tickets.index') }}" wire:navigate id="support-tab-tickets"
            class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'tickets' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
            {{ __('Tickets') }}
        </a>
        <a href="{{ route('admin.canned-responses.index') }}" wire:navigate id="support-tab-templates"
            class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'templates' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
            {{ __('Templates') }}
        </a>
    </nav>
</div>
