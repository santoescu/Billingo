@php
    $activeTab = $activeTab ?? 'sell';
@endphp

<div class="mb-6 border-b border-gray-200 dark:border-neutral-700">
    <nav class="flex gap-4" aria-label="Tabs">
        <a href="{{ route('pos.create') }}" wire:navigate id="pos-tab-sell"
            class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'sell' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
            {{ __('Sell') }}
        </a>
        <a href="{{ route('pos.shift') }}" wire:navigate id="pos-tab-shift"
            class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'shift' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
            {{ __('Cash register') }}
        </a>
        @if ($isAdmin ?? false)
            <a href="{{ route('pos.sellers.index') }}" wire:navigate id="pos-tab-sellers"
                class="py-3 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'sellers' ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                {{ __('Sellers') }}
            </a>
        @endif
    </nav>
</div>
