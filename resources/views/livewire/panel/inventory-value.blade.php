<div class="{{ $inventory ? '' : 'hidden' }}">
@if ($inventory)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Inventory value') }}</h3>
        <dl class="space-y-2">
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Inventory cost') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">$<x-panel.count-up :target="$inventory['total_cost']" :decimals="2" /></dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Products in stock') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ number_format($inventory['products_count']) }}</dd>
            </div>
            <div class="flex justify-between items-baseline">
                <dt class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Units in stock') }}</dt>
                <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ rtrim(rtrim(number_format($inventory['units'], 2), '0'), '.') }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-zinc-500 dark:text-neutral-400">{{ __('Stock multiplied by the average cost of each product that tracks inventory.') }}</p>
    </div>
@endif
</div>
