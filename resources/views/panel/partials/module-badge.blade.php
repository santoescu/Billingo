@php
    $moduleBadgeColors = [
        'invoicing' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'pos' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'cotizaciones' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    ];
@endphp
<span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium {{ $moduleBadgeColors[$module] ?? 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300' }}">
    {{ config("modules.$module.name") }}
</span>
