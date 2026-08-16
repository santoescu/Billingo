@php
    $warehousesById = $warehouses->keyBy(fn ($warehouse) => (string) $warehouse->_id);
    $priceTypesById = $priceTypes->keyBy(fn ($priceType) => (string) $priceType->_id);

    $searchableSelectConfig = \App\Support\SelectConfig::searchable();
    $basicSelectConfig = \App\Support\SelectConfig::basic();

    $warehouseNamesFor = function ($product) use ($warehousesById) {
        return collect($product->warehouse_stocks ?? [])
            ->map(fn ($entry) => $warehousesById->get($entry['warehouse_id'] ?? null)?->name)
            ->filter()
            ->implode(', ');
    };

    $productPricesMap = $products->mapWithKeys(function ($product) use ($priceTypesById) {
        $extraByType = collect($product->extra_prices ?? [])->keyBy('price_type_id');

        $rows = $priceTypesById->map(function ($priceType) use ($extraByType) {
            $entry = $extraByType->get((string) $priceType->_id);

            return $entry ? ['name' => $priceType->name, 'price' => (float) $entry['price']] : null;
        })->filter()->values();

        return [(string) $product->_id => $rows];
    });

    $productWarehousesMap = $products->mapWithKeys(function ($product) use ($warehousesById) {
        $rows = collect($product->warehouse_stocks ?? [])
            ->map(function ($entry) use ($warehousesById) {
                $warehouse = $warehousesById->get($entry['warehouse_id'] ?? null);

                return $warehouse ? ['name' => $warehouse->name, 'stock' => (float) $entry['stock']] : null;
            })
            ->filter()
            ->values();

        return [(string) $product->_id => $rows];
    });

    $warehouseProductsMap = $warehouses->mapWithKeys(function ($warehouse) use ($products) {
        $warehouseId = (string) $warehouse->_id;

        $items = $products
            ->map(function ($product) use ($warehouseId) {
                $entry = collect($product->warehouse_stocks ?? [])->firstWhere('warehouse_id', $warehouseId);

                return $entry ? [
                    'barcode' => $product->barcode,
                    'code' => $product->code,
                    'description' => $product->description,
                    'price' => $product->unit_price_formatted,
                    'unit_price' => (float) $product->unit_price,
                    'stock' => (float) $entry['stock'],
                ] : null;
            })
            ->filter()
            ->values();

        return [$warehouseId => $items];
    });
@endphp

