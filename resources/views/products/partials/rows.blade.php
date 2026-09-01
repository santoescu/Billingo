@foreach ($products as $product)
    <tr>
        <td class="px-4 py-4">
            <div class="flex items-center gap-3">
                @if ($product->image_url)
                    <img src="{{ $product->image_url }}" alt="" class="shrink-0 size-9 rounded-lg object-cover zoomable-thumb cursor-zoom-in">
                @else
                    <span class="flex items-center justify-center shrink-0 size-9 rounded-lg bg-accent/10 text-accent">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    </span>
                @endif
                <div class="min-w-0">
                    <span class="block text-sm text-gray-600 dark:text-neutral-400 truncate">{{ $product->barcode ?? '—' }}</span>
                    <span class="block text-xs text-neutral-400 dark:text-neutral-500 truncate">{{ $product->code ?? '—' }}</span>
                </div>
            </div>
        </td>
        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $product->description }}</td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400" data-order="{{ $product->unit_price }}">
            {{ $product->unit_price_formatted }}
            <button type="button" class="block text-xs text-accent hover:underline" onclick="showProductPrices('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }})">
                {{ __('View all prices') }}
            </button>
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400" data-order="{{ $product->tracks_inventory ? $product->stock : -1 }}">
            @if ($product->tracks_inventory)
                {{ rtrim(rtrim(number_format((float) $product->stock, 2, '.', ','), '0'), '.') }}
            @else
                <span class="text-xs text-neutral-400">{{ __('Not tracked') }}</span>
            @endif
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            {{ $warehouseNamesFor($product) ?: '—' }}
            @if ($product->tracks_inventory)
                <button type="button" class="block text-xs text-accent hover:underline" onclick="showProductWarehouses('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }})">
                    {{ __('View all warehouses') }}
                </button>
            @endif
        </td>
        <td class="px-4 py-4 text-right">
            <div class="flex justify-end gap-1">
                <a href="{{ route('products.show', $product->_id) }}" class="product-view-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                </a>

                <button type="button" class="product-edit-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="openProductPanel({!! Illuminate\Support\Js::from($product->makeHidden('image_data')) !!})">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                        <path d="m15 5 4 4"></path>
                    </svg>
                </button>

                <div class="hs-dropdown [--auto-close:true] relative inline-flex">
                    <button type="button" class="product-more-actions-btn hs-dropdown-toggle flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('More actions') }}">
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 flex items-center gap-1 dark:bg-neutral-800 dark:border-neutral-700">
                        @if ($product->tracks_inventory)
                            <button type="button" class="product-register-entry-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Register entry') }}" title="{{ __('Register entry') }}" onclick="openStockEntryPanel('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }})">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            </button>
                            <button type="button" class="product-fix-cost-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Fix cost') }}" title="{{ __('Fix cost') }}" onclick="openAverageCostPanel('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }}, {{ (float) ($product->average_cost ?? 0) }})">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                            </button>
                        @endif
                        <form action="{{ route('products.destroy', $product->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="product-delete-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </td>
    </tr>
@endforeach
