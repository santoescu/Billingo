<div class="{{ ! empty($products) ? '' : 'hidden' }}">
@if (! empty($products))
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ __('Low stock') }}</h3>
        <ul class="space-y-2">
            @foreach ($products as $product)
                <li class="flex justify-between items-center gap-3">
                    <span class="text-sm text-gray-800 dark:text-white truncate">{{ $product->description }}</span>
                    <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ rtrim(rtrim(number_format($product->stock, 2), '0'), '.') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
</div>