<x-layouts.app :title="__('Inventory')">
    @include('partials.tittle', [
        'title' => __('Inventory'),
        'subheading' => __('The products and warehouses you manage.'),
    ])

    <div class="mb-4 border-b border-gray-200 dark:border-neutral-700">
        <nav class="flex gap-4" aria-label="Tabs">
            <button type="button" id="tab-products-btn" class="inventory-tab-btn py-3 px-1 border-b-2 border-accent text-sm font-medium text-accent" onclick="showInventoryTab('products')">
                {{ __('Products') }}
            </button>
            <button type="button" id="tab-warehouses-btn" class="inventory-tab-btn py-3 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200" onclick="showInventoryTab('warehouses')">
                {{ __('Warehouses') }}
            </button>
            <button type="button" id="tab-price-types-btn" class="inventory-tab-btn py-3 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200" onclick="showInventoryTab('price-types')">
                {{ __('Price types') }}
            </button>
        </nav>
    </div>

    <div id="tab-products">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <div class="flex gap-2">
                            <flux:button variant="filled" icon="arrow-up-tray" onclick="openImportModal()">
                                {{ __('Import from Excel') }}
                            </flux:button>
                            <flux:button variant="primary" icon="plus" onclick="openProductPanel()">
                                {{ __('New product') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="productsTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Product') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Price') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Stock') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Warehouse') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($products as $product)
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                @if ($product->image_url)
                                                    <img src="{{ $product->image_url }}" alt="" class="shrink-0 size-9 rounded-lg object-cover">
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
                                                <a href="{{ route('products.show', $product->_id) }}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>

                                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="openProductPanel({!! Illuminate\Support\Js::from($product->makeHidden('image_data')) !!})">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                        <path d="m15 5 4 4"></path>
                                                    </svg>
                                                </button>

                                                <div class="hs-dropdown [--auto-close:true] relative inline-flex">
                                                    <button type="button" class="hs-dropdown-toggle flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('More actions') }}">
                                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                    </button>
                                                    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 flex items-center gap-1 dark:bg-neutral-800 dark:border-neutral-700">
                                                        @if ($product->tracks_inventory)
                                                            <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Register entry') }}" title="{{ __('Register entry') }}" onclick="openStockEntryPanel('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }})">
                                                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                                            </button>
                                                            <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Fix cost') }}" title="{{ __('Fix cost') }}" onclick="openAverageCostPanel('{{ (string) $product->_id }}', {{ Illuminate\Support\Js::from($product->description) }}, {{ (float) ($product->average_cost ?? 0) }})">
                                                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                                                            </button>
                                                        @endif
                                                        <form action="{{ route('products.destroy', $product->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-warehouses" class="hidden">
        
        <div class="flex flex-col gap-6">
            <div class="flex justify-end">
                <flux:button variant="primary" icon="plus" onclick="openWarehousePanel()">
                    {{ __('New warehouse') }}
                </flux:button>
            </div>

            @if ($warehouses->isEmpty())
                <section class="flex min-h-[160px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('You have no registered warehouses yet.') }}</p>
                </section>
            @else
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($warehouses as $warehouse)
                        @php $warehouseProductCount = $warehouseProductsMap->get((string) $warehouse->_id, collect())->count(); @endphp
                        <div class="relative space-y-2 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600">
                            <div class="absolute right-3 top-3 flex gap-1">
                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="event.stopPropagation(); openWarehousePanel({!! Illuminate\Support\Js::from($warehouse) !!})">
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                        <path d="m15 5 4 4"></path>
                                    </svg>
                                </button>
                                <form action="{{ route('warehouses.destroy', $warehouse->_id) }}" method="POST" onsubmit="event.stopPropagation(); return window.appConfirmDialog.open(event, this, '{{ __('The stock assigned to this warehouse will become unassigned in each product (the total stock is not lost).') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}" onclick="event.stopPropagation()">
                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>

                            <button type="button" class="w-full space-y-2 pr-14 text-left" onclick="showWarehouseProducts('{{ (string) $warehouse->_id }}', {{ Illuminate\Support\Js::from($warehouse->name) }})">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $warehouse->name }}</h2>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $warehouse->address ?? __('No address') }}</p>
                                <span class="inline-block rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">
                                    {{ trans_choice(':count product|:count products', $warehouseProductCount, ['count' => $warehouseProductCount]) }}
                                </span>
                            </button>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>

    <div id="tab-price-types" class="hidden">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-end items-center gap-4">
                        <flux:button variant="primary" icon="plus" onclick="openPriceTypePanel()">
                            {{ __('New price type') }}
                        </flux:button>
                    </div>

                    @if ($priceTypes->isEmpty())
                        <div class="p-10 text-center">
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('You have no registered price types yet.') }}</p>
                        </div>
                    @else
                        <div class="overflow-hidden">
                            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                                <thead class="bg-gray-50 dark:bg-neutral-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                        <th scope="col" class="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                    @foreach ($priceTypes as $priceType)
                                        <tr>
                                            <td class="px-4 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $priceType->name }}</td>
                                            <td class="px-4 py-4 text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="openPriceTypePanel({!! Illuminate\Support\Js::from($priceType) !!})">
                                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                            <path d="m15 5 4 4"></path>
                                                        </svg>
                                                    </button>

                                                    <form action="{{ route('price-types.destroy', $priceType->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('Products will lose the price associated with this price type.') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}">
                                                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: precios de un producto -->
    <div id="product-prices-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="product-prices-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="product-prices-modal-label" class="font-bold text-gray-800 dark:text-white"></h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#product-prices-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="product-prices-modal-body" class="p-4 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
            </div>
        </div>
    </div>

    <!-- Modal: bodegas de un producto -->
    <div id="product-warehouses-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="product-warehouses-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="product-warehouses-modal-label" class="font-bold text-gray-800 dark:text-white"></h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#product-warehouses-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="product-warehouses-modal-body" class="p-4 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
            </div>
        </div>
    </div>

    <!-- Panel deslizante único: agregar/editar tipo de precio -->
    <div id="price-type-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="price-type-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="price-type-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('Price type') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#price-type-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-visible p-4">
            <form id="priceTypeForm" method="POST" action="{{ route('price-types.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" id="pt-method" name="_method" value="POST">

                <flux:input id="pt-name" name="name" :label="__('Name')" value="{{ old('name') }}" required />

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel deslizante: registrar entrada de mercancía (con costo, único
         punto donde se recalcula average_cost -- ver ProductController::storeStockEntry()) -->
    <div id="stock-entry-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="stock-entry-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="stock-entry-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('Register stock entry') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#stock-entry-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-visible p-4">
            <form id="stockEntryForm" method="POST" class="space-y-6">
                @csrf
                <p id="se-product-name" class="font-medium text-gray-800 dark:text-neutral-200"></p>

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Warehouse') }}</label>
                    <select id="se-warehouse_id" name="warehouse_id" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="se-quantity-display">{{ __('Quantity') }}</label>
                    <input type="text" id="se-quantity-display" inputmode="decimal" placeholder="0"
                        class="h-10 py-2 px-3 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <input type="hidden" id="se-quantity" name="quantity">
                </div>

                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="se-unit_cost-display">{{ __('Unit cost') }}</label>
                    <div class="relative">
                        <input type="text" id="se-unit_cost-display" inputmode="decimal" placeholder="0"
                            class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                            <span class="text-sm">$</span>
                        </div>
                    </div>
                    <input type="hidden" id="se-unit_cost" name="unit_cost">
                </div>

                <flux:input id="se-note" name="note" :label="__('Note (optional)')" maxlength="255" :placeholder="__('e.g. Purchase invoice #1234')" />

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: corregir costo promedio de un producto (sin tocar cantidades
         ni crear un movimiento en el kardex -- ver ProductController::correctAverageCost()) -->
    <div id="average-cost-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="average-cost-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-sm sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="average-cost-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Fix average cost') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#average-cost-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="averageCostForm" method="POST" class="p-4 flex flex-col gap-4">
                    @csrf
                    <p id="ac-product-name" class="font-medium text-gray-800 dark:text-neutral-200"></p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('This only updates the average cost used to value the existing stock. It does not add or remove units, and it will not appear in the kardex.') }}</p>

                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="ac-average_cost-display">{{ __('Unit cost') }}</label>
                        <div class="relative">
                            <input type="text" id="ac-average_cost-display" inputmode="decimal" placeholder="0"
                                class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <span class="text-sm">$</span>
                            </div>
                        </div>
                        <input type="hidden" id="ac-average_cost" name="average_cost">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10" data-hs-overlay="#average-cost-modal">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: productos de una bodega -->
    <div id="warehouse-products-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="warehouse-products-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-3xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="warehouse-products-modal-label" class="font-bold text-gray-800 dark:text-white"></h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#warehouse-products-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="warehouse-products-modal-body" class="p-4 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
            </div>
        </div>
    </div>

    <!-- Modal: importar productos desde Excel -->
    <div id="product-import-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="product-import-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-4xl sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="product-import-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Import from Excel') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#product-import-modal">
                        <span class="sr-only">Close</span>
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>

                <div id="import-step-upload" class="p-4 space-y-4">
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Upload an Excel (.xlsx) or CSV file. On the next step you will decide which column goes into which field.') }}</p>

                    <div id="import-file-upload" data-hs-file-upload='{
                            "url": "#",
                            "autoProcessQueue": false,
                            "singleton": true,
                            "autoHideTrigger": true
                        }'>
                        <template data-hs-file-upload-preview>
                            <div class="p-3 bg-white border border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700">
                                <div class="mb-1 flex justify-between items-center">
                                    <div class="flex items-center gap-x-3">
                                        <span class="size-8 shrink-0 flex justify-center items-center bg-gray-100 text-gray-500 rounded-lg dark:bg-neutral-700 dark:text-neutral-400" data-hs-file-upload-file-icon>
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                                <span data-hs-file-upload-file-name></span>.<span data-hs-file-upload-file-ext></span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-neutral-400" data-hs-file-upload-file-size></p>
                                        </div>
                                    </div>
                                    <button type="button" class="shrink-0 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" data-hs-file-upload-remove title="{{ __('Remove') }}">
                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </div>

                                <div class="flex items-center gap-x-3 whitespace-nowrap">
                                    <div class="flex w-full h-2 bg-gray-100 rounded-full overflow-hidden dark:bg-neutral-700" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-hs-file-upload-progress-bar>
                                        <div class="flex flex-col justify-center rounded-full overflow-hidden bg-accent text-xs text-white text-center whitespace-nowrap transition-all duration-500 hs-file-upload-complete:bg-green-500" style="width: 0" data-hs-file-upload-progress-bar-pane></div>
                                    </div>
                                    <div class="w-10 text-end">
                                        <span class="text-sm text-gray-800 dark:text-white">
                                            <span data-hs-file-upload-progress-bar-value>0</span>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="cursor-pointer h-20 flex items-center justify-center gap-2 border border-dashed border-gray-300 rounded-lg text-center dark:border-neutral-600" data-hs-file-upload-trigger>
                            <svg class="shrink-0 size-5 text-gray-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 13v8"/><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="m8 17 4-4 4 4"/></svg>
                            <p class="text-sm text-gray-600 dark:text-neutral-400">
                                {{ __('Drop your file here or') }} <span class="font-semibold text-accent">{{ __('browse') }}</span>
                            </p>
                        </div>

                        <div class="mt-2 space-y-2 empty:mt-0" data-hs-file-upload-previews></div>
                    </div>

                    <input type="file" id="import-file-input" class="hidden" accept=".xlsx,.xls,.csv">

                    <div id="import-upload-status" class="hidden text-sm text-red-600 dark:text-red-400"></div>
                    <div class="flex justify-end">
                        <flux:button type="button" id="import-analyze-btn" variant="primary" onclick="analyzeImportFile()">
                            <span id="import-analyze-label">{{ __('Analyze file') }}</span>
                            <span id="import-analyze-spinner" class="hidden">
                                <span class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                    {{ __('Processing...') }}
                                </span>
                            </span>
                        </flux:button>
                    </div>
                </div>

                <div id="import-step-mapping" class="hidden p-4 space-y-4">
                    <p id="import-row-count" class="text-sm text-neutral-600 dark:text-neutral-400"></p>
                    <div class="max-h-[55vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Excel column') }}</th>
                                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Sample data') }}</th>
                                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Maps to') }}</th>
                                </tr>
                            </thead>
                            <tbody id="import-mapping-rows" class="divide-y divide-gray-200 dark:divide-neutral-700"></tbody>
                        </table>
                    </div>
                    <div class="flex justify-between items-center">
                        <flux:button type="button" id="import-back-btn" variant="filled" onclick="resetImportModal()">{{ __('Back') }}</flux:button>
                        <flux:button type="button" id="import-submit-btn" variant="primary" onclick="submitImport()">
                            <span id="import-submit-label">{{ __('Import') }}</span>
                            <span id="import-submit-spinner" class="hidden">
                                <span class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                    {{ __('Processing...') }}
                                </span>
                            </span>
                        </flux:button>
                    </div>
                </div>

                <div id="import-step-result" class="hidden p-4 space-y-4">
                    <p id="import-result-summary" class="text-sm text-neutral-800 dark:text-neutral-200"></p>
                    <div class="flex justify-end">
                        <flux:button type="button" variant="primary" onclick="window.location.reload()">{{ __('Close') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel deslizante único: agregar/editar producto -->
    <div id="product-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700 flex flex-col" role="dialog" tabindex="-1" aria-labelledby="product-panel-label">
        <div class="shrink-0 flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="product-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('Product') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#product-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <form id="productForm" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" id="pr-method" name="_method" value="POST">
                <input type="hidden" id="pr-remove_image" name="remove_image" value="0">

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-stretch gap-3">
                    <div class="shrink-0 relative w-32 aspect-square">
                        <button type="button" onclick="document.getElementById('pr-image').click()" class="relative block size-full rounded-lg overflow-hidden focus:outline-hidden focus:ring-2 focus:ring-accent" title="{{ __('Image') }}">
                            <img id="pr-image-preview" src="" alt="" class="hidden size-full object-cover object-center">
                            <span id="pr-image-placeholder" class="flex items-center justify-center size-full bg-accent/10 text-accent">
                                <svg class="shrink-0 size-11" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            </span>
                        </button>
                        <input type="file" id="pr-image" name="image" accept="image/*" class="hidden">
                        <button type="button" id="pr-image-remove-btn" class="hidden absolute -top-1.5 -end-1.5 size-5 items-center justify-center rounded-full bg-white text-gray-500 border border-gray-200 shadow-sm hover:bg-red-50 hover:text-red-600 hover:border-red-200 focus:outline-hidden dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-400" onclick="removeProductImage()" aria-label="{{ __('Remove image') }}" title="{{ __('Remove image') }}">
                            <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 flex flex-col gap-3">
                        <flux:input id="pr-code" name="code" :label="__('Code')" value="{{ old('code') }}" required />
                        <flux:input id="pr-barcode" name="barcode" :label="__('Barcode')" value="{{ old('barcode') }}" />
                    </div>
                </div>

                <flux:input id="pr-description" name="description" :label="__('Description')" value="{{ old('description') }}" required />

                <flux:field>
                    <flux:label>{{ __('Unit') }}</flux:label>
                    <select id="pr-unit_code" name="unit_code" data-hs-select='{!! $searchableSelectConfig !!}' class="hidden">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach ($measurementUnits as $unit)
                            <option value="{{ $unit->codigo }}" @selected(old('unit_code') === $unit->codigo)>{{ $unit->codigo }} - {{ $unit->descripcion }}</option>
                        @endforeach
                    </select>
                </flux:field>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('Prices') }}</label>
                        <flux:button type="button" size="sm" variant="filled" icon="plus" onclick="addExtraPriceLine()">{{ __('Add price') }}</flux:button>
                    </div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('The first price is the one used by default.') }}</p>
                    <div id="pr-extra_prices_body" class="space-y-3"></div>
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="checkbox" id="pr-tracks_inventory" name="tracks_inventory" value="1" class="accent-accent" @checked(old('tracks_inventory'))>
                    {{ __('Track inventory for this product') }}
                </label>

                <div id="pr-stock_wrapper" class="hidden space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Total stock') }}</label>
                        <div class="product-stock-input-wrapper bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg h-10" data-hs-input-number='{"step": 1, "min": 0}'>
                            <div class="w-full h-full flex justify-between items-center gap-x-1">
                                <div class="grow px-3">
                                    <input id="pr-stock_total" class="w-full h-full p-0 bg-transparent border-0 text-sm text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-400 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" style="-moz-appearance: textfield;" type="number" name="stock" aria-roledescription="{{ __('Total stock') }}" value="{{ old('stock') }}" data-hs-input-number-input>
                                </div>
                                <div class="flex flex-col h-full divide-y divide-zinc-200 dark:divide-white/10 border-s border-zinc-200 dark:border-white/10">
                                    <button type="button" class="flex-1 w-7 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-se-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Increase') }}" data-hs-input-number-increment>
                                        <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                    </button>
                                    <button type="button" class="flex-1 w-7 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-ee-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Decrease') }}" data-hs-input-number-decrement>
                                        <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Changing this value later will record a stock adjustment.') }}</p>

                    <div id="pr-initial-cost-wrapper" class="hidden">
                        <label class="block text-sm font-medium text-zinc-800 dark:text-white mb-2" for="pr-initial_unit_cost-display">{{ __('Unit cost') }}</label>
                        <div class="relative">
                            <input type="text" id="pr-initial_unit_cost-display" inputmode="decimal" placeholder="0"
                                class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                                <span class="text-sm">$</span>
                            </div>
                        </div>
                        <input type="hidden" id="pr-initial_unit_cost" name="initial_unit_cost">
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Used to set the starting average cost. To add more stock later with its own cost, use "Register entry" from the product list.') }}</p>
                    </div>

                    <div class="flex justify-between items-center">
                        <label class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('Stock by warehouse') }}</label>
                        <flux:button type="button" size="sm" variant="filled" icon="plus" onclick="addProductWarehouseLine()">{{ __('Add warehouse') }}</flux:button>
                    </div>
                    <div id="pr-warehouse_stocks_body" class="space-y-3"></div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Unassigned') }}</label>
                        <div class="bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg h-10 flex items-center px-3">
                            <input id="pr-stock_unassigned" type="text" readonly tabindex="-1" class="w-full h-full p-0 bg-transparent border-0 text-sm text-zinc-700 dark:text-zinc-300 focus:ring-0 focus:outline-hidden" value="0">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel deslizante único: agregar/editar bodega -->
    <div id="warehouse-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="warehouse-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="warehouse-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('Warehouse') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#warehouse-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-visible p-4">
            <form id="warehouseForm" method="POST" action="{{ route('warehouses.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" id="wh-method" name="_method" value="POST">

                <flux:input id="wh-name" name="name" :label="__('Name')" value="{{ old('name') }}" required />
                <flux:input id="wh-address" name="address" :label="__('Address')" value="{{ old('address') }}" />

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <template id="extraPriceLineTemplate">
        <div class="extra-price-line flex gap-3 items-end">
            <div class="flex-1">
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Price name') }}</label>
                <select class="extra-price-type hidden" name="extra_prices[__INDEX__][price_type_id]" data-hs-select='{!! $basicSelectConfig !!}'>
                    <option value="">{{ __('Select...') }}</option>
                    @foreach ($priceTypes as $priceType)
                        <option value="{{ $priceType->_id }}">{{ $priceType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40 shrink-0">
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Price') }}</label>
                <div class="relative">
                    <input type="text" class="extra-price-display ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent" inputmode="decimal" placeholder="0">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <span class="text-sm">$</span>
                    </div>
                </div>
                <input type="hidden" class="extra-price-value" name="extra_prices[__INDEX__][price]">
            </div>
            <button type="button" class="h-10 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" onclick="removeExtraPriceLine(this)">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
            </button>
        </div>
    </template>

    <template id="warehouseStockLineTemplate">
        <div class="warehouse-stock-line flex gap-3 items-end">
            <div class="flex-1">
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Warehouse') }}</label>
                <select class="warehouse-stock-warehouse hidden" name="warehouse_stocks[__INDEX__][warehouse_id]" data-hs-select='{!! $basicSelectConfig !!}'>
                    <option value="">{{ __('Select...') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->_id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32 shrink-0">
                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Stock') }}</label>
                <div class="product-stock-input-wrapper bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg h-10" data-hs-input-number='{"step": 1, "min": 0}'>
                    <div class="w-full h-full flex justify-between items-center gap-x-1">
                        <div class="grow px-2">
                            <input class="warehouse-stock-value w-full h-full p-0 bg-transparent border-0 text-sm text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-400 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" style="-moz-appearance: textfield;" type="number" name="warehouse_stocks[__INDEX__][stock]" aria-roledescription="{{ __('Stock') }}" value="0" data-hs-input-number-input>
                        </div>
                        <div class="flex flex-col h-full divide-y divide-zinc-200 dark:divide-white/10 border-s border-zinc-200 dark:border-white/10">
                            <button type="button" class="flex-1 w-6 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-se-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Increase') }}" data-hs-input-number-increment>
                                <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            </button>
                            <button type="button" class="flex-1 w-6 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 rounded-ee-lg hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Decrease') }}" data-hs-input-number-decrement>
                                <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="h-10 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" onclick="removeProductWarehouseLine(this)">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
            </button>
        </div>
    </template>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            (function () {
                const warehouseProductsMap = @json($warehouseProductsMap);
                // Para insertarlo dentro de un template literal de JS hay que pasarlo
                // por la directiva de Blade que lo escapa para JS (no el texto
                // crudo): si no, el propio parser de JS desescapa las comillas
                // del JSON antes de que llegue al HTML, dejando el atributo
                // data-hs-select con un JSON inválido.
                const basicSelectConfigJson = @json($basicSelectConfig);
                const searchableSelectConfigJson = @json($searchableSelectConfig);
                const productPricesMap = @json($productPricesMap);
                const productWarehousesMap = @json($productWarehousesMap);
                const inventoryTabs = ['products', 'warehouses', 'price-types'];

                window.showInventoryTab = function (tab) {
                    inventoryTabs.forEach((name) => {
                        const isActive = name === tab;
                        document.getElementById(`tab-${name}`).classList.toggle('hidden', ! isActive);

                        const btn = document.getElementById(`tab-${name}-btn`);
                        btn.classList.toggle('border-accent', isActive);
                        btn.classList.toggle('text-accent', isActive);
                        btn.classList.toggle('border-transparent', ! isActive);
                        btn.classList.toggle('text-gray-500', ! isActive);
                    });
                };

                function escapeHtml(value) {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
                }

                function renderWarehouseProductRows(items) {
                    return items.map((item) => `
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${escapeHtml(item.barcode) || '—'}</td>
                            <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400">${escapeHtml(item.code) || '—'}</td>
                            <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-neutral-200">${escapeHtml(item.description)}</td>
                            <td class="px-3 py-2 text-sm text-gray-600 dark:text-neutral-400" data-order="${item.unit_price ?? 0}">${escapeHtml(item.price)}</td>
                            <td class="px-3 py-2 text-sm text-end text-gray-600 dark:text-neutral-400">${item.stock}</td>
                        </tr>
                    `).join('');
                }

                let warehouseProductsTableInstance = null;

                window.showWarehouseProducts = function (warehouseId, warehouseName) {
                    const items = warehouseProductsMap[warehouseId] || [];

                    document.getElementById('warehouse-products-modal-label').textContent = warehouseName;

                    const body = document.getElementById('warehouse-products-modal-body');
                    if (warehouseProductsTableInstance) {
                        warehouseProductsTableInstance.destroy();
                        warehouseProductsTableInstance = null;
                    }

                    if (items.length === 0) {
                        body.innerHTML = `<p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('No products assigned to this warehouse yet.') }}</p>`;
                    } else {
                        body.innerHTML = `
                            <div class="relative mb-3">
                                <label class="sr-only">{{ __('Search') }}</label>
                                <input type="text" id="warehouse-products-search" placeholder="{{ __('Search') }}" autocomplete="off"
                                    class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent">
                            </div>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700" id="warehouse-products-table">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Barcode') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Price') }}</th>
                                        <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Stock') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                    ${renderWarehouseProductRows(items)}
                                </tbody>
                            </table>
                        `;

                        warehouseProductsTableInstance = initWorkflowDataTable('#warehouse-products-table', '#warehouse-products-search', {
                            pageLength: 10,
                        });
                    }

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#warehouse-products-modal');
                    }
                };

                function formatMoneyCop(value) {
                    return '$' + Number(value ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                let warehouseStockLineIndex = 0;

                function toggleStockField() {
                    const checkbox = document.getElementById('pr-tracks_inventory');
                    document.getElementById('pr-stock_wrapper').classList.toggle('hidden', !checkbox.checked);

                    const isNew = document.getElementById('productForm').dataset.isNew === 'true';
                    document.getElementById('pr-initial-cost-wrapper').classList.toggle('hidden', !(checkbox.checked && isNew));
                }

                function recalcUnassignedStock() {
                    const total = parseFloat(document.getElementById('pr-stock_total').value) || 0;
                    const assigned = Array.from(document.querySelectorAll('.warehouse-stock-value'))
                        .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
                    const unassigned = Math.round((total - assigned) * 100) / 100;

                    const unassignedInput = document.getElementById('pr-stock_unassigned');
                    unassignedInput.value = unassigned;
                    unassignedInput.classList.toggle('text-red-600', unassigned < 0);
                    unassignedInput.classList.toggle('dark:text-red-400', unassigned < 0);
                }

                /**
                 * Reconstruye cada select de bodega deshabilitando las que ya
                 * están elegidas en OTRA fila, para que un mismo producto no
                 * pueda repetir bodega.
                 * @returns {void}
                 */
                function refreshWarehouseSelectOptions() {
                    const selects = Array.from(document.querySelectorAll('.warehouse-stock-warehouse'));
                    const usedByOthers = (self) => selects
                        .filter((el) => el !== self)
                        .map((el) => el.value)
                        .filter(Boolean);

                    selects.forEach((selectEl) => {
                        const currentValue = selectEl.value;
                        const used = usedByOthers(selectEl);
                        const instance = window.HSSelect && HSSelect.getInstance(selectEl);
                        if (instance && typeof instance.destroy === 'function') {
                            instance.destroy();
                            selectEl.parentElement.appendChild(selectEl);
                        }

                        selectEl.querySelectorAll('option[value]').forEach((option) => {
                            if (option.value === '') return;
                            option.disabled = used.includes(option.value) && option.value !== currentValue;
                        });

                        if (window.HSSelect) {
                            new HSSelect(selectEl);
                        }
                    });
                }

                /**
                 * Agrega una fila de stock por bodega al formulario de
                 * producto. El valor de stock se fija ANTES de inicializar
                 * el componente +/- (HSInputNumber): si se pone después, el
                 * contador queda desincronizado con lo que se ve (el botón
                 * +/- seguiría pensando que arrancó en 0). Los botones +/-
                 * no disparan un evento "input" nativo, por eso también se
                 * escucha "change.hs.inputNumber".
                 * @param {string} [warehouseId] Bodega preseleccionada.
                 * @param {number} [stock] Stock inicial de esa bodega.
                 * @returns {HTMLElement} La fila creada.
                 */
                window.addProductWarehouseLine = function (warehouseId, stock) {
                    const template = document.getElementById('warehouseStockLineTemplate');
                    const html = template.innerHTML.replaceAll('__INDEX__', warehouseStockLineIndex);
                    const container = document.getElementById('pr-warehouse_stocks_body');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const row = wrapper.firstElementChild;
                    container.appendChild(row);
                    warehouseStockLineIndex++;

                    if (window.HSSelect) {
                        row.querySelectorAll('[data-hs-select]').forEach((el) => new HSSelect(el));
                    }

                    const warehouseSelect = row.querySelector('.warehouse-stock-warehouse');
                    if (warehouseId) {
                        const instance = window.HSSelect && HSSelect.getInstance(warehouseSelect);
                        if (instance) {
                            instance.setValue(warehouseId);
                        } else {
                            warehouseSelect.value = warehouseId;
                        }
                    }
                    warehouseSelect.addEventListener('change', refreshWarehouseSelectOptions);

                    const stockInput = row.querySelector('.warehouse-stock-value');
                    stockInput.value = stock ?? 0;

                    if (window.HSInputNumber) {
                        row.querySelectorAll('[data-hs-input-number]').forEach((el) => new HSInputNumber(el));
                    }

                    stockInput.addEventListener('input', recalcUnassignedStock);
                    row.querySelector('[data-hs-input-number]')?.addEventListener('change.hs.inputNumber', recalcUnassignedStock);

                    refreshWarehouseSelectOptions();
                    recalcUnassignedStock();

                    return row;
                };

                window.removeProductWarehouseLine = function (button) {
                    button.closest('.warehouse-stock-line').remove();
                    refreshWarehouseSelectOptions();
                    recalcUnassignedStock();
                };

                function resetProductWarehouseLines(entries) {
                    document.getElementById('pr-warehouse_stocks_body').innerHTML = '';
                    (entries ?? []).forEach((entry) => addProductWarehouseLine(entry.warehouse_id, entry.stock));
                    recalcUnassignedStock();
                }

                let extraPriceLineIndex = 0;

                /**
                 * Reconstruye cada select de tipo de precio deshabilitando
                 * los que ya están elegidos en OTRA fila, para no repetir el
                 * mismo tipo de precio dos veces en un mismo producto.
                 * @returns {void}
                 */
                function refreshPriceTypeSelectOptions() {
                    const selects = Array.from(document.querySelectorAll('.extra-price-type'));
                    const usedByOthers = (self) => selects
                        .filter((el) => el !== self)
                        .map((el) => el.value)
                        .filter(Boolean);

                    selects.forEach((selectEl) => {
                        const currentValue = selectEl.value;
                        const used = usedByOthers(selectEl);
                        const instance = window.HSSelect && HSSelect.getInstance(selectEl);
                        if (instance && typeof instance.destroy === 'function') {
                            instance.destroy();
                            selectEl.parentElement.appendChild(selectEl);
                        }

                        selectEl.querySelectorAll('option[value]').forEach((option) => {
                            if (option.value === '') return;
                            option.disabled = used.includes(option.value) && option.value !== currentValue;
                        });

                        if (window.HSSelect) {
                            new HSSelect(selectEl);
                        }
                    });
                }

                /**
                 * Formatea un valor decimal al formato visible colombiano
                 * (punto de miles, coma de centavos) -- el campo oculto
                 * (rawDecimalValue) guarda el valor con punto decimal
                 * estándar, el que espera el backend.
                 * @param {string} intPart Parte entera, solo dígitos.
                 * @param {string} decPart Parte decimal, solo dígitos (máx. 2).
                 * @param {boolean} hasComma Si el usuario ya escribió la coma decimal.
                 * @returns {string}
                 */
                function formatCop(intPart, decPart, hasComma) {
                    if (!intPart && !hasComma) {
                        return '';
                    }
                    const formattedInt = Number(intPart || '0').toLocaleString('es-CO');
                    return hasComma ? `${formattedInt},${decPart}` : formattedInt;
                }

                function rawDecimalValue(intPart, decPart) {
                    if (!intPart && !decPart) {
                        return '';
                    }
                    return decPart ? `${intPart || '0'}.${decPart}` : (intPart || '0');
                }

                /**
                 * Precarga el precio visible/oculto de una fila a partir de
                 * un valor crudo con punto decimal, tal como viene del
                 * backend (ej. "12345.67").
                 * @param {HTMLElement} row
                 * @param {string|number} rawValue
                 * @returns {void}
                 */
                function setExtraPriceValue(row, rawValue) {
                    const display = row.querySelector('.extra-price-display');
                    const hidden = row.querySelector('.extra-price-value');
                    const str = String(rawValue ?? '');
                    if (!str) {
                        hidden.value = '';
                        display.value = '';
                        return;
                    }
                    const [intRaw, decRaw] = str.split('.');
                    const intPart = (intRaw ?? '').replace(/\D/g, '');
                    const decPart = (decRaw ?? '').replace(/\D/g, '').slice(0, 2);
                    hidden.value = rawDecimalValue(intPart, decPart);
                    display.value = formatCop(intPart, decPart, decRaw !== undefined);
                }

                /**
                 * Reformatea el precio mientras el usuario escribe. Lo que
                 * ve/escribe usa coma para centavos; se quitan los puntos de
                 * miles que el propio formatCop() agregó al formatear.
                 * @param {HTMLElement} row
                 * @param {string} typedValue
                 * @returns {void}
                 */
                function handleExtraPriceInput(row, typedValue) {
                    let str = String(typedValue ?? '').replace(/\./g, '');
                    const commaIndex = str.indexOf(',');
                    let intPart, decPart, hasComma;
                    if (commaIndex === -1) {
                        intPart = str.replace(/\D/g, '');
                        decPart = '';
                        hasComma = false;
                    } else {
                        intPart = str.slice(0, commaIndex).replace(/\D/g, '');
                        decPart = str.slice(commaIndex + 1).replace(/\D/g, '').slice(0, 2);
                        hasComma = true;
                    }
                    row.querySelector('.extra-price-value').value = rawDecimalValue(intPart, decPart);
                    row.querySelector('.extra-price-display').value = formatCop(intPart, decPart, hasComma);
                }

                window.addExtraPriceLine = function (priceTypeId, price) {
                    const template = document.getElementById('extraPriceLineTemplate');
                    const html = template.innerHTML.replaceAll('__INDEX__', extraPriceLineIndex);
                    const container = document.getElementById('pr-extra_prices_body');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const row = wrapper.firstElementChild;
                    container.appendChild(row);
                    extraPriceLineIndex++;

                    if (window.HSSelect) {
                        row.querySelectorAll('[data-hs-select]').forEach((el) => new HSSelect(el));
                    }

                    const typeSelect = row.querySelector('.extra-price-type');
                    if (priceTypeId) {
                        const instance = window.HSSelect && HSSelect.getInstance(typeSelect);
                        if (instance) {
                            instance.setValue(priceTypeId);
                        } else {
                            typeSelect.value = priceTypeId;
                        }
                    }
                    typeSelect.addEventListener('change', refreshPriceTypeSelectOptions);

                    setExtraPriceValue(row, price);
                    row.querySelector('.extra-price-display').addEventListener('input', (event) => {
                        handleExtraPriceInput(row, event.target.value);
                    });

                    refreshPriceTypeSelectOptions();

                    return row;
                };

                window.removeExtraPriceLine = function (button) {
                    button.closest('.extra-price-line').remove();
                    refreshPriceTypeSelectOptions();
                };

                function resetExtraPriceLines(entries) {
                    document.getElementById('pr-extra_prices_body').innerHTML = '';
                    (entries ?? []).forEach((entry) => addExtraPriceLine(entry.price_type_id, entry.price));
                }

                window.showProductPrices = function (productId, productName) {
                    const rows = productPricesMap[productId] || [];

                    document.getElementById('product-prices-modal-label').textContent = productName;

                    const body = document.getElementById('product-prices-modal-body');
                    if (rows.length === 0) {
                        body.innerHTML = `<p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This product has no price types assigned yet.') }}</p>`;
                    } else {
                        body.innerHTML = `
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                    ${rows.map((row) => `
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-800 dark:text-neutral-200">${escapeHtml(row.name)}</td>
                                            <td class="px-3 py-2 text-sm text-end text-gray-600 dark:text-neutral-400">${formatMoneyCop(row.price)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#product-prices-modal');
                    }
                };

                window.showProductWarehouses = function (productId, productName) {
                    const rows = productWarehousesMap[productId] || [];

                    document.getElementById('product-warehouses-modal-label').textContent = productName;

                    const body = document.getElementById('product-warehouses-modal-body');
                    if (rows.length === 0) {
                        body.innerHTML = `<p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This product has no stock in any warehouse yet.') }}</p>`;
                    } else {
                        body.innerHTML = `
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                    ${rows.map((row) => `
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-800 dark:text-neutral-200">${escapeHtml(row.name)}</td>
                                            <td class="px-3 py-2 text-sm text-end text-gray-600 dark:text-neutral-400">${row.stock}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#product-warehouses-modal');
                    }
                };

                /**
                 * Mismo parseo que formatCop/rawDecimalValue, aplicado a
                 * campos sueltos por id (no filas repetibles como en extra
                 * price -- por eso no reusa handleExtraPriceInput, que está
                 * acoplado a `.extra-price-*` dentro de una fila).
                 * @param {string} typedValue Lo que el usuario escribió.
                 * @param {string} hiddenId Id del input oculto (valor con punto decimal).
                 * @param {string} displayId Id del input visible (formato colombiano).
                 * @returns {void}
                 */
                function handleDecimalDisplayInput(typedValue, hiddenId, displayId) {
                    let str = String(typedValue ?? '').replace(/\./g, '');
                    const commaIndex = str.indexOf(',');
                    let intPart, decPart, hasComma;
                    if (commaIndex === -1) {
                        intPart = str.replace(/\D/g, '');
                        decPart = '';
                        hasComma = false;
                    } else {
                        intPart = str.slice(0, commaIndex).replace(/\D/g, '');
                        decPart = str.slice(commaIndex + 1).replace(/\D/g, '').slice(0, 2);
                        hasComma = true;
                    }
                    document.getElementById(hiddenId).value = rawDecimalValue(intPart, decPart);
                    document.getElementById(displayId).value = formatCop(intPart, decPart, hasComma);
                }

                window.openStockEntryPanel = function (productId, productName) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#stock-entry-panel');
                    }

                    const form = document.getElementById('stockEntryForm');
                    document.getElementById('se-product-name').textContent = productName;
                    setSelectValue('se-warehouse_id', '');
                    document.getElementById('se-quantity').value = '';
                    document.getElementById('se-quantity-display').value = '';
                    document.getElementById('se-unit_cost').value = '';
                    document.getElementById('se-unit_cost-display').value = '';
                    document.getElementById('se-note').value = '';

                    form.action = @json(route('products.stock-entries.store', ['product' => '__ID__'])).replace('__ID__', productId);
                };

                /**
                 * Abre el modal de corrección de costo promedio, precargando
                 * el costo actual (puede ser 0) con el mismo parseo que usa
                 * setExtraPriceValue para un valor crudo con punto decimal
                 * viniendo del backend.
                 * @param {string} productId
                 * @param {string} productName
                 * @param {number} currentAverageCost
                 * @returns {void}
                 */
                window.openAverageCostPanel = function (productId, productName, currentAverageCost) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#average-cost-modal');
                    }

                    const form = document.getElementById('averageCostForm');
                    document.getElementById('ac-product-name').textContent = productName;

                    const str = String(currentAverageCost ?? 0);
                    const [intRaw, decRaw] = str.split('.');
                    const intPart = (intRaw ?? '').replace(/\D/g, '');
                    const decPart = (decRaw ?? '').replace(/\D/g, '').slice(0, 2);
                    document.getElementById('ac-average_cost').value = rawDecimalValue(intPart, decPart);
                    document.getElementById('ac-average_cost-display').value = formatCop(intPart, decPart, decRaw !== undefined);

                    form.action = @json(route('products.average-cost.update', ['product' => '__ID__'])).replace('__ID__', productId);
                };

                window.openPriceTypePanel = function (priceType) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#price-type-panel');
                    }

                    const form = document.getElementById('priceTypeForm');

                    document.getElementById('pt-name').value = priceType?.name ?? '';

                    if (priceType?.id) {
                        form.action = @json(route('price-types.update', ['priceType' => '__ID__'])).replace('__ID__', priceType.id);
                        document.getElementById('pt-method').value = 'PUT';
                    } else {
                        form.action = @json(route('price-types.store'));
                        document.getElementById('pt-method').value = 'POST';
                    }
                };

                // "__ignore__" en vez de "" para el valor de "No importar": un
                // <option value=""> se comporta raro en el select de Preline
                // (lo trata como "nada seleccionado" en vez de una opción real),
                // así que una vez elegías otra cosa ya no lo dejaba volver a
                // seleccionar "No importar".
                const importIgnoreValue = '__ignore__';
                const importTargetOptions = [
                    [importIgnoreValue, '{{ __('Do not import') }}'],
                    ['code', '{{ __('Code') }}'],
                    ['description', '{{ __('Description') }}'],
                    ['barcode', '{{ __('Barcode') }}'],
                    ['unit_code', '{{ __('Unit (DIAN code)') }}'],
                    ['tracks_inventory', '{{ __('Tracks inventory (SI/NO)') }}'],
                    ['stock', '{{ __('Unassigned stock') }}'],
                    ['warehouse_stock', '{{ __('Stock in a warehouse') }}'],
                    ['price', '{{ __('Price') }}'],
                    ['cost', '{{ __('Unit cost') }}'],
                ];
                const existingWarehouseNames = @json($warehouses->pluck('name'));
                const existingPriceTypeNames = @json($priceTypes->pluck('name'));
                let importToken = null;

                window.openImportModal = function () {
                    resetImportModal();
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#product-import-modal');
                    }
                };

                window.resetImportModal = function () {
                    importToken = null;
                    document.getElementById('import-file-input').value = '';
                    document.getElementById('import-upload-status').classList.add('hidden');
                    document.getElementById('import-step-upload').classList.remove('hidden');
                    document.getElementById('import-step-mapping').classList.add('hidden');
                    document.getElementById('import-step-result').classList.add('hidden');
                    document.getElementById('import-submit-label').classList.remove('hidden');
                    document.getElementById('import-submit-spinner').classList.add('hidden');
                    document.getElementById('import-submit-btn').disabled = false;
                    document.getElementById('import-back-btn').disabled = false;
                    document.getElementById('import-analyze-label').classList.remove('hidden');
                    document.getElementById('import-analyze-spinner').classList.add('hidden');
                    document.getElementById('import-analyze-btn').disabled = false;
                    window.appModalProcessing.stop('#product-import-modal');

                    const uploadEl = document.getElementById('import-file-upload');
                    const instance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
                    instance?.element?.dropzone?.removeAllFiles(true);
                };

                window.analyzeImportFile = async function () {
                    const fileInput = document.getElementById('import-file-input');
                    const statusEl = document.getElementById('import-upload-status');
                    const analyzeBtn = document.getElementById('import-analyze-btn');
                    const labelEl = document.getElementById('import-analyze-label');
                    const spinnerEl = document.getElementById('import-analyze-spinner');
                    statusEl.classList.add('hidden');

                    if (! fileInput.files.length) {
                        statusEl.textContent = '{{ __('Choose a file first.') }}';
                        statusEl.classList.remove('hidden');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('file', fileInput.files[0]);

                    analyzeBtn.disabled = true;
                    labelEl.classList.add('hidden');
                    spinnerEl.classList.remove('hidden');
                    window.appModalProcessing.start('#product-import-modal');

                    try {
                        const response = await fetch(@json(route('products.import.preview')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: formData,
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            statusEl.textContent = data.message || '{{ __('Could not read the file.') }}';
                            statusEl.classList.remove('hidden');
                            return;
                        }

                        importToken = data.token;
                        renderImportMapping(data.headers, data.sample, data.row_count);

                        document.getElementById('import-step-upload').classList.add('hidden');
                        document.getElementById('import-step-mapping').classList.remove('hidden');
                    } catch (error) {
                        statusEl.textContent = error.message;
                        statusEl.classList.remove('hidden');
                    } finally {
                        analyzeBtn.disabled = false;
                        labelEl.classList.remove('hidden');
                        spinnerEl.classList.add('hidden');
                        window.appModalProcessing.stop('#product-import-modal');
                    }
                };

                /**
                 * Arma las opciones de un select "nombre nuevo o ya
                 * existente": primera opción = el encabezado de la columna
                 * (crea uno nuevo con ese nombre), seguida de los que ya
                 * existen (sin repetir el encabezado si ya coincide con uno
                 * existente).
                 * @param {string} header
                 * @param {string[]} existingNames
                 * @returns {string} HTML de las <option>.
                 */
                function buildNameOptions(header, existingNames) {
                    const isExisting = existingNames.includes(header);
                    const seen = new Set([header]);
                    const extra = existingNames.filter((name) => {
                        if (seen.has(name)) return false;
                        seen.add(name);
                        return true;
                    });

                    const headerLabel = isExisting ? escapeHtml(header) : `${escapeHtml(header)} ({{ __('new') }})`;

                    return `<option value="${escapeHtml(header)}" selected>${headerLabel}</option>`
                        + extra.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`).join('');
                }

                function renderImportMapping(headers, sample, rowCount) {
                    document.getElementById('import-row-count').textContent =
                        '{{ __('Rows found') }}: ' + rowCount;

                    const optionsHtml = importTargetOptions
                        .map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`)
                        .join('');

                    const container = document.getElementById('import-mapping-rows');
                    container.innerHTML = headers.map((header, index) => {
                        const samples = sample.map((row) => escapeHtml(row[index] ?? '')).filter(Boolean).join(', ');

                        return `
                            <tr class="import-mapping-row" data-column="${index}">
                                <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-neutral-200 align-top">${escapeHtml(header)}</td>
                                <td class="px-3 py-2 text-xs text-gray-500 dark:text-neutral-400 align-top">${samples}</td>
                                <td class="px-3 py-2 align-top">
                                    <select class="import-target-select hidden" data-hs-select='${basicSelectConfigJson}'>
                                        ${optionsHtml}
                                    </select>
                                    <div class="import-price-name hidden mt-2">
                                        <select class="import-price-name-select hidden" data-hs-select='${searchableSelectConfigJson}'>
                                            ${buildNameOptions(header, existingPriceTypeNames)}
                                        </select>
                                    </div>
                                    <div class="import-warehouse-name hidden mt-2">
                                        <select class="import-warehouse-name-select hidden" data-hs-select='${searchableSelectConfigJson}'>
                                            ${buildNameOptions(header, existingWarehouseNames)}
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    if (window.HSSelect) {
                        container.querySelectorAll('.import-target-select, .import-price-name-select, .import-warehouse-name-select').forEach((el) => new HSSelect(el));
                        container.querySelectorAll('.import-target-select').forEach((el) => {
                            el.addEventListener('change', () => toggleImportCompanionField(el));
                        });
                    }
                }

                window.toggleImportCompanionField = function (selectEl) {
                    const row = selectEl.closest('.import-mapping-row');
                    row.querySelector('.import-price-name').classList.toggle('hidden', selectEl.value !== 'price');
                    row.querySelector('.import-warehouse-name').classList.toggle('hidden', selectEl.value !== 'warehouse_stock');
                };

                window.submitImport = async function () {
                    const mapping = Array.from(document.querySelectorAll('.import-mapping-row'))
                        .map((row) => {
                            const target = row.querySelector('.import-target-select').value;
                            if (! target || target === importIgnoreValue) {
                                return null;
                            }

                            return {
                                column: parseInt(row.dataset.column, 10),
                                target,
                                price_type_name: target === 'price' ? row.querySelector('.import-price-name-select').value : null,
                                warehouse_name: target === 'warehouse_stock' ? row.querySelector('.import-warehouse-name-select').value : null,
                            };
                        })
                        .filter(Boolean);

                    if (mapping.length === 0) {
                        alert('{{ __('Map at least one column.') }}');
                        return;
                    }

                    const submitBtn = document.getElementById('import-submit-btn');
                    const backBtn = document.getElementById('import-back-btn');
                    const labelEl = document.getElementById('import-submit-label');
                    const spinnerEl = document.getElementById('import-submit-spinner');
                    submitBtn.disabled = true;
                    backBtn.disabled = true;
                    labelEl.classList.add('hidden');
                    spinnerEl.classList.remove('hidden');
                    window.appModalProcessing.start('#product-import-modal');

                    try {
                        const response = await fetch(@json(route('products.import')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ token: importToken, mapping }),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            alert(data.message || '{{ __('Could not import the file.') }}');
                            return;
                        }

                        let summary = '{{ __('Created') }}: ' + data.created + ' — {{ __('Updated') }}: ' + data.updated + ' — {{ __('Skipped') }}: ' + data.skipped;

                        if (data.skipped > 0 && data.skipped_reasons) {
                            const reasons = [];
                            if (data.skipped_reasons.no_code) reasons.push(data.skipped_reasons.no_code + ' {{ __('without a mapped code') }}');
                            if (data.skipped_reasons.no_description) reasons.push(data.skipped_reasons.no_description + ' {{ __('without a mapped description') }}');
                            if (data.skipped_reasons.no_price) reasons.push(data.skipped_reasons.no_price + ' {{ __('without any price mapped') }}');
                            if (reasons.length) {
                                summary += '\n' + '{{ __('Skipped rows') }}: ' + reasons.join(', ');
                            }
                        }

                        document.getElementById('import-result-summary').textContent = summary;
                        document.getElementById('import-result-summary').classList.add('whitespace-pre-line');
                        document.getElementById('import-step-mapping').classList.add('hidden');
                        document.getElementById('import-step-result').classList.remove('hidden');
                    } catch (error) {
                        alert(error.message || '{{ __('Could not import the file.') }}');
                    } finally {
                        submitBtn.disabled = false;
                        backBtn.disabled = false;
                        labelEl.classList.remove('hidden');
                        spinnerEl.classList.add('hidden');
                        window.appModalProcessing.stop('#product-import-modal');
                    }
                };

                function setSelectValue(selectId, value) {
                    const el = document.getElementById(selectId);
                    const instance = window.HSSelect && HSSelect.getInstance(el);
                    if (instance) {
                        instance.setValue(value ?? '');
                    } else {
                        el.value = value ?? '';
                    }
                }

                function setProductImagePreview(url) {
                    const img = document.getElementById('pr-image-preview');
                    const placeholder = document.getElementById('pr-image-placeholder');
                    const removeBtn = document.getElementById('pr-image-remove-btn');

                    if (url) {
                        img.src = url;
                        img.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                        removeBtn.classList.remove('hidden');
                        removeBtn.classList.add('flex');
                    } else {
                        img.src = '';
                        img.classList.add('hidden');
                        placeholder.classList.remove('hidden');
                        removeBtn.classList.add('hidden');
                        removeBtn.classList.remove('flex');
                    }
                }

                window.removeProductImage = function () {
                    document.getElementById('pr-image').value = '';
                    document.getElementById('pr-remove_image').value = '1';
                    setProductImagePreview(null);
                };

                /**
                 * Abre el panel de crear/editar producto, precargando todos
                 * sus campos. form.dataset.isNew marca si el costo inicial
                 * aplica: solo tiene sentido al CREAR (define el primer
                 * costo promedio del producto) -- al editar, para sumar
                 * stock con su propio costo se usa "Register entry", que sí
                 * queda registrado como un movimiento del kardex. El stock
                 * total se fija directo por fuera del componente +/-
                 * (HSInputNumber), así que hay que reconstruirlo para que el
                 * contador arranque sincronizado con lo que se ve (si no, el
                 * primer clic en +/- parte del valor viejo, no del nuevo).
                 * @param {object} [product] Producto a editar, o vacío para crear uno nuevo.
                 * @returns {void}
                 */
                window.openProductPanel = function (product) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#product-panel');
                    }

                    const form = document.getElementById('productForm');
                    form.dataset.isNew = product?.id ? 'false' : 'true';
                    document.getElementById('pr-initial_unit_cost').value = '';
                    document.getElementById('pr-initial_unit_cost-display').value = '';

                    document.getElementById('pr-code').value = product?.code ?? '';
                    document.getElementById('pr-barcode').value = product?.barcode ?? '';
                    document.getElementById('pr-description').value = product?.description ?? '';
                    document.getElementById('pr-image').value = '';
                    document.getElementById('pr-remove_image').value = '0';
                    setProductImagePreview(product?.image_url ?? null);
                    setSelectValue('pr-unit_code', product?.unit_code);
                    resetExtraPriceLines(product?.extra_prices);
                    if (! product?.extra_prices || product.extra_prices.length === 0) {
                        addExtraPriceLine();
                    }
                    document.getElementById('pr-tracks_inventory').checked = !!product?.tracks_inventory;

                    const stockTotalInput = document.getElementById('pr-stock_total');
                    stockTotalInput.value = product?.stock ?? 0;
                    if (window.HSInputNumber) {
                        const wrapper = stockTotalInput.closest('[data-hs-input-number]');
                        HSInputNumber.getInstance(wrapper, true)?.element?.destroy();
                        new HSInputNumber(wrapper);
                    }

                    resetProductWarehouseLines(product?.warehouse_stocks);
                    toggleStockField();

                    if (product?.id) {
                        form.action = @json(route('products.update', ['product' => '__ID__'])).replace('__ID__', product.id);
                        document.getElementById('pr-method').value = 'PUT';
                    } else {
                        form.action = @json(route('products.store'));
                        document.getElementById('pr-method').value = 'POST';
                    }
                };

                window.openWarehousePanel = function (warehouse) {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#warehouse-panel');
                    }

                    const form = document.getElementById('warehouseForm');

                    document.getElementById('wh-name').value = warehouse?.name ?? '';
                    document.getElementById('wh-address').value = warehouse?.address ?? '';

                    if (warehouse?.id) {
                        form.action = @json(route('warehouses.update', ['warehouse' => '__ID__'])).replace('__ID__', warehouse.id);
                        document.getElementById('wh-method').value = 'PUT';
                    } else {
                        form.action = @json(route('warehouses.store'));
                        document.getElementById('wh-method').value = 'POST';
                    }
                };

                /**
                 * Wiring inicial de la pantalla de productos: toggle de
                 * inventario, preview de imagen, campos decimales, tabla y
                 * el dropzone de importación. Los botones +/- del stock no
                 * disparan un evento "input" nativo, por eso también se
                 * escucha "change.hs.inputNumber". El dropzone de
                 * importación no sube el archivo (autoProcessQueue: false):
                 * solo se usa para elegirlo, y se copia al <input
                 * type="file"> real que sí viaja con la petición al
                 * analizar el archivo; como no hay subida real, la barra de
                 * progreso nunca se movería sola, así que se marca como
                 * completa apenas se elige el archivo.
                 * @returns {void}
                 */
                function init() {
                    const checkbox = document.getElementById('pr-tracks_inventory');

                    if (!checkbox || checkbox.dataset.bound === 'true') {
                        return;
                    }
                    checkbox.dataset.bound = 'true';

                    checkbox.addEventListener('change', toggleStockField);

                    document.getElementById('pr-image').addEventListener('change', (event) => {
                        const file = event.target.files?.[0];
                        document.getElementById('pr-remove_image').value = '0';
                        if (!file) {
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = () => setProductImagePreview(reader.result);
                        reader.readAsDataURL(file);
                    });

                    document.getElementById('se-quantity-display').addEventListener('input', (event) => {
                        handleDecimalDisplayInput(event.target.value, 'se-quantity', 'se-quantity-display');
                    });
                    document.getElementById('se-unit_cost-display').addEventListener('input', (event) => {
                        handleDecimalDisplayInput(event.target.value, 'se-unit_cost', 'se-unit_cost-display');
                    });
                    document.getElementById('pr-initial_unit_cost-display').addEventListener('input', (event) => {
                        handleDecimalDisplayInput(event.target.value, 'pr-initial_unit_cost', 'pr-initial_unit_cost-display');
                    });
                    document.getElementById('ac-average_cost-display').addEventListener('input', (event) => {
                        handleDecimalDisplayInput(event.target.value, 'ac-average_cost', 'ac-average_cost-display');
                    });

                    document.getElementById('pr-stock_total').addEventListener('input', recalcUnassignedStock);
                    document.getElementById('pr-stock_total').closest('[data-hs-input-number]')?.addEventListener('change.hs.inputNumber', recalcUnassignedStock);

                    document.getElementById('productForm').addEventListener('submit', (event) => {
                        if (checkbox.checked && parseFloat(document.getElementById('pr-stock_unassigned').value) < 0) {
                            event.preventDefault();
                            alert('{{ __('The sum of warehouse quantities cannot exceed the total stock.') }}');
                        }
                    });

                    initWorkflowDataTable('#productsTable', '#hs-table-with-pagination-search', {
                        columnDefs: [{ targets: -1, orderable: false }],
                    });

                    const importUploadEl = document.getElementById('import-file-upload');
                    const importFileInput = document.getElementById('import-file-input');

                    if (window.HSFileUpload) {
                        HSFileUpload.autoInit();
                    }
                    const importUploadInstance = window.HSFileUpload && HSFileUpload.getInstance(importUploadEl, true);
                    if (importUploadInstance?.element?.dropzone) {
                        const dropzone = importUploadInstance.element.dropzone;

                        dropzone.on('addedfile', function (file) {
                            const transfer = new DataTransfer();
                            transfer.items.add(file);
                            importFileInput.files = transfer.files;

                            const previewElement = file.previewElement;
                            if (previewElement) {
                                previewElement.classList.add('complete');
                                const bar = previewElement.querySelector('[data-hs-file-upload-progress-bar]');
                                const pane = previewElement.querySelector('[data-hs-file-upload-progress-bar-pane]');
                                const value = previewElement.querySelector('[data-hs-file-upload-progress-bar-value]');
                                if (bar) bar.setAttribute('aria-valuenow', '100');
                                if (pane) pane.style.width = '100%';
                                if (value) value.textContent = '100';
                            }
                        });

                        dropzone.on('removedfile', function () {
                            importFileInput.value = '';
                        });
                    }

                    @if ($errors->any())
                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#product-panel');
                        }
                    @endif
                }

                document.addEventListener('DOMContentLoaded', init);
                document.addEventListener('livewire:navigated', init);
            })();
        </script>
    @endpush
</x-layouts.app>
