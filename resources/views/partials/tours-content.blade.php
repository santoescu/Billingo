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
        'issue-document' => [
            'steps' => [
                [
                    'selector' => '#doc-resolution-field',
                    'title' => __('Resolution'),
                    'description' => __('The DIAN-authorized numbering range you\'re issuing under -- the prefix and consecutive fill in on their own once you pick it.'),
                ],
                [
                    'selector' => '#doc-cliente-search',
                    'title' => __('Search existing client'),
                    'description' => __("Search by name or ID -- picking one fills in their data. If they're not registered yet, fill in the fields below by hand."),
                ],
                [
                    'selector' => '.line-product-search',
                    'title' => __('Add a line'),
                    'description' => __('Search a product by code, barcode or description to add it as a line -- a new empty line appears automatically for the next one.'),
                ],
                [
                    'selector' => '#documentLinesTotal',
                    'title' => __('Total'),
                    'description' => __('Updates live as you add lines, discounts and taxes.'),
                ],
                [
                    'selector' => '#documentSubmitBtn',
                    'title' => __('Issue document'),
                    'description' => __('Validates and sends it to the DIAN right away -- once issued, it can\'t be edited, only corrected with a credit or debit note.'),
                ],
            ],
        ],
        'pos-open-shift' => [
            'steps' => [
                [
                    'selector' => '#opening-balance-display',
                    'title' => __('Opening balance'),
                    'description' => __('How much cash you\'re starting the shift with -- used later to compare against what you actually count when you close it.'),
                ],
                [
                    'selector' => '#shift-fv-resolution-field',
                    'title' => __('Sales invoice resolution'),
                    'description' => __('The numbering range used for every sale in this shift, whether or not you also issue it as an electronic invoice.'),
                ],
                [
                    'selector' => '#shift-invoicing-resolution-field',
                    'title' => __('Electronic invoice resolution'),
                    'description' => __('Only needed if you plan to issue electronic invoices from the POS during this shift, not just the sales receipt.'),
                ],
                [
                    'selector' => '#shift-open-btn',
                    'title' => __('Open shift'),
                    'description' => __('Once it\'s open you can start selling -- you won\'t be able to sell without an open shift.'),
                ],
            ],
        ],
        'pos-close-shift' => [
            'steps' => [
                [
                    'selector' => '.shift-close-btn',
                    'title' => __('Close shift'),
                    'description' => __('Click here to count your cash and close the shift.'),
                    'panel' => '#admin-close-shift-modal',
                ],
                [
                    'selector' => '#admin-close-counted-display',
                    'title' => __('Counted cash'),
                    'description' => __('What you actually count in the drawer -- compare it against "Expected cash" above to spot any difference.'),
                ],
                [
                    'selector' => '#admin-close-shift-form button[type="submit"]',
                    'title' => __('Close shift'),
                    'description' => __('That\'s it -- the shift closes with whatever you counted, and you\'ll need to open a new one to keep selling.'),
                ],
            ],
        ],
        'pos-sell' => [
            'steps' => [
                [
                    'selector' => '#pos-ticket-add-btn',
                    'title' => __('Pre-bills'),
                    'description' => __('Open a new tab to attend a different client without losing the cart you already had going -- each tab keeps its own cart and client.'),
                ],
                [
                    'selector' => '#pos-product-search',
                    'title' => __('Search a product'),
                    'description' => __('Search by code, barcode or description -- click a card in the grid below to add it to the cart.'),
                ],
                [
                    'selector' => '#pos-cart-body',
                    'title' => __('Cart'),
                    'description' => __('Every product you\'ve added -- change the quantity or remove a line right here.'),
                ],
                [
                    'selector' => '#pos-client-search',
                    'title' => __('Client'),
                    'description' => __('Search an existing client, or leave it as the default "final consumer" if you don\'t need to identify who\'s buying.'),
                ],
                [
                    'selector' => '#pos-total-display',
                    'title' => __('Total'),
                    'description' => __('Updates live as you add or remove products from the cart.'),
                ],
                [
                    'selector' => '#pos-checkout-btn',
                    'title' => __('Charge sale'),
                    'description' => __('Opens the payment screen -- once charged you can print the receipt or, if the module is active, also issue it as an electronic invoice.'),
                ],
            ],
        ],
        'create-quotation' => [
            'steps' => [
                [
                    'selector' => '#quote-ticket-add-btn',
                    'title' => __('Pre-quotations'),
                    'description' => __('Open a new tab to start a different quotation without losing the cart you already had going -- each tab keeps its own cart and client.'),
                ],
                [
                    'selector' => '#quote-product-search',
                    'title' => __('Search a product'),
                    'description' => __('Search by code, barcode or description -- click a card in the grid below to add it to the cart.'),
                ],
                [
                    'selector' => '#quote-cart-body',
                    'title' => __('Cart'),
                    'description' => __('Every product you\'ve added -- change the quantity or remove a line right here.'),
                ],
                [
                    'selector' => '#quote-client-search',
                    'title' => __('Client'),
                    'description' => __('Search an existing client, or leave it as the default one if you don\'t have their details yet.'),
                ],
                [
                    'selector' => '#quote-total-display',
                    'title' => __('Total'),
                    'description' => __('Updates live as you add or remove products from the cart.'),
                ],
                [
                    'selector' => '#quote-submit-btn',
                    'title' => __('Issue quotation'),
                    'description' => __('Generates the quotation PDF -- your client can later turn it into a sale or an invoice without typing everything again.'),
                ],
            ],
        ],
        'quotations-search' => [
            'steps' => [
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Filter by client or number to find a quotation you already issued -- open it to view or download its PDF.'),
                ],
            ],
        ],
        'documents-search' => [
            'steps' => [
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Filter by client, number or prefix to quickly find a document you already issued -- click the eye icon on any row to open it.'),
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
        'completed' => __('You already completed this guide'),
    ];
@endphp
<script>
    window.appTours = @json($appTours);
    window.appTourLabels = @json($appTourLabels);
    window.appHelpUrl = @json(route('help.index'));
</script>
