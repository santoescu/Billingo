@php
    $myModules = session('selected_company.modules', []);
    $hasInvoicing = array_key_exists('invoicing', $myModules);
    $hasPos = array_key_exists('pos', $myModules);
    $hasCotizaciones = array_key_exists('cotizaciones', $myModules);
    $hasInventory = $hasInvoicing || $hasPos || $hasCotizaciones;

    $exampleProduct = session('selected_company.id')
        ? \App\Models\Product::where('company_id', (string) session('selected_company.id'))->first()
        : null;

    $hasReceiving = array_key_exists('receiving', $myModules);

    $sections = [
        [
            'label' => __('General'),
            'guides' => array_values(array_filter([
                [
                    'label' => __('Create a company'),
                    'description' => __('Fill in the identification, fiscal info and digital certificate to register a new company to issue documents under.'),
                    'url' => route('help.index', ['tour' => 'company-create']),
                ],
                session('selected_company') ? [
                    'label' => __('Edit company'),
                    'description' => __('Update an existing company\'s fiscal info, DIAN pin/software ID and digital certificate.'),
                    'url' => route('help.index', ['tour' => 'company-dian-setup']),
                ] : null,
                $hasInventory ? [
                    'label' => __('Resolutions'),
                    'description' => __('Sync the numbering ranges the DIAN authorized, or create your own for sales tickets, notes and quotations.'),
                    'url' => route('help.index', ['tour' => 'dian-resolutions']),
                ] : null,
            ])),
        ],
        [
            'label' => __('Company'),
            'guides' => array_values(array_filter([
                $hasInventory ? [
                    'label' => __('Clients'),
                    'description' => __('Register the person or company you will invoice, sell to, or quote.'),
                    'url' => route('help.index', ['tour' => 'create-client']),
                ] : null,
                $hasInventory ? [
                    'label' => __('Edit a client'),
                    'description' => __('Search for a client you already registered and update their info.'),
                    'url' => route('help.index', ['tour' => 'edit-client']),
                ] : null,
                $hasReceiving ? [
                    'label' => __('Providers'),
                    'description' => __('Register the person or company you buy from.'),
                    'url' => route('help.index', ['tour' => 'create-provider']),
                ] : null,
                $hasReceiving ? [
                    'label' => __('Edit a provider'),
                    'description' => __('Search for a provider you already registered and update their info.'),
                    'url' => route('help.index', ['tour' => 'edit-provider']),
                ] : null,
                session('selected_company') ? [
                    'label' => __('Members'),
                    'description' => __("Invite people to your company and set what they can access."),
                    'url' => route('help.index', ['tour' => 'members']),
                ] : null,
                session('selected_company') ? [
                    'label' => __('Edit a member'),
                    'description' => __("Change what a member can access, module by module."),
                    'url' => route('help.index', ['tour' => 'edit-member']),
                ] : null,
                session('selected_company') ? [
                    'label' => __('Remove a member'),
                    'description' => __('Take a member out of the company.'),
                    'url' => route('help.index', ['tour' => 'delete-member']),
                ] : null,
            ])),
        ],
        [
            'label' => __('Inventory'),
            'guides' => $hasInventory ? array_values(array_filter([
                [
                    'label' => __('Create a product'),
                    'description' => __('Create a product, decide whether to track its stock, and assign it price types and warehouses.'),
                    'url' => route('help.index', ['tour' => 'inventory']),
                ],
                [
                    'label' => __('Create a warehouse'),
                    'description' => __('Register a physical or logical location to keep stock in.'),
                    'url' => route('help.index', ['tour' => 'warehouse']),
                ],
                [
                    'label' => __('Create a price type'),
                    'description' => __('Register a price list (retail, wholesale, etc.) you can then set on each product.'),
                    'url' => route('help.index', ['tour' => 'price-type']),
                ],
                [
                    'label' => __('Search and edit a product'),
                    'description' => __('Use the search box to quickly find the product you want to edit.'),
                    'url' => route('help.index', ['tour' => 'edit-product']),
                ],
                [
                    'label' => __('Fix cost'),
                    'description' => __('Correct the average cost of a product.'),
                    'url' => route('help.index', ['tour' => 'fix-cost']),
                ],
                [
                    'label' => __('Register entry'),
                    'description' => __('Add more stock to a product that already tracks inventory.'),
                    'url' => route('help.index', ['tour' => 'stock-entry']),
                ],
                [
                    'label' => __('Delete a product'),
                    'description' => __('Remove a product from your catalog.'),
                    'url' => route('help.index', ['tour' => 'delete-product']),
                ],
                $exampleProduct ? [
                    'label' => __('View a product'),
                    'description' => __('General info, prices, stock and the full movement history (kardex) of a product.'),
                    'url' => route('help.index', ['tour' => 'product-show']),
                ] : null,
                [
                    'label' => __('Export to Excel'),
                    'description' => __('Download your catalog as a spreadsheet, choosing which columns to include.'),
                    'url' => route('help.index', ['tour' => 'products-export']),
                ],
                [
                    'label' => __('Import from Excel'),
                    'description' => __('Upload a spreadsheet to create or update many products at once.'),
                    'url' => route('help.index', ['tour' => 'products-import']),
                ],
            ])) : [],
        ],
        [
            'label' => config('modules.invoicing.name'),
            'guides' => $hasInvoicing ? array_values(array_filter([
                [
                    'label' => __('Issue document'),
                    'description' => __('Pick a resolution, search a client, add lines and issue an electronic document.'),
                    'url' => route('help.index', ['tour' => 'issue-document']),
                ],
                [
                    'label' => __('View a document'),
                    'description' => __('Find a document you already issued and see its full detail -- also covers marking a credit invoice as paid.'),
                    'url' => route('help.index', ['tour' => 'documents-search']),
                ],
            ])) : [],
        ],
        [
            'label' => config('modules.pos.name'),
            'guides' => $hasPos ? array_values(array_filter([
                [
                    'label' => __('Open shift'),
                    'description' => __('Set your opening cash balance and the resolutions you\'ll sell under.'),
                    'url' => route('help.index', ['tour' => 'pos-open-shift']),
                ],
                [
                    'label' => __('Sell'),
                    'description' => __('Add products to the cart, pick a client and charge the sale.'),
                    'url' => route('help.index', ['tour' => 'pos-sell']),
                ],
                [
                    'label' => __('Close shift'),
                    'description' => __('Count your cash and close the shift at the end of the day.'),
                    'url' => route('help.index', ['tour' => 'pos-close-shift']),
                ],
                [
                    'label' => __('Payment methods'),
                    'description' => __('Register your own payment methods, optionally mapped to their DIAN equivalent.'),
                    'url' => route('help.index', ['tour' => 'payment-methods']),
                ],
                [
                    'label' => __('Sellers'),
                    'description' => __('Register who you can pick as the seller when charging a sale.'),
                    'url' => route('help.index', ['tour' => 'sellers']),
                ],
                [
                    'label' => __('View a sale'),
                    'description' => __('Find a sale you already charged and see its full detail -- also covers issuing it as an electronic invoice.'),
                    'url' => route('help.index', ['tour' => 'pos-sales-search']),
                ],
            ])) : [],
        ],
        [
            'label' => config('modules.cotizaciones.name'),
            'guides' => $hasCotizaciones ? array_values(array_filter([
                [
                    'label' => __('Create a quotation'),
                    'description' => __('Add products to the cart, pick a client and issue the quotation PDF.'),
                    'url' => route('help.index', ['tour' => 'create-quotation']),
                ],
                [
                    'label' => __('View a quotation'),
                    'description' => __('Find a quotation you already issued and see its full detail -- also covers converting it into a POS sale or an electronic invoice.'),
                    'url' => route('help.index', ['tour' => 'quotations-search']),
                ],
                [
                    'label' => __('Public catalog link'),
                    'description' => __('Create a link you can share with your clients so they build their own quotation, no account needed.'),
                    'url' => route('help.index', ['tour' => 'catalog-link']),
                ],
            ])) : [],
        ],
        [
            'label' => __('Panel'),
            'guides' => session('selected_company') ? [
                [
                    'label' => __('Metrics panel'),
                    'description' => __('Filter by period, see your activity per module, and export it to PDF.'),
                    'url' => route('help.index', ['tour' => 'panel']),
                ],
            ] : [],
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
                        <a href="{{ $guide['url'] }}" class="relative block space-y-1.5 rounded-lg border border-gray-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-accent hover:shadow-md dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="font-semibold text-gray-800 dark:text-white">{{ $guide['label'] }}</p>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $guide['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
