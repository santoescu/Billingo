@php
    $appTours = [
        'create-client' => [
            'steps' => [
                [
                    'selector' => '#sidebar-clients',
                    'title' => __('Clients'),
                    'description' => __('Click "Clients" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#new-third-party-btn',
                    'title' => __('Create a client'),
                    'description' => __('Click here to open the new client form.'),
                    'panel' => '#third-party-panel',
                ],
                [
                    'selector' => '#tp-identification_type-field',
                    'title' => __('Identification type and DV'),
                    'description' => __('The kind of ID this client has -- for a NIT, the check digit (DV) fills in on its own once the DIAN lookup finds a match.'),
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
                    'selector' => '#tp-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#tp-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this client -- required for the electronic documents you issue to them to go out correctly.'),
                ],
                [
                    'selector' => '#tp-address',
                    'title' => __('Address'),
                    'description' => __('Required by the DIAN on every electronic document -- without it, a document issued to this client is rejected.'),
                ],
                [
                    'selector' => '#tp-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#tp-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info -- also required if you plan to email this client their documents.'),
                ],
                [
                    'selector' => '#thirdPartyForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('That\'s it -- the client is now ready to be invoiced, sold to, or quoted.'),
                ],
            ],
        ],
        'edit-client' => [
            'steps' => [
                [
                    'selector' => '#sidebar-clients',
                    'title' => __('Clients'),
                    'description' => __('Click "Clients" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to quickly find the client you want to edit.'),
                ],
                [
                    'selector' => '.third-party-edit-btn',
                    'title' => __('Edit'),
                    'description' => __('Opens the same form used to create it, already filled in -- change whatever you need and save.'),
                    'panel' => '#third-party-panel',
                ],
                [
                    'selector' => '#tp-identification_type-field',
                    'title' => __('Identification type and DV'),
                    'description' => __('The kind of ID this client has -- for a NIT, the check digit (DV) fills in on its own once the DIAN lookup finds a match.'),
                ],
                [
                    'selector' => '#tp-identificacion',
                    'title' => __('Identification'),
                    'description' => __("The client's ID number. Changing it re-runs the DIAN lookup, which can overwrite some of the fields below."),
                ],
                [
                    'selector' => '#tp-name',
                    'title' => __('Name'),
                    'description' => __("The client's name or business name."),
                ],
                [
                    'selector' => '#tp-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#tp-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this client -- required for the electronic documents you issue to them to go out correctly.'),
                ],
                [
                    'selector' => '#tp-address',
                    'title' => __('Address'),
                    'description' => __('Required by the DIAN on every electronic document -- without it, a document issued to this client is rejected.'),
                ],
                [
                    'selector' => '#tp-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#tp-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info -- also required if you plan to email this client their documents.'),
                ],
                [
                    'selector' => '#thirdPartyForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('Updates the client with whatever you changed.'),
                ],
            ],
        ],
        'create-provider' => [
            'steps' => [
                [
                    'selector' => '#sidebar-providers',
                    'title' => __('Providers'),
                    'description' => __('Click "Providers" in the sidebar, under "Document receiving", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#new-third-party-btn',
                    'title' => __('Create a provider'),
                    'description' => __('Click here to open the new provider form.'),
                    'panel' => '#third-party-panel',
                ],
                [
                    'selector' => '#tp-identification_type-field',
                    'title' => __('Identification type and DV'),
                    'description' => __('The kind of ID this provider has -- for a NIT, the check digit (DV) fills in on its own once the DIAN lookup finds a match.'),
                ],
                [
                    'selector' => '#tp-identificacion',
                    'title' => __('Identification'),
                    'description' => __("Enter the provider's ID number. If it's already registered with the DIAN, some fields fill in automatically."),
                ],
                [
                    'selector' => '#tp-name',
                    'title' => __('Name'),
                    'description' => __("The provider's name or business name."),
                ],
                [
                    'selector' => '#tp-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#tp-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this provider.'),
                ],
                [
                    'selector' => '#tp-address',
                    'title' => __('Address'),
                    'description' => __("This provider's physical address."),
                ],
                [
                    'selector' => '#tp-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#tp-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info for this provider.'),
                ],
                [
                    'selector' => '#thirdPartyForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('That\'s it -- the provider is now ready to associate with received documents.'),
                ],
            ],
        ],
        'edit-provider' => [
            'steps' => [
                [
                    'selector' => '#sidebar-providers',
                    'title' => __('Providers'),
                    'description' => __('Click "Providers" in the sidebar, under "Document receiving", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to quickly find the provider you want to edit.'),
                ],
                [
                    'selector' => '.third-party-edit-btn',
                    'title' => __('Edit'),
                    'description' => __('Opens the same form used to create it, already filled in -- change whatever you need and save.'),
                    'panel' => '#third-party-panel',
                ],
                [
                    'selector' => '#tp-identification_type-field',
                    'title' => __('Identification type and DV'),
                    'description' => __('The kind of ID this provider has -- for a NIT, the check digit (DV) fills in on its own once the DIAN lookup finds a match.'),
                ],
                [
                    'selector' => '#tp-identificacion',
                    'title' => __('Identification'),
                    'description' => __('The provider\'s ID number. Changing it re-runs the DIAN lookup, which can overwrite some of the fields below.'),
                ],
                [
                    'selector' => '#tp-name',
                    'title' => __('Name'),
                    'description' => __("The provider's name or business name."),
                ],
                [
                    'selector' => '#tp-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#tp-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this provider.'),
                ],
                [
                    'selector' => '#tp-address',
                    'title' => __('Address'),
                    'description' => __("This provider's physical address."),
                ],
                [
                    'selector' => '#tp-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#tp-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info for this provider.'),
                ],
                [
                    'selector' => '#thirdPartyForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('Updates the provider with whatever you changed.'),
                ],
            ],
        ],
        'issue-document' => [
            'steps' => [
                [
                    'selector' => '#sidebar-documents',
                    'title' => __('Issued documents'),
                    'description' => __('Click "Issued documents" in the sidebar to reach the list.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#new-document-btn',
                    'title' => __('New document'),
                    'description' => __('Click here to open the new document form.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#doc-tipo_documento-field',
                    'title' => __('Document type'),
                    'description' => __('An invoice for a new sale, or a credit/debit note to correct one you already issued -- picking a note shows the "Referenced invoice" section to link it to the original.'),
                ],
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
                    'selector' => '#doc-cliente_identificacion',
                    'title' => __('Identification'),
                    'description' => __('The client\'s ID number -- if it\'s registered with the DIAN, their name and other fields fill in automatically once you tab out of this field.'),
                ],
                [
                    'selector' => '#doc-cliente_nombre',
                    'title' => __('Name'),
                    'description' => __('The client\'s name or business name, as it should appear on the document.'),
                ],
                [
                    'selector' => '#doc-cliente_direccion',
                    'title' => __('Address, department and city'),
                    'description' => __('Required by the DIAN on every electronic document -- without it, the document is rejected.'),
                ],
                [
                    'selector' => '.line-product-search',
                    'title' => __('Add a line'),
                    'description' => __('Search a product by code, barcode or description to add it as a line -- a new empty line appears automatically for the next one. Each line lets you change quantity, unit price, warehouse, discount and taxes.'),
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
                    'selector' => '#sidebar-pos-sell',
                    'title' => __('Sell'),
                    'description' => __('Click "Sell" in the sidebar, under "Point of sale" -- if you don\'t have an open shift yet, you land here automatically instead of the selling screen.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#pos-tab-shift',
                    'title' => __('Cash register'),
                    'description' => __('Click here to switch to the cash register tab.'),
                    'realNav' => true,
                    'resumeMissingMessage' => __('You already have an open shift, so "Sell" took you straight to the selling screen -- close your current shift first to see this guide.'),
                ],
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
                    'selector' => '#sidebar-pos-sell',
                    'title' => __('Sell'),
                    'description' => __('Click "Sell" in the sidebar, under "Point of sale" -- with an open shift, the "Cash" tab at the top takes you to this screen.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#pos-tab-shift',
                    'title' => __('Cash register'),
                    'description' => __('Click here to switch to the cash register tab.'),
                    'realNav' => true,
                    'resumeMissingMessage' => __('You don\'t have an open shift yet, so "Sell" took you to open one first -- open a shift, then come back to this guide.'),
                ],
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
                    'selector' => '#sidebar-pos-sell',
                    'title' => __('Sell'),
                    'description' => __('Click "Sell" in the sidebar, under "Point of sale", any time you need to come back here -- you need an open shift first (see the "Open shift" guide).'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#pos-ticket-add-btn',
                    'resumeMissingMessage' => __('You don\'t have an open shift yet, so "Sell" took you to open one first -- open a shift, then come back to this guide.'),
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
                    'selector' => '#sidebar-quotations-create',
                    'title' => __('New quotation'),
                    'description' => __('Click "New quotation" in the sidebar, under "Quotations", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
                    'selector' => '#sidebar-quotations-index',
                    'title' => __('Quotations'),
                    'description' => __('Click "Quotations" in the sidebar, under "Quotations", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Filter by client or number to find a quotation you already issued.'),
                ],
                [
                    'selector' => '.quotation-view-btn',
                    'title' => __('View'),
                    'description' => __('Opens the quotation\'s full detail.'),
                    'skipMissingElement' => true,
                    'realNav' => true,
                ],
                [
                    'selector' => '#quote-show-customer',
                    'title' => __('Customer'),
                    'description' => __('The client this quotation was made for.'),
                ],
                [
                    'selector' => '#quote-show-lines',
                    'title' => __('Lines'),
                    'description' => __('Every product on the quotation, with quantity, unit price and the warehouse the stock would come out of.'),
                ],
                [
                    'selector' => '#quote-show-summary',
                    'title' => __('Summary'),
                    'description' => __('Status, dates and totals.'),
                ],
                [
                    'selector' => '#quote-show-convert',
                    'title' => __('Convert quotation'),
                    'description' => __('If it\'s still pending, convert it into a POS sale or an electronic invoice from here, reusing the same client and lines -- once converted, this section links to whatever it became.'),
                ],
                [
                    'selector' => '#quote-show-download-btn',
                    'title' => __('Download PDF'),
                    'description' => __('Download the quotation as a PDF.'),
                ],
            ],
        ],
        'documents-search' => [
            'steps' => [
                [
                    'selector' => '#sidebar-documents',
                    'title' => __('Issued documents'),
                    'description' => __('Click "Issued documents" in the sidebar, under "Document issuance", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Filter by client, number or prefix to quickly find a document you already issued.'),
                ],
                [
                    'selector' => '.document-view-btn',
                    'title' => __('View'),
                    'description' => __('Opens the document\'s full detail.'),
                    'skipMissingElement' => true,
                    'realNav' => true,
                ],
                [
                    'selector' => '#doc-show-customer',
                    'title' => __('Customer'),
                    'description' => __('The client this document was issued to, exactly as it went out.'),
                ],
                [
                    'selector' => '#doc-show-lines',
                    'title' => __('Lines'),
                    'description' => __('Every product on the document, with quantity, unit price and the warehouse the stock came out of.'),
                ],
                [
                    'selector' => '#doc-show-dian-message',
                    'title' => __('DIAN message'),
                    'description' => __('Only shows up once the DIAN responds -- a summary and, if it was rejected, the specific rules it failed.'),
                ],
                [
                    'selector' => '#doc-show-summary',
                    'title' => __('Summary'),
                    'description' => __('Status, dates, totals and payment info -- if it\'s a credit invoice, this is also where you mark it as paid once you collect it.'),
                ],
                [
                    'selector' => '#doc-show-downloads',
                    'title' => __('Downloads'),
                    'description' => __('Download the UBL XML you sent and, once the DIAN responds, its acceptance response too.'),
                ],
            ],
        ],
        'inventory' => [
            'steps' => [
                [
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
                    'description' => __('Add or update as many prices as you need (retail, wholesale, etc.) -- the first one is the default. You need to have created the price type first (see the "Create a price type" guide) to be able to pick it here.'),
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
                    'description' => __('Updates the product with whatever you changed.'),
                ],
            ],
        ],
        'product-show' => [
            'steps' => [
                [
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to find the product you want to see.'),
                ],
                [
                    'selector' => '.product-view-btn',
                    'title' => __('View'),
                    'description' => __('Opens this product\'s full detail: general info, prices, stock and its complete movement history.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#product-show-general',
                    'title' => __('General'),
                    'description' => __('This section shows the code, barcode and unit of measure -- click "Edit" from the product list to change any of this.'),
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
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to find the product whose cost you need to correct.'),
                ],
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
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to find the product you\'re adding stock to.'),
                ],
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
                    'selector' => '#se-warehouse_id-field',
                    'title' => __('Warehouse'),
                    'description' => __('Optional -- pick where this stock is going. Leave it as "Unassigned" if you don\'t need to split it by warehouse.'),
                ],
                [
                    'selector' => '#se-quantity-display',
                    'title' => __('Quantity'),
                    'description' => __('How much you\'re adding in this entry.'),
                ],
                [
                    'selector' => '#se-unit_cost-display',
                    'title' => __('Unit cost'),
                    'description' => __('What you paid per unit for this entry -- it\'s used to recalculate the product\'s average cost.'),
                ],
                [
                    'selector' => '#se-note',
                    'title' => __('Note'),
                    'description' => __('Optional -- a reference for this entry, like a purchase invoice number, so you remember where the stock came from.'),
                ],
                [
                    'selector' => '#stockEntryForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('Adds the quantity to the product\'s stock and records the movement in its kardex.'),
                ],
            ],
        ],
        'delete-product' => [
            'steps' => [
                [
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Use the search box to find the product you want to remove.'),
                ],
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
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
                    'selector' => '#sidebar-members',
                    'title' => __('Members'),
                    'description' => __('Click "Members" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
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
        'edit-member' => [
            'steps' => [
                [
                    'selector' => '#sidebar-members',
                    'title' => __('Members'),
                    'description' => __('Click "Members" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '.member-edit-btn',
                    'title' => __('Edit'),
                    'description' => __('Opens the same form used to add a member, already filled in with their current role in each module.'),
                    'panel' => '#edit-member',
                ],
                [
                    'selector' => '#edit-member-modules-fields',
                    'title' => __('Modules and role'),
                    'description' => __('Change the role for any module, or set it to "No access" to take away access to that module entirely -- it saves exactly what\'s selected here for each module.'),
                ],
                [
                    'selector' => '#editMemberForm button[type="submit"]',
                    'title' => __('Save'),
                    'description' => __('Updates this member\'s access right away.'),
                ],
            ],
        ],
        'delete-member' => [
            'steps' => [
                [
                    'selector' => '#sidebar-members',
                    'title' => __('Members'),
                    'description' => __('Click "Members" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '.member-delete-btn',
                    'title' => __('Delete'),
                    'description' => __('Removes this person from the company entirely (they keep their account, just lose access to this company) -- it will ask you to confirm first, since this action cannot be undone. The owner can\'t be removed this way.'),
                ],
            ],
        ],
        'company-create' => [
            'steps' => [
                [
                    'selector' => '#sidebar-companies',
                    'title' => __('Companies'),
                    'description' => __('Click "Companies" in the sidebar to reach the companies list.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#new-company-btn',
                    'title' => __('New company'),
                    'description' => __('Click here to open the new company form.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#identification-type-field',
                    'title' => __('Identification type'),
                    'description' => __('Each company has its own fiscal information, digital certificate and contracted modules. Usually the identification type is "NIT" for a business, which enables the check digit (DV) field next to it.'),
                ],
                [
                    'selector' => '#identificacion',
                    'title' => __('Identification'),
                    'description' => __('This company\'s ID number, exactly as registered with the DIAN. The DV fills in on its own for a NIT.'),
                ],
                [
                    'selector' => '#company-create-name',
                    'title' => __('Company name'),
                    'description' => __('The legal or business name that shows up on every document you issue.'),
                ],
                [
                    'selector' => '#company-create-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#company-create-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this company -- required for the electronic documents to go out correctly. Ask your accountant if you\'re not sure which ones apply.'),
                ],
                [
                    'selector' => '#company-create-address',
                    'title' => __('Address'),
                    'description' => __('This company\'s physical address.'),
                ],
                [
                    'selector' => '#company-create-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#company-create-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info -- shows up on documents if your template includes it.'),
                ],
                [
                    'selector' => '#company-create-dian_environment-field',
                    'title' => __('DIAN environment'),
                    'description' => __('Start in "Testing" until the DIAN approves your habilitación -- switch to "Production" only once you\'re authorized to issue real documents.'),
                ],
                [
                    'selector' => '#company-create-dian_pin-field',
                    'title' => __('Pin and Software ID'),
                    'description' => __('The codes provided when the invoicing software is registered with the DIAN -- used to sync resolutions automatically. You can skip this and add it later from the company\'s edit screen.'),
                ],
                [
                    'selector' => '#certificate-upload',
                    'title' => __('Digital certificate'),
                    'description' => __('The .p12/.pfx file the DIAN issued for this company -- required to sign electronic documents. You can skip this and add it later from the company\'s edit screen.'),
                ],
                [
                    'selector' => '#dian_certificate_validate',
                    'title' => __('Validate'),
                    'description' => __('Checks the certificate and password are correct before saving -- fix any error here, not after creating the company.'),
                ],
                [
                    'selector' => '#company-create-submit-btn',
                    'title' => __('Save'),
                    'description' => __('Creates the company -- next, set up its DIAN pin/software ID and resolutions before issuing anything (see the next guides).'),
                ],
            ],
        ],
        'company-dian-setup' => [
            'steps' => [
                [
                    'selector' => '#sidebar-companies',
                    'title' => __('Companies'),
                    'description' => __('Click "Companies" in the sidebar to reach the companies list -- you need at least one company already created (see the "Create a company" guide).'),
                    'realNav' => true,
                ],
                [
                    'selector' => '.company-edit-btn',
                    'title' => __('Edit company'),
                    'description' => __('Click the pencil on a company\'s card to open this form.'),
                    'panel' => '#edit-company',
                    'skipMissingElement' => true,
                ],
                [
                    'selector' => '#edit-company-name',
                    'title' => __('Company name'),
                    'description' => __('The legal or business name that shows up on every document you issue.'),
                ],
                [
                    'selector' => '#edit-company-person_type-field',
                    'title' => __('Person type'),
                    'description' => __('"Legal entity" for a business (usually with a NIT), "Natural person" for an individual.'),
                ],
                [
                    'selector' => '#edit-company-fiscal_responsibilities-field',
                    'title' => __('Fiscal responsibilities'),
                    'description' => __('The DIAN tax responsibility codes that apply to this company -- required for electronic documents to go out correctly.'),
                ],
                [
                    'selector' => '#edit-company-address',
                    'title' => __('Address'),
                    'description' => __('This company\'s physical address.'),
                ],
                [
                    'selector' => '#edit-company-department_code-field',
                    'title' => __('Department and city'),
                    'description' => __('Pick the department first -- the city list narrows down to that department automatically.'),
                ],
                [
                    'selector' => '#edit-company-phone-field',
                    'title' => __('Phone and email'),
                    'description' => __('Optional contact info -- shows up on documents if your template includes it.'),
                ],
                [
                    'selector' => '#edit-company-status-field',
                    'title' => __('Status'),
                    'description' => __('Set it to "Inactive" to stop this company from showing up as selectable, without deleting anything.'),
                ],
                [
                    'selector' => '#edit-company-dian_environment-field',
                    'title' => __('DIAN environment'),
                    'description' => __('Stay in "Testing" until the DIAN approves your habilitación -- switch to "Production" only once you\'re authorized to issue real documents.'),
                ],
                [
                    'selector' => '#edit-company-dian_pin',
                    'title' => __('Pin'),
                    'description' => __('The pin the DIAN assigned to this company\'s software.'),
                ],
                [
                    'selector' => '#edit-company-dian_software_id',
                    'title' => __('Software ID'),
                    'description' => __('The software ID the DIAN assigned when you registered your billing software -- pin and software ID together are what let you sync resolutions automatically.'),
                ],
                [
                    'selector' => '#edit-company-certificate-upload',
                    'title' => __('Digital certificate'),
                    'description' => __('Upload or replace the .p12/.pfx file used to sign electronic documents.'),
                ],
                [
                    'selector' => '#edit-company-certificate-validate',
                    'title' => __('Validate'),
                    'description' => __('Checks the certificate and password before saving -- once it\'s valid, you\'re ready to set up resolutions.'),
                ],
                [
                    'selector' => '#edit-company-habilitacion-btn',
                    'title' => __('DIAN enablement'),
                    'description' => __('Opens the screen to send and track the DIAN test batch (habilitación) -- required once before you can switch this company to the production environment.'),
                ],
                [
                    'selector' => '#edit-company-submit-btn',
                    'title' => __('Save'),
                    'description' => __('Saves every change made in this form, including the pin, software ID and certificate.'),
                ],
            ],
        ],
        'dian-resolutions' => [
            'steps' => [
                [
                    'selector' => '#sidebar-resolutions',
                    'title' => __('Resolutions'),
                    'description' => __('Click "Resolutions" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#dian-sync-btn',
                    'title' => __('Sync from DIAN'),
                    'description' => __('Pulls the numbering ranges the DIAN already authorized for this company\'s electronic invoices -- only shown if the invoicing module is active, and only works once the pin and software ID are configured (see the DIAN setup guide).'),
                    'skipMissingElement' => true,
                ],
                [
                    'selector' => '#dian-add-prefix-btn',
                    'title' => __('Add prefixes'),
                    'description' => __('The DIAN doesn\'t authorize a range for sales tickets, credit/debit notes or quotations -- define your own prefix and starting number here.'),
                    'panel' => '#note-numbering-modal',
                ],
                [
                    'selector' => '#dian-manual-doctype-field',
                    'title' => __('Document type'),
                    'description' => __('Which kind of document this numbering range is for -- sales ticket, credit note, debit note or quotation.'),
                ],
                [
                    'selector' => '#dian-manual-prefix',
                    'title' => __('Prefix'),
                    'description' => __('The letters that go before the consecutive number (e.g. "COT" for quotations).'),
                ],
                [
                    'selector' => '#dian-manual-range_from',
                    'title' => __('Starting number'),
                    'description' => __('The first consecutive number to use -- it increases automatically after that.'),
                ],
                [
                    'selector' => '#dian-manual-submit-btn',
                    'title' => __('Create'),
                    'description' => __('Saves this numbering range -- it shows up right away in the table below, ready to pick when issuing that kind of document.'),
                ],
            ],
        ],
        'payment-methods' => [
            'steps' => [
                [
                    'selector' => '#sidebar-pos-payment-methods',
                    'title' => __('Payment methods'),
                    'description' => __('Click "Payment methods" in the sidebar, under "Point of sale", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#new-payment-method-btn',
                    'title' => __('New payment method'),
                    'description' => __('Click here to open the new payment method form.'),
                    'panel' => '#payment-method-panel',
                ],
                [
                    'selector' => '#payment-method-name',
                    'title' => __('Name'),
                    'description' => __('What shows up on the POS payment screen (e.g. "Nequi", "Card").'),
                ],
                [
                    'selector' => '#payment-method-dian-code-field',
                    'title' => __('DIAN equivalent'),
                    'description' => __('Required only if you plan to issue this sale as an electronic invoice -- without it, a sale paid this way can\'t be turned into one.'),
                ],
                [
                    'selector' => '#payment-method-submit-btn',
                    'title' => __('Save'),
                    'description' => __('It shows up right away in the payment method list, and as an option on the POS checkout screen.'),
                ],
            ],
        ],
        'sellers' => [
            'steps' => [
                [
                    'selector' => '#sidebar-pos-sell',
                    'title' => __('Sell'),
                    'description' => __('Click "Sell" in the sidebar, under "Point of sale" -- the "Sellers" tab (admin only) is up top from there.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#pos-tab-sellers',
                    'title' => __('Sellers'),
                    'description' => __('Click here to switch to the sellers tab.'),
                    'realNav' => true,
                    'resumeMissingMessage' => __('You don\'t have an open shift yet, so "Sell" took you to open one first -- open a shift, then come back to this guide.'),
                ],
                [
                    'selector' => '#new-seller-btn',
                    'title' => __('New seller'),
                    'description' => __('Click here to open the new seller form.'),
                    'panel' => '#seller-panel',
                ],
                [
                    'selector' => '#seller-name',
                    'title' => __('Name'),
                    'description' => __('Who you\'ll pick as the seller when charging a sale -- used for the "sales by seller" chart in the panel.'),
                ],
                [
                    'selector' => '#seller-submit-btn',
                    'title' => __('Save'),
                    'description' => __('It shows up right away in the seller list, ready to pick on the POS checkout screen.'),
                ],
            ],
        ],
        'pos-sales-search' => [
            'steps' => [
                [
                    'selector' => '#sidebar-pos-sales',
                    'title' => __('Sales'),
                    'description' => __('Click "Sales" in the sidebar, under "Point of sale", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#hs-table-with-pagination-search',
                    'title' => __('Search'),
                    'description' => __('Filter by client or number to find a sale you already charged.'),
                ],
                [
                    'selector' => '.pos-sale-view-btn',
                    'title' => __('View'),
                    'description' => __('Opens the sale\'s full detail.'),
                    'skipMissingElement' => true,
                    'realNav' => true,
                ],
                [
                    'selector' => '#sale-show-customer',
                    'title' => __('Customer'),
                    'description' => __('The client this sale was charged to.'),
                ],
                [
                    'selector' => '#sale-show-lines',
                    'title' => __('Lines'),
                    'description' => __('Every product on the sale, with quantity, unit price and the warehouse the stock came out of -- an admin can edit it here if it hasn\'t been issued as an electronic invoice yet.'),
                ],
                [
                    'selector' => '#sale-show-summary',
                    'title' => __('Summary'),
                    'description' => __('Status, dates, totals and how it was paid.'),
                ],
                [
                    'selector' => '#sale-show-electronic',
                    'title' => __('Electronic invoice'),
                    'description' => __('If it hasn\'t been issued as an electronic invoice yet and the client has valid billing data, issue it right from here.'),
                ],
                [
                    'selector' => '#sale-show-download-btn',
                    'title' => __('Download receipt'),
                    'description' => __('Download the sales receipt as a PDF.'),
                ],
            ],
        ],
        'catalog-link' => [
            'steps' => [
                [
                    'selector' => '#sidebar-quotations-index',
                    'title' => __('Quotations'),
                    'description' => __('Click "Quotations" in the sidebar, under "Quotations" -- this card is above the quotations table on that screen.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#catalog-link-add-btn',
                    'title' => __('New link'),
                    'description' => __('Create a link you can share with your clients -- they browse the catalog and build their own quotation, no account needed.'),
                    'panel' => '#catalog-link-modal',
                ],
                [
                    'selector' => '#catalog-link-label',
                    'title' => __('Label'),
                    'description' => __('Optional -- a name to tell this link apart from others in the list (e.g. "Main store", "Warehouse 2").'),
                ],
                [
                    'selector' => '#catalog-link-warehouse-field',
                    'title' => __('Warehouse'),
                    'description' => __('If you pick one, the link only shows and sells stock from that warehouse -- leave it as "All warehouses" to show everything.'),
                ],
                [
                    'selector' => '#catalog-link-primary-price-field',
                    'title' => __('Main price type'),
                    'description' => __('The price shown on each product card in the catalog.'),
                ],
                [
                    'selector' => '#catalog-link-visible-prices-field',
                    'title' => __('Other visible price types'),
                    'description' => __('Optional -- any extra prices you check here show up in the price list when hovering over a product card, along with the main price.'),
                ],
                [
                    'selector' => '#catalog-link-submit-btn',
                    'title' => __('Create link'),
                    'description' => __('It shows up in the list above right after, with a button next to it to copy the URL to your clipboard.'),
                ],
            ],
        ],
        'panel' => [
            'steps' => [
                [
                    'selector' => '#sidebar-panel',
                    'title' => __('Panel'),
                    'description' => __('Click "Panel" in the sidebar, near the top, any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#panel-period-filters',
                    'title' => __('Period'),
                    'description' => __('Every number and chart below updates to that time window, with a comparison against the same-length period before it.'),
                ],
                [
                    'selector' => '#panel-metrics-grid',
                    'title' => __('Modules'),
                    'description' => __('One card per module you administer, with today\'s and this period\'s activity, plus how much it changed against the previous period of the same length.'),
                ],
                [
                    'selector' => '#panel-utility',
                    'title' => __('Gross profit'),
                    'description' => __('Revenue minus the real cost of what you sold in the period, using the cost each product had at the moment of each sale -- not its current cost. Only shown if you administer invoicing and/or POS.'),
                    'skipMissingElement' => true,
                ],
                [
                    'selector' => '#panel-low-stock',
                    'title' => __('Low stock'),
                    'description' => __('Products that track inventory and are running low -- a heads-up before you run out. Only shown if any product is below the threshold.'),
                    'skipMissingElement' => true,
                ],
                [
                    'selector' => '#panel-receivables',
                    'title' => __('Accounts receivable'),
                    'description' => __('How much you still have pending to collect from credit invoices, how much of that is overdue, and the invoices most overdue right now -- always the current balance, not filtered by the period above.'),
                    'skipMissingElement' => true,
                ],
                [
                    'selector' => '#panel-trend-chart',
                    'title' => __('Trend'),
                    'description' => __('How your activity moved within the chosen period -- by hour if it\'s today, by day for a week/month, by month for a year.'),
                ],
                [
                    'selector' => '#panel-export-btn',
                    'title' => __('Export PDF'),
                    'description' => __('Downloads the same data as flat tables, ready to print or share.'),
                ],
            ],
        ],
        'products-export' => [
            'steps' => [
                [
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#products-export-btn',
                    'title' => __('Export to Excel'),
                    'description' => __('Downloads your current catalog as a spreadsheet -- edit it and import it back to update many products at once.'),
                    'panel' => '#product-export-modal',
                ],
                [
                    'selector' => '#product-export-fields',
                    'title' => __('Columns'),
                    'description' => __('Code, description and barcode always go in the file -- check any other column you also want (unit, cost, prices by type, stock by warehouse).'),
                ],
                [
                    'selector' => '#product-export-submit-btn',
                    'title' => __('Export'),
                    'description' => __('Downloads the spreadsheet right away with the columns you picked.'),
                ],
            ],
        ],
        'products-import' => [
            'steps' => [
                [
                    'selector' => '#sidebar-inventory',
                    'title' => __('Inventory'),
                    'description' => __('Click "Inventory" in the sidebar, under "Company", any time you need to come back here.'),
                    'realNav' => true,
                ],
                [
                    'selector' => '#products-import-btn',
                    'title' => __('Import from Excel'),
                    'description' => __('Upload a spreadsheet to create or update products in bulk.'),
                    'panel' => '#product-import-modal',
                ],
                [
                    'selector' => '#import-file-upload',
                    'title' => __('Choose file'),
                    'description' => __('An Excel (.xlsx) or CSV file -- products are matched by code, so an existing code updates that product instead of creating a duplicate.'),
                ],
                [
                    'selector' => '#import-analyze-btn',
                    'title' => __('Analyze file'),
                    'description' => __('Reads the file and lets you match each of its columns to a field before anything is saved -- nothing changes in your catalog until you confirm on the next step.'),
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
        'resumeMissing' => __('This guide can\'t continue on this screen -- something about your current state took you somewhere else.'),
        'noticeTitle' => __('Notice'),
    ];
@endphp
<script>
    window.appTours = @json($appTours);
    window.appTourLabels = @json($appTourLabels);
    window.appHelpUrl = @json(route('help.index'));
</script>
