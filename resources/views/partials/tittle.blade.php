@php
    $selectedCompany = session('selected_company');
@endphp

<div class="relative mb-6 w-full flex items-center justify-between">
    <div>
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ $subheading }}</flux:subheading>
    </div>

    <div class="flex items-center gap-3">
        @if (filled(data_get($selectedCompany, 'name')))
            <div class="text-right">
                <div class="rounded-md bg-gray-100 px-4 py-3 text-lg font-semibold text-accent dark:bg-neutral-800">
                    {{ data_get($selectedCompany, 'name') }}
                </div>
                @if ((data_get($selectedCompany, 'dian_environment') ?? \App\Models\Company::DIAN_AMBIENTE_PRUEBAS) !== \App\Models\Company::DIAN_AMBIENTE_PRODUCCION)
                    <span class="mt-1 inline-block rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('Testing environment') }}</span>
                @endif
            </div>
        @endif

        @isset($button)
            <a href="{{ $button['route'] }}">
                <flux:button :id="$button['id'] ?? null" variant="primary">
                    {{ $button['label'] }}
                </flux:button>
            </a>
        @endisset
    </div>
</div>
<flux:separator variant="subtle" class="mb-6" />
