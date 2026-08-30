@php
    $myModules = session('selected_company.modules', []);
    $hasInvoicing = array_key_exists('invoicing', $myModules);
    $hasPos = array_key_exists('pos', $myModules);
    $hasCotizaciones = array_key_exists('cotizaciones', $myModules);
    $hasInventory = $hasInvoicing || $hasPos || $hasCotizaciones;

    $exampleProduct = session('selected_company.id')
        ? \App\Models\Product::where('company_id', (string) session('selected_company.id'))->first()
        : null;

    $sections = [
        [
            'label' => __('General'),
            'guides' => [
                [
                    'label' => __('Company'),
                    'description' => __('Create a new company to issue documents under.'),
                    'url' => route('dashboard', ['tour' => 'company']),
                ],
            ],
        ],
        [
            'label' => __('Company'),
            'guides' => array_values(array_filter([
                $hasInventory ? [
                    'label' => __('Clients'),
                    'description' => __('Register the person or company you will invoice, sell to, or quote.'),
                    'url' => route('clients.index', ['tour' => 'create-client']),
                ] : null,
                session('selected_company') ? [
                    'label' => __('Members'),
                    'description' => __("Invite people to your company and set what they can access."),
                    'url' => route('companies.members.index', ['tour' => 'members']),
                ] : null,
            ])),
        ],
        [
            'label' => __('Inventory'),
            'guides' => $hasInventory ? array_values(array_filter([
                [
                    'label' => __('Create a product'),
                    'description' => __('Create a product, decide whether to track its stock, and assign it price types and warehouses.'),
                    'url' => route('products.index', ['tour' => 'inventory']),
                ],
                [
                    'label' => __('Create a warehouse'),
                    'description' => __('Register a physical or logical location to keep stock in.'),
                    'url' => route('products.index', ['tour' => 'warehouse']),
                ],
                [
                    'label' => __('Create a price type'),
                    'description' => __('Register a price list (retail, wholesale, etc.) you can then set on each product.'),
                    'url' => route('products.index', ['tour' => 'price-type']),
                ],
                [
                    'label' => __('Search and edit a product'),
                    'description' => __('Use the search box to quickly find the product you want to edit.'),
                    'url' => route('products.index', ['tour' => 'edit-product']),
                ],
                [
                    'label' => __('Fix cost'),
                    'description' => __('Correct the average cost of a product.'),
                    'url' => route('products.index', ['tour' => 'fix-cost']),
                ],
                [
                    'label' => __('Register entry'),
                    'description' => __('Add more stock to a product that already tracks inventory.'),
                    'url' => route('products.index', ['tour' => 'stock-entry']),
                ],
                [
                    'label' => __('Delete a product'),
                    'description' => __('Remove a product from your catalog.'),
                    'url' => route('products.index', ['tour' => 'delete-product']),
                ],
                $exampleProduct ? [
                    'label' => __('View a product'),
                    'description' => __('General info, prices, stock and the full movement history (kardex) of a product.'),
                    'url' => route('products.show', ['product' => $exampleProduct->_id, 'tour' => 'product-show']),
                ] : null,
            ])) : [],
        ],
    ];

    $sections = array_values(array_filter($sections, fn ($section) => ! empty($section['guides'])));
@endphp

<x-layouts.app :title="__('Help')">
    @include('partials.tittle', [
        'title' => __('Help'),
        'subheading' => __('Guided walkthroughs for the most common actions.'),
    ])

    <div class="space-y-8">
        @foreach ($sections as $section)
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-neutral-500">{{ $section['label'] }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($section['guides'] as $guide)
                        <a href="{{ $guide['url'] }}" class="block space-y-1.5 rounded-lg border border-gray-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-accent hover:shadow-md dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="font-semibold text-gray-800 dark:text-white">{{ $guide['label'] }}</p>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $guide['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
