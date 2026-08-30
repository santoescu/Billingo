@php
    $appTours = [
        'create-client' => [
            'steps' => [
                [
                    'selector' => '#new-third-party-btn',
                    'title' => __('Create a client'),
                    'description' => __('Click here to open the new client form.'),
                    'panel' => '#third-party-panel',
                ],
                [
                    'selector' => '#tp-identificacion',
                    'title' => __('Identification'),
                    'description' => __("Enter the client's ID number. If it's already registered with the DIAN, some fields fill in automatically."),
                ],
                [
                    'selector' => '#tp-name',
                    'title' => __('Name'),
                    'description' => __("The client's name or business name."),
                ],
                [
                    'selector' => '#thirdPartyForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('That\'s it -- the client is now ready to be invoiced, sold to, or quoted.'),
                ],
            ],
        ],
        'inventory' => [
            'steps' => [
                [
                    'selector' => '#new-product-btn',
                    'title' => __('Create a product'),
                    'description' => __('Click here to open the new product form.'),
                    'panel' => '#product-panel',
                ],
                [
                    'selector' => '#pr-image-btn',
                    'title' => __('Image'),
                    'description' => __('Optional -- it shows up on the product list, the public catalog and the POS grid.'),
                ],
                [
                    'selector' => '#pr-code',
                    'title' => __('Code'),
                    'description' => __('An internal code to identify this product -- you choose the format. It has to be unique, and it\'s what you search by everywhere else in the app.'),
                ],
                [
                    'selector' => '#pr-barcode',
                    'title' => __('Barcode'),
                    'description' => __('Optional -- if it has one, scanning it in the POS or on the invoice finds this product automatically.'),
                ],
                [
                    'selector' => '#pr-description',
                    'title' => __('Description'),
                    'description' => __('The name that shows up on documents and in the catalog.'),
                ],
                [
                    'selector' => '#pr-unit_code-field',
                    'title' => __('Unit'),
                    'description' => __('The DIAN unit of measure (unit, kilogram, hour, etc.) -- required so electronic documents go out correctly.'),
                ],
                [
                    'selector' => '#pr-add-price-btn',
                    'title' => __('Price types'),
                    'description' => __('Add as many prices as you need (retail, wholesale, etc.) -- the first one is the default. You need to have created the price type first (see the "Create a price type" guide) to be able to pick it here.'),
                ],
                [
                    'selector' => '#pr-tracks_inventory',
                    'title' => __('Track inventory'),
                    'description' => __('Turn it on if stock should be discounted every time you sell or invoice this product. Leave it off for services or anything you don\'t need to count.'),
                    'clickAndAdvance' => '#pr-tracks_inventory',
                ],
                [
                    'selector' => '#pr-stock_total',
                    'title' => __('Total stock'),
                    'description' => __('How many units you have right now, in total. Changing this later records a stock adjustment.'),
                ],
                [
                    'selector' => '#pr-add-warehouse-btn',
                    'title' => __('Stock by warehouse'),
                    'description' => __('Split the total stock across your warehouses -- whatever is left unassigned shows up separately, below. You need to have created the warehouse first (see the "Create a warehouse" guide) to be able to pick it here.'),
                ],
                [
                    'selector' => '#productForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('That\'s it -- the product is ready to sell, invoice, or quote.'),
                ],
            ],
        ],
        'edit-product' => [
            'steps' => [
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to quickly find the product you want to edit.'),
                ],
                [
                    'selector' => '.product-edit-btn',
                    'title' => __('Edit'),
                    'description' => __('Opens the same form used to create it, already filled in -- change whatever you need and save.'),
                    'panel' => '#product-panel',
                ],
            ],
        ],
        'product-show' => [
            'steps' => [
                [
                    'selector' => '#product-show-general',
                    'title' => __('General'),
                    'description' => __('The code, barcode and unit of measure -- click "Edit" from the product list to change any of this.'),
                ],
                [
                    'selector' => '#product-show-prices',
                    'title' => __('Prices'),
                    'description' => __('Every price type this product has, with its value -- also edited from the product list.'),
                ],
                [
                    'selector' => '#product-show-stock',
                    'title' => __('Stock'),
                    'description' => __('Total stock, average unit cost, and how it\'s split across warehouses. If a product doesn\'t track inventory, this section says so instead.'),
                ],
                [
                    'selector' => '#product-show-kardex',
                    'title' => __('Kardex'),
                    'description' => __('The full movement history for this product: sales, invoices, manual entries and cost corrections, newest first. Click a movement to open the document it came from.'),
                ],
                [
                    'selector' => '#product-show-image',
                    'title' => __('Image'),
                    'description' => __('Click it to upload or replace the product\'s photo -- it updates right away, no need to save anything else.'),
                ],
            ],
        ],
        'fix-cost' => [
            'steps' => [
                [
                    'selector' => '.product-more-actions-btn',
                    'title' => __('More actions'),
                    'description' => __('Click here to see more actions for this product.'),
                    'clickAndAdvance' => '.product-more-actions-btn',
                ],
                [
                    'selector' => '.product-fix-cost-btn',
                    'title' => __('Fix cost'),
                    'description' => __('Correct the average cost without touching quantities or creating a kardex movement.'),
                    'panel' => '#average-cost-modal',
                ],
                [
                    'selector' => '#ac-average_cost-display',
                    'title' => __('Unit cost'),
                    'description' => __('The new average cost used to value the current stock.'),
                ],
            ],
        ],
        'stock-entry' => [
            'steps' => [
                [
                    'selector' => '.product-more-actions-btn',
                    'title' => __('More actions'),
                    'description' => __('Click here to see more actions for this product.'),
                    'clickAndAdvance' => '.product-more-actions-btn',
                ],
                [
                    'selector' => '.product-register-entry-btn',
                    'title' => __('Register entry'),
                    'description' => __('Add more stock to this product with its own cost, without having to edit it.'),
                    'panel' => '#stock-entry-panel',
                ],
                [
                    'selector' => '#se-quantity-display',
                    'title' => __('Quantity'),
                    'description' => __('How much you\'re adding in this entry.'),
                ],
            ],
        ],
        'delete-product' => [
            'steps' => [
                [
                    'selector' => '.product-more-actions-btn',
                    'title' => __('More actions'),
                    'description' => __('Click here to see more actions for this product.'),
                    'clickAndAdvance' => '.product-more-actions-btn',
                ],
                [
                    'selector' => '.product-delete-btn',
                    'title' => __('Delete'),
                    'description' => __('It will ask you to confirm before deleting it -- this action cannot be undone.'),
                ],
            ],
        ],
        'warehouse' => [
            'steps' => [
                [
                    'selector' => '#tab-warehouses-btn',
                    'title' => __('Warehouses'),
                    'description' => __('Click here to switch to the warehouses tab.'),
                    'clickAndAdvance' => '#tab-warehouses-btn',
                ],
                [
                    'selector' => '#new-warehouse-btn',
                    'title' => __('Create a warehouse'),
                    'description' => __('Click here to open the new warehouse form.'),
                    'panel' => '#warehouse-panel',
                ],
                [
                    'selector' => '#wh-name',
                    'title' => __('Name'),
                    'description' => __('A name to identify this warehouse (e.g. "Main store", "Central warehouse").'),
                ],
                [
                    'selector' => '#wh-address',
                    'title' => __('Address'),
                    'description' => __('Optional -- useful if you have more than one physical location.'),
                ],
            ],
        ],
        'price-type' => [
            'steps' => [
                [
                    'selector' => '#tab-price-types-btn',
                    'title' => __('Price types'),
                    'description' => __('Click here to switch to the price types tab.'),
                    'clickAndAdvance' => '#tab-price-types-btn',
                ],
                [
                    'selector' => '#new-price-type-btn',
                    'title' => __('Create a price type'),
                    'description' => __('Click here to open the new price type form.'),
                    'panel' => '#price-type-panel',
                ],
                [
                    'selector' => '#pt-name',
                    'title' => __('Name'),
                    'description' => __('A name to identify this price (e.g. "Retail", "Wholesale") -- you can then set it on each product.'),
                ],
            ],
        ],
        'members' => [
            'steps' => [
                [
                    'selector' => '#add-member-btn',
                    'title' => __('Add a member'),
                    'description' => __('Invite someone who already has an account on the platform to this company.'),
                    'panel' => '#add-member',
                ],
                [
                    'selector' => '#member-email',
                    'title' => __('Email'),
                    'description' => __('The email that person already used to register.'),
                ],
                [
                    'selector' => '#member-modules-fields',
                    'title' => __('Modules and role'),
                    'description' => __('For each active module, choose that person\'s role there (or "No access" if they shouldn\'t enter that module).'),
                ],
            ],
        ],
        'company' => [
            'steps' => [
                [
                    'selector' => '#new-company-btn',
                    'title' => __('Create a company'),
                    'description' => __('Each company has its own fiscal information, digital certificate and contracted modules.'),
                ],
            ],
        ],
    ];

    $appTourLabels = [
        'next' => __('Next'),
        'prev' => __('Back'),
        'done' => __('Done'),
        'progress' => __('{{current}} of {{total}}'),
    ];
@endphp
<script>
    window.appTours = @json($appTours);
    window.appTourLabels = @json($appTourLabels);
    window.appHelpUrl = @json(route('help.index'));
</script>
