@php
    $identificationTypes = [
        '13' => __('Citizenship card'),
        '31' => __('NIT'),
        '11' => __('Civil registry'),
        '12' => __('Identity card'),
        '21' => __('Foreigner card'),
        '22' => __('Foreigner ID card'),
        '41' => __('Passport'),
        '42' => __('Foreign identification document'),
        '47' => __('PEP (Special Permanence Permit)'),
        '48' => __('PPT (Temporary Protection Permit)'),
        '50' => __('NIT from another country'),
        '91' => __('NUIP'),
    ];

    $defaultClientJs = [
        'id' => (string) $defaultClient->_id,
        'identification_type' => $defaultClient->identification_type,
        'identificacion' => $defaultClient->identificacion,
        'name' => $defaultClient->name,
        'person_type' => $defaultClient->person_type,
        'fiscal_responsibilities' => $defaultClient->fiscal_responsibilities,
        'address' => $defaultClient->address,
        'department_code' => $defaultClient->department_code,
        'city_code' => $defaultClient->city_code,
        'phone' => $defaultClient->phone,
        'email' => $defaultClient->email,
    ];

    // $products ya viene en el mismo shape que el AJAX de búsqueda (ver
    // PosController::create() -> DocumentoEmitidoController::mapProductsForJs()),
    // con precios por tipo y stock por bodega -- no hace falta remapearlo acá.
    $initialProductsJs = $products;

    $paymentSelectConfig = \App\Support\SelectConfig::basic();

    $paymentMethodsJs = $paymentMethods->map(fn ($m) => [
        'id' => (string) $m->_id,
        'name' => $m->name,
        'dianCode' => $m->dian_payment_means_code,
    ])->values();
@endphp

<x-layouts.app :title="__('Sell')">
    @include('partials.tittle', [
        'title' => __('Point of sale'),
        'subheading' => __('Search a product and add it to the cart to sell.'),
    ])

    @include('pos.partials.tabs', ['activeTab' => 'sell'])

    <div id="pos-checkout-error" class="hidden mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>

    <!-- Pre-cuentas: cada una es un cliente + carrito independiente, para
         atender a un cliente sin perder lo que ya tenía otro a medio armar. -->
    <div class="mb-4 flex items-center gap-1 overflow-x-auto">
        <div id="pos-tickets-bar" class="flex items-center gap-1"></div>
        <button type="button" id="pos-ticket-add-btn"
            class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-accent dark:text-neutral-400 dark:hover:bg-neutral-700 focus:outline-hidden"
            aria-label="{{ __('New pre-bill') }}" title="{{ __('New pre-bill') }}">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-6 items-start">
        <!-- Columna izquierda: buscador + grid de productos -->
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="p-4 border-b border-gray-200 dark:border-neutral-700 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input type="text" id="pos-product-search" autocomplete="off"
                        placeholder="{{ __('Code, barcode or description') }}"
                        class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base shadow-xs h-10 py-2 px-3 ps-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                </div>
                <div class="sm:w-56 shrink-0">
                    {{-- "all" es un valor real seleccionable (no la cadena vacía):
                         con "" como valor, Preline lo trata como el
                         placeholder y ya no se puede volver a elegir desde
                         el dropdown una vez elegida otra bodega. --}}
                    <select id="pos-warehouse-filter" data-hs-select='{!! \App\Support\SelectConfig::searchable() !!}' class="hidden">
                        <option value="all" selected>{{ __('All warehouses') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="pos-product-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
            <p id="pos-product-grid-empty" class="hidden p-4 text-sm text-zinc-500 dark:text-neutral-400">{{ __('No products found.') }}</p>
        </div>

        <!-- Columna derecha: cliente + carrito + pago -->
        <div class="flex flex-col gap-4">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4">
                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Client') }}</label>
                <div class="relative">
                    <input type="text" id="pos-client-search" autocomplete="off"
                        class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs h-10 py-2 px-3 pe-9 focus:outline-hidden focus:ring-2 focus:ring-accent"
                        placeholder="{{ __('Search by name or identification') }}" value="{{ $defaultClient->name }}">
                    <button type="button" id="pos-client-add-btn"
                        class="absolute inset-y-0 end-0 flex items-center justify-center w-9 text-zinc-400 hover:text-accent focus:outline-hidden"
                        aria-label="{{ __('New client') }}" title="{{ __('New client') }}">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    </button>
                    <div id="pos-client-results" class="hidden absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 rounded-lg shadow-xl"></div>
                </div>
                <p id="pos-client-identificacion" class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ $defaultClient->identificacion }}</p>

                @if ($sellers->isNotEmpty())
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Seller') }}</label>
                        <select id="pos-seller-select" data-hs-select='{!! $paymentSelectConfig !!}' class="hidden">
                            <option value="">{{ __('No seller assigned') }}</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->_id }}">{{ $seller->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-2.5 py-2.5 border-b border-gray-200 dark:border-neutral-700">
                    <div class="grid" style="grid-template-columns: 1fr 76px 40px 112px 108px 40px;">
                        <span class="px-2.5 text-xs font-medium text-zinc-500 dark:text-neutral-400 self-center">{{ __('Description') }}</span>
                        <span class="text-xs font-medium text-zinc-500 dark:text-neutral-400 text-center self-center">{{ __('Quantity') }}</span>
                        <span></span>
                        <div class="self-center">
                            @if ($priceTypes->isNotEmpty())
                                <div class="hs-dropdown [--auto-close:false] relative">
                                    <button type="button" id="pos-bulk-price-type-btn" class="hs-dropdown-toggle inline-flex items-center gap-1 px-2 text-xs font-medium text-zinc-500 hover:text-accent dark:text-neutral-400 focus:outline-hidden" title="{{ __('Apply price type to all lines') }}">
                                        {{ __('Price list') }}
                                        <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-56 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 space-y-0.5 dark:bg-neutral-800 dark:border-neutral-700">
                                        @foreach ($priceTypes as $priceType)
                                            <button type="button" class="pos-bulk-price-type-option w-full text-start px-3 py-1.5 text-sm rounded-lg text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10" data-price-type-id="{{ $priceType->_id }}">
                                                {{ $priceType->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <span class="px-2 text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Unit price') }}</span>
                            @endif
                        </div>
                        <span class="text-xs font-medium text-zinc-500 dark:text-neutral-400 text-end px-2.5 self-center">{{ __('Subtotal') }}</span>
                        <span></span>
                    </div>
                </div>
                <div id="pos-cart-body" class="divide-y divide-gray-200 dark:divide-neutral-700 max-h-[40vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                <p id="pos-cart-empty" class="p-4 text-sm text-zinc-500 dark:text-neutral-400">
                    <svg class="mx-auto mb-2 size-8 text-zinc-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span class="block text-center">{{ __("You don't have any products in the cart yet.") }}</span>
                </p>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 flex flex-col gap-3">
                <div id="pos-payment-fields" class="flex flex-col gap-3">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Payment method') }}</label>
                            @if ($paymentMethods->isNotEmpty())
                                <button type="button" id="pos-payment-add-btn" class="text-xs font-medium text-accent hover:underline focus:outline-hidden">
                                    + {{ __('Add payment method') }}
                                </button>
                            @endif
                        </div>
                        <div id="pos-payment-lines" class="flex flex-col gap-2"></div>
                        @if ($paymentMethods->isEmpty())
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('You have no payment methods configured yet.') }}</p>
                        @else
                            <p id="pos-payment-remaining" class="hidden mt-1 text-xs text-red-600 dark:text-red-400"></p>
                        @endif
                    </div>

                    <div id="pos-cash-section" class="hidden grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="pos-efectivo-display">{{ __('Cash received') }}</label>
                            <div class="relative">
                                <input type="text" inputmode="decimal" id="pos-efectivo-display"
                                    class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-2">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Change') }}</span>
                            <span id="pos-change-display" class="block text-sm font-semibold text-gray-800 dark:text-neutral-200 h-10 leading-10">$0.00</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-neutral-700">
                    <span class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('Total') }}</span>
                    <span id="pos-total-display" class="text-xl font-bold text-gray-800 dark:text-neutral-200">$0.00</span>
                </div>

                <flux:button type="button" variant="primary" id="pos-checkout-btn" class="w-full">{{ __('Charge sale') }}</flux:button>
            </div>
        </div>
    </div>

    {{-- Mismo panel de alta de cliente que usa la pantalla de Clientes (ver
         third-parties/partials/form-panel.blade.php) -- el POS ya no tiene
         su propio formulario simplificado; window.thirdPartyPanelOnSave más
         abajo hace que, en vez de recargar la página, el cliente recién
         creado quede seleccionado en la pre-cuenta activa. --}}
    @include('third-parties.partials.form-panel', ['panelLabel' => __('Client'), 'storeRoute' => 'clients.store'])

    <!-- Modal: resultado de la venta -->
    <div id="pos-result-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="pos-result-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="pos-result-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Sale completed') }}</h3>
                    <span id="pos-result-numeral" class="text-sm text-zinc-500 dark:text-zinc-400"></span>
                </div>
                <div class="p-4">
                    <div id="pos-result-message" class="mb-3 rounded-md p-3 text-sm hidden"></div>
                    <iframe id="pos-result-preview" class="w-full rounded-lg border border-gray-200 dark:border-neutral-700" style="height: 50vh;" title="{{ __('Receipt preview') }}"></iframe>
                </div>
                <div class="p-4 pt-0 grid grid-cols-2 gap-2">
                    {{-- El "hidden" se pone en este div envolvente, no
                         directo en el flux:button: flux:button siempre trae
                         su propia clase "inline-flex" (así se le pase
                         "hidden" en $attributes), y en el CSS compilado esa
                         clase queda definida DESPUÉS de ".hidden" -- mismo
                         nivel de especificidad, empate que gana la que está
                         más abajo en la hoja, así que el botón nunca se
                         llegaba a ocultar de verdad por más que se le
                         alternara la clase por JS. --}}
                    <div id="pos-result-issue-electronic-wrapper" class="col-span-2 hidden">
                        <flux:button type="button" id="pos-result-issue-electronic-btn" variant="primary" icon="bolt" class="w-full" onclick="window.posIssueElectronic()">
                            {{ __('Issue electronic invoice') }}
                        </flux:button>
                    </div>
                    <flux:button type="button" variant="filled" icon="printer" onclick="window.posPrintReceipt()">{{ __('Print') }}</flux:button>
                    <flux:button type="button" variant="filled" icon="arrow-down-tray" onclick="window.posDownloadReceipt()">{{ __('Download') }}</flux:button>
                    <flux:button type="button" variant="filled" icon="receipt-percent" class="col-span-2" onclick="window.posGoToSales()">{{ __('Go to sales') }}</flux:button>
                    <flux:button type="button" variant="primary" class="col-span-2" onclick="window.posNewSale()">{{ __('New sale') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const productSearchUrl = @json(route('documents.create-product-search'));
                const clientSearchUrl = @json(route('documents.create-client-search'));
                const checkoutUrl = @json(route('pos.checkout'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                const defaultClient = @json($defaultClientJs);

                const initialProducts = @json($initialProductsJs);

                const paymentMethods = @json($paymentMethodsJs);
                const paymentSelectConfigAttr = @json($paymentSelectConfig);

                const quotationId = @json($quotationId ?? null);
                const quotationPrefill = @json($quotationPrefill ?? null);

                const editSaleId = @json($editSaleId ?? null);
                const editSalePrefill = @json($editSalePrefill ?? null);
                const editSaleUpdateUrl = @json(($editSaleId ?? null) ? route('pos.sales.update', $editSaleId) : null);

                const canEditPrice = @json($canEditPrice ?? false);

                // Pre-cuentas: cada una es un cliente + carrito + medio de
                // pago independientes, para poder atender a un cliente sin
                // perder lo que ya tenía armado otro que sigue decidiendo.
                // Solo viven en memoria del navegador (se pierden si se
                // recarga la página antes de cobrar), igual que una
                // pre-cuenta de papel se pierde si se bota.
                let nextTicketNumber = 1;
                let tickets = [];
                let activeTicketId = null;
                let currentSale = null;

                /**
                 * Crea una pre-cuenta nueva y la agrega a `tickets`. El
                 * carrito es un array de {code, barcode, description,
                 * unit_code, unit_price, qty}. Una venta puede repartirse
                 * entre varios medios de pago (p. ej. mitad en Addi, mitad
                 * en efectivo); `payments` es un array de {paymentMethodId,
                 * amount}.
                 * @returns {object} La pre-cuenta creada.
                 */
                function makeTicket() {
                    const ticket = {
                        id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
                        number: nextTicketNumber++,
                        client: { ...defaultClient },
                        cart: [],
                        payments: paymentMethods.length > 0 ? [{ paymentMethodId: paymentMethods[0].id, amount: 0 }] : [],
                        efectivoRecibido: '',
                        warehouseId: 'all',
                        sellerId: '',
                    };
                    tickets.push(ticket);
                    return ticket;
                }

                function activeTicket() {
                    return tickets.find((t) => t.id === activeTicketId);
                }

                function switchTicket(id) {
                    activeTicketId = id;
                    renderTickets();
                    renderActiveTicket();
                }

                function addTicket() {
                    const ticket = makeTicket();
                    switchTicket(ticket.id);
                }

                function closeTicket(id) {
                    const ticket = tickets.find((t) => t.id === id);
                    if (! ticket) {
                        return;
                    }
                    if (ticket.cart.length > 0 && ! confirm('{{ __('This pre-bill has products in the cart. Close it anyway?') }}')) {
                        return;
                    }

                    tickets = tickets.filter((t) => t.id !== id);

                    if (tickets.length === 0) {
                        makeTicket();
                    }
                    if (activeTicketId === id) {
                        activeTicketId = tickets[0].id;
                    }

                    renderTickets();
                    renderActiveTicket();
                }

                function renderTickets() {
                    const bar = document.getElementById('pos-tickets-bar');
                    bar.innerHTML = '';

                    tickets.forEach((ticket) => {
                        const isActive = ticket.id === activeTicketId;
                        const tab = document.createElement('div');
                        tab.className = 'shrink-0 flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium cursor-pointer ' + (isActive
                            ? 'bg-accent/10 text-accent'
                            : 'text-zinc-500 hover:bg-zinc-100 dark:text-neutral-400 dark:hover:bg-neutral-700');
                        tab.innerHTML = `
                            <span>{{ __('Pre-bill') }} ${ticket.number}${ticket.cart.length ? ' (' + ticket.cart.length + ')' : ''}</span>
                            <button type="button" data-action="close" class="rounded-full hover:bg-black/10 dark:hover:bg-white/10 p-0.5" aria-label="{{ __('Close') }}">
                                <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            </button>
                        `;
                        tab.addEventListener('click', () => switchTicket(ticket.id));
                        tab.querySelector('[data-action="close"]').addEventListener('click', (event) => {
                            event.stopPropagation();
                            closeTicket(ticket.id);
                        });
                        bar.appendChild(tab);
                    });
                }

                /**
                 * Repinta toda la columna derecha/izquierda para la
                 * pre-cuenta activa (cliente, pago, carrito, grid de
                 * productos). La bodega elegida es propia de cada
                 * pre-cuenta (no un filtro global): al cambiar de pestaña,
                 * se restaura la que tenía esta y se vuelve a buscar con
                 * ese filtro.
                 * @returns {void}
                 */
                function renderActiveTicket() {
                    const ticket = activeTicket();
                    if (! ticket) {
                        return;
                    }

                    document.getElementById('pos-client-search').value = ticket.client.name;
                    document.getElementById('pos-client-identificacion').textContent = ticket.client.identificacion;
                    document.getElementById('pos-client-results').classList.add('hidden');

                    const sellerSelect = document.getElementById('pos-seller-select');
                    if (sellerSelect) {
                        const sellerInstance = window.HSSelect && HSSelect.getInstance(sellerSelect);
                        if (sellerInstance) {
                            sellerInstance.setValue(ticket.sellerId || '');
                        } else {
                            sellerSelect.value = ticket.sellerId || '';
                        }
                    }

                    renderPaymentLines();
                    document.getElementById('pos-efectivo-display').value = ticket.efectivoRecibido;

                    const warehouseSelect = document.getElementById('pos-warehouse-filter');
                    const warehouseInstance = window.HSSelect && HSSelect.getInstance(warehouseSelect);
                    if (warehouseInstance) {
                        warehouseInstance.setValue(ticket.warehouseId || 'all');
                    } else {
                        warehouseSelect.value = ticket.warehouseId || 'all';
                    }
                    searchProducts(currentProductQuery());

                    updatePosCashSectionVisibility();
                    renderCart();
                }

                function formatMoney(value) {
                    return '$' + (value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function showError(message) {
                    const box = document.getElementById('pos-checkout-error');
                    box.textContent = message;
                    box.classList.remove('hidden');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                function debounce(fn, wait) {
                    let timeout;
                    return (...args) => {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => fn(...args), wait);
                    };
                }

                // --- Grid de productos ---

                /**
                 * Pinta el grid de productos del POS. Con una bodega
                 * elegida, el stock que se muestra en el badge es el de ESA
                 * bodega puntual (product.warehouses viene del backend en la
                 * búsqueda por AJAX) -- mostrar "product.stock" ahí sería el
                 * total de todas las bodegas, no el de la que el cajero está
                 * mirando; con "Todas las bodegas" se muestra el total (ya
                 * es "product.stock", la suma de todas). Los badges de
                 * inventario/precio usan un tooltip propio (mismo diseño que
                 * los dropdowns de selects de la app) en vez del title
                 * nativo del navegador: el badge muestra el número que
                 * aplica a lo que se está mirando ahora mismo (bodega
                 * filtrada o total), y al pasar el mouse se ve el desglose
                 * completo. La tarjeta es horizontal (ícono a la izquierda,
                 * info apilada a la derecha) para ser más compacta en alto y
                 * dejar ver más productos de un vistazo sin perder ningún
                 * dato.
                 * @param {Array} products
                 * @returns {void}
                 */
                function renderProducts(products) {
                    const grid = document.getElementById('pos-product-grid');
                    const empty = document.getElementById('pos-product-grid-empty');
                    grid.innerHTML = '';
                    empty.classList.toggle('hidden', products.length > 0);

                    const warehouseId = document.getElementById('pos-warehouse-filter').value;
                    const hasWarehouseFilter = warehouseId && warehouseId !== 'all';

                    products.forEach((product) => {
                        const warehouseStock = hasWarehouseFilter
                            ? product.warehouses?.find((w) => w.warehouse_id === warehouseId)?.stock
                            : null;
                        const stockToShow = warehouseStock !== null && warehouseStock !== undefined ? warehouseStock : product.stock;

                        const inventoryLines = (product.warehouses || [])
                            .map((w) => [w.warehouse_name, (w.stock ?? 0).toLocaleString('es-CO')]);
                        if (inventoryLines.length === 0) {
                            inventoryLines.push(['{{ __('No warehouse breakdown available.') }}', '']);
                        }

                        const priceLines = (product.prices && product.prices.length > 0)
                            ? product.prices.map((p) => [p.price_type_name, formatMoney(p.price)])
                            : [['{{ __('Base price') }}', formatMoney(product.unit_price)]];

                        const card = document.createElement('button');
                        card.type = 'button';
                        card.className = 'flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-neutral-700 p-2.5 text-start hover:border-accent hover:shadow-sm focus:outline-hidden focus:ring-2 focus:ring-accent transition';
                        const iconHtml = product.image_url
                            ? `<img src="${product.image_url}" alt="" class="shrink-0 size-12 rounded-lg object-cover zoomable-thumb cursor-zoom-in">`
                            : `<span class="flex items-center justify-center shrink-0 size-12 rounded-lg bg-accent/10 text-accent">
                                <svg class="shrink-0 size-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            </span>`;
                        card.innerHTML = `
                            ${iconHtml}
                            <div class="flex-1 min-w-0 flex flex-col gap-1">
                                <span class="text-sm font-medium text-gray-800 dark:text-white line-clamp-1" title="${escapeHtml(product.description || '')}">${escapeHtml(product.description || '')}</span>
                                <div class="flex justify-between items-center text-xs text-zinc-500 dark:text-neutral-400">
                                    <span class="truncate">${escapeHtml(product.code || '—')}</span>
                                    <span class="shrink-0">${escapeHtml(product.barcode || '—')}</span>
                                </div>
                                <div class="flex justify-between items-center gap-2" data-badges></div>
                            </div>
                        `;
                        card.addEventListener('click', () => addToCart(product, card));

                        const badgesRow = card.querySelector('[data-badges]');
                        badgesRow.appendChild(tooltipBadge(`{{ __('Inventory') }}: ${(stockToShow ?? 0).toLocaleString('es-CO')}`, inventoryLines, 'text-xs text-zinc-600 dark:text-zinc-300'));
                        const displayPrice = (product.prices && product.prices.length > 0) ? product.prices[0].price : product.unit_price;
                        badgesRow.appendChild(tooltipBadge(formatMoney(displayPrice), priceLines, 'text-sm font-semibold text-gray-800 dark:text-neutral-200'));

                        grid.appendChild(card);
                    });
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                /**
                 * Devuelve (creándolo si hace falta) el único tooltip
                 * flotante compartido por todos los badges (mismo diseño
                 * que los dropdowns de selects de la app) en vez del title
                 * nativo del navegador. Es "position: fixed" y vive pegado a
                 * <body> -- si quedara "absolute" dentro de la tarjeta, el
                 * contenedor del grid (que tiene scroll) lo recortaría o lo
                 * dejaría empujando las tarjetas vecinas en vez de flotar
                 * por encima de todo.
                 * @returns {HTMLElement}
                 */
                function ensureFloatingTooltip() {
                    let el = document.getElementById('pos-floating-tooltip');
                    if (! el) {
                        el = document.createElement('div');
                        el.id = 'pos-floating-tooltip';
                        el.className = 'hidden fixed z-50 w-56 bg-white border border-zinc-200 rounded-lg shadow-xl p-2 text-xs dark:bg-zinc-700 dark:border-white/10';
                        document.body.appendChild(el);
                    }
                    return el;
                }

                /**
                 * Muestra el tooltip flotante junto a triggerEl. Arriba del
                 * elemento por defecto; si no cabe (muy cerca del borde
                 * superior de la ventana), abajo. Igual en horizontal, para
                 * que nunca se salga de la pantalla.
                 * @param {HTMLElement} triggerEl
                 * @param {Array<[string, string]>} lines
                 * @returns {void}
                 */
                function showFloatingTooltip(triggerEl, lines) {
                    const el = ensureFloatingTooltip();
                    el.innerHTML = lines.map(([left, right]) => `
                        <div class="flex justify-between gap-3 py-0.5">
                            <span class="text-zinc-500 dark:text-neutral-400">${escapeHtml(left)}</span>
                            ${right ? `<span class="font-medium text-zinc-700 dark:text-zinc-200">${escapeHtml(right)}</span>` : ''}
                        </div>
                    `).join('');
                    el.classList.remove('hidden');

                    const triggerRect = triggerEl.getBoundingClientRect();
                    const tooltipRect = el.getBoundingClientRect();

                    let top = triggerRect.top - tooltipRect.height - 8;
                    if (top < 8) {
                        top = triggerRect.bottom + 8;
                    }
                    let left = triggerRect.left;
                    if (left + tooltipRect.width > window.innerWidth - 8) {
                        left = window.innerWidth - tooltipRect.width - 8;
                    }

                    el.style.top = `${top}px`;
                    el.style.left = `${left}px`;
                }

                function hideFloatingTooltip() {
                    document.getElementById('pos-floating-tooltip')?.classList.add('hidden');
                }

                function tooltipBadge(label, lines, badgeClasses) {
                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-white/10 px-2 py-0.5 cursor-default ${badgeClasses}`;
                    badge.textContent = label;
                    badge.addEventListener('mouseenter', () => showFloatingTooltip(badge, lines));
                    badge.addEventListener('mouseleave', hideFloatingTooltip);
                    return badge;
                }

                async function searchProducts(query) {
                    const warehouseId = document.getElementById('pos-warehouse-filter').value || 'all';
                    const params = new URLSearchParams({ q: query, warehouse_id: warehouseId });

                    const response = await fetch(productSearchUrl + '?' + params.toString());
                    const data = await response.json();
                    renderProducts(data.products || []);
                }

                function currentProductQuery() {
                    return document.getElementById('pos-product-search').value.trim();
                }

                /**
                 * Engancha la búsqueda de producto y el filtro de bodega. El
                 * select de bodega es de Preline (buscable): dispara
                 * "change" nativo y "change.hs.select" propio -- hace falta
                 * escuchar los dos para no perderse el cambio sin importar
                 * cuál de las dos formas lo generó (clic en una opción vs.
                 * setValue() por código).
                 * @returns {void}
                 */
                function bindProductSearch() {
                    document.getElementById('pos-product-search').addEventListener('input', debounce((event) => {
                        searchProducts(event.target.value.trim());
                    }, 300));

                    const warehouseSelect = document.getElementById('pos-warehouse-filter');
                    const onWarehouseChange = () => {
                        activeTicket().warehouseId = warehouseSelect.value;
                        searchProducts(currentProductQuery());
                    };
                    warehouseSelect.addEventListener('change', onWarehouseChange);
                    warehouseSelect.addEventListener('change.hs.select', onWarehouseChange);
                }

                /**
                 * El vendedor es propio de cada pre-cuenta (ticket), igual
                 * que la bodega -- una caja puede facturar a nombre de
                 * varios vendedores en pestañas distintas.
                 * @returns {void}
                 */
                function bindSellerSelect() {
                    const sellerSelect = document.getElementById('pos-seller-select');
                    if (! sellerSelect) {
                        return;
                    }

                    const onSellerChange = () => {
                        activeTicket().sellerId = sellerSelect.value;
                    };
                    sellerSelect.addEventListener('change', onSellerChange);
                    sellerSelect.addEventListener('change.hs.select', onSellerChange);
                }

                // --- Carrito ---

                /**
                 * Arma una línea de carrito para un producto en una bodega
                 * puntual. Sin "precio base" aparte: si el producto tiene
                 * tipos de precio configurados, el primero de la lista (en
                 * el orden en que se agregaron) es el que aplica por
                 * defecto -- solo se cae al precio propio del producto si no
                 * tiene ningún tipo de precio. `availableWarehouses` trae
                 * todas las bodegas con stock de este producto, para que el
                 * cajero pueda cambiar de cuál está sacando desde el
                 * selector de la línea (igual que en facturación
                 * electrónica).
                 * @param {object} product Shape de mapProductsForJs().
                 * @param {string|null} warehouseId
                 * @param {string|null} warehouseName
                 * @param {number|null} warehouseStock
                 * @returns {object} Línea de carrito.
                 */
                function makeCartLine(product, warehouseId, warehouseName, warehouseStock) {
                    const prices = product.prices || [];
                    const defaultPrice = prices[0] ?? null;

                    return {
                        code: product.code,
                        barcode: product.barcode,
                        description: product.description,
                        unit_code: product.unit_code || 'EA',
                        basePrice: product.unit_price,
                        prices,
                        priceTypeId: defaultPrice?.price_type_id ?? null,
                        unit_price: defaultPrice?.price ?? product.unit_price,
                        qty: 1,
                        warehouseId: warehouseId,
                        warehouseName: warehouseName,
                        warehouseStock: warehouseStock,
                        availableWarehouses: warehousesWithStock(product),
                    };
                }

                /**
                 * Bodegas reales (no la entrada "sin asignar") con stock > 0
                 * para un producto -- si no tiene ninguna, se vende sin tope
                 * de cantidad (mismo criterio que un producto que no
                 * controla inventario).
                 * @param {object} product
                 * @returns {Array}
                 */
                function warehousesWithStock(product) {
                    return (product.warehouses || []).filter((w) => w.warehouse_id && (w.stock ?? 0) > 0);
                }

                /**
                 * Agrega un producto al carrito de la pre-cuenta activa,
                 * atrapando cualquier error para que no deje el POS como
                 * "congelado" sin decir nada -- se ve en consola para
                 * depurar y se le avisa al cajero en vez de quedarse
                 * callado. Ver addToCartUnsafe() para la lógica real: cada
                 * línea del carrito queda ligada a UNA bodega puntual, con
                 * la cantidad topada al stock real de esa bodega (igual que
                 * en facturación electrónica); si el cajero sigue agregando
                 * el mismo producto y esa bodega ya se agotó en el carrito,
                 * en vez de bloquear se abre una línea nueva sacando de otra
                 * bodega que sí tenga stock, y si no hay más bodegas con
                 * stock, no se agrega nada más.
                 * @param {object} product
                 * @param {HTMLElement} cardEl Tarjeta clickeada, para anclar el error si falla.
                 * @returns {void}
                 */
                function addToCart(product, cardEl) {
                    try {
                        addToCartUnsafe(product, cardEl);
                    } catch (error) {
                        console.error('addToCart failed', error);
                        showError('{{ __('Could not add the product to the cart.') }}');
                    }
                }

                /**
                 * Hace parpadear en rojo la tarjeta de producto y flota un
                 * mensaje sobre ELLA (reusa el tooltip flotante de los
                 * badges), en vez del banner genérico de arriba -- así queda
                 * claro a cuál producto le faltó stock cuando hay varios en
                 * pantalla. Se quitan las clases de borde normal y se ponen
                 * las rojas (dejarlas todas juntas no sirve: Tailwind no
                 * garantiza que "border-red-400" gane solo por venir después
                 * en el HTML, misma especificidad que "border-zinc-200";
                 * gana la que quede después en la hoja de estilos compilada,
                 * no en el classList). "hover:border-accent" también hay
                 * que quitarlo mientras dure el rojo -- si no, con el mouse
                 * encima (que es justo donde está, recién le dieron clic) el
                 * hover se lo gana y el borde vuelve al color normal de la
                 * app.
                 * @param {HTMLElement} cardEl
                 * @param {string} message
                 * @returns {void}
                 */
                function flashCardError(cardEl, message) {
                    if (! cardEl) {
                        showError(message);
                        return;
                    }
                    cardEl.classList.remove('border-zinc-200', 'dark:border-neutral-700', 'hover:border-accent');
                    cardEl.classList.add('border-red-400', 'dark:border-red-500');
                    showFloatingTooltip(cardEl, [[message, '']]);
                    setTimeout(() => {
                        cardEl.classList.remove('border-red-400', 'dark:border-red-500');
                        cardEl.classList.add('border-zinc-200', 'dark:border-neutral-700', 'hover:border-accent');
                        hideFloatingTooltip();
                    }, 4000);
                }

                /**
                 * Lógica real de agregar un producto al carrito (ver
                 * addToCart() para el wrapper con try/catch). Si hay un
                 * filtro de bodega activo en el buscador, se prefiere esa
                 * (es de donde el cajero está mirando el producto), pero
                 * solo si de verdad tiene stock. Si todas las bodegas con
                 * stock de este producto ya están topadas en el carrito, no
                 * hay de dónde sacar más y se avisa con flashCardError() en
                 * vez de quedarse en silencio.
                 * @param {object} product
                 * @param {HTMLElement} cardEl
                 * @returns {void}
                 */
                function addToCartUnsafe(product, cardEl) {
                    const cart = activeTicket().cart;
                    const availableWarehouses = warehousesWithStock(product);

                    if (availableWarehouses.length === 0) {
                        const existing = cart.find((line) => line.code === product.code && ! line.warehouseId);
                        if (existing) {
                            existing.qty += 1;
                        } else {
                            cart.push(makeCartLine(product, null, null, null));
                        }
                        renderCart();
                        renderTickets();
                        return;
                    }

                    const filterWarehouseId = document.getElementById('pos-warehouse-filter').value;
                    const preferred = (filterWarehouseId && filterWarehouseId !== 'all')
                        ? availableWarehouses.find((w) => w.warehouse_id === filterWarehouseId)
                        : null;
                    const ordered = preferred
                        ? [preferred, ...availableWarehouses.filter((w) => w !== preferred)]
                        : availableWarehouses;

                    for (const w of ordered) {
                        const existing = cart.find((line) => line.code === product.code && line.warehouseId === w.warehouse_id);
                        if (existing) {
                            if (existing.qty < w.stock) {
                                existing.qty += 1;
                                renderCart();
                                renderTickets();
                                return;
                            }
                            continue; // esta bodega ya está topada en el carrito, probar la siguiente
                        }

                        cart.push(makeCartLine(product, w.warehouse_id, w.warehouse_name, w.stock));
                        renderCart();
                        renderTickets();
                        return;
                    }

                    flashCardError(cardEl, '{{ __('There is no more stock of this product in any warehouse.') }}');
                }

                /**
                 * Repinta el carrito, con blindaje contra recursión:
                 * reconstruirlo dispara eventos propios de Preline (el
                 * stepper de cantidad, los dropdowns) que pueden terminar
                 * llamando a renderCart() de nuevo antes de que la llamada
                 * anterior termine -- sin este seguro, eso arma un bucle
                 * infinito y revienta la pila (visto como cientos de
                 * "Maximum call stack size exceeded" en consola). Si ya se
                 * está reconstruyendo, cualquier llamada de más simplemente
                 * no hace nada.
                 * @returns {void}
                 */
                let isRenderingCart = false;
                function renderCart() {
                    if (isRenderingCart) {
                        return;
                    }
                    isRenderingCart = true;
                    try {
                        renderCartUnsafe();
                    } finally {
                        isRenderingCart = false;
                    }
                }

                /**
                 * Reconstruye el cuerpo del carrito. Todos los campos de una
                 * línea quedan "pegados" en una sola tira (mismo recurso
                 * visual que usa el proyecto WorkFlow para sus líneas de
                 * presupuesto de contrato): un grid con columnas fijas, sin
                 * separación entre campos, redondeando solo las puntas -- se
                 * ve como un único campo continuo en vez de varias cajas
                 * sueltas. Los botones +/- del stepper de Preline no
                 * disparan un "input" nativo (el plugin solo pone el valor a
                 * mano), así que hace falta escuchar también su propio
                 * evento, además del "input" normal para cuando se escribe
                 * la cantidad a mano; la cantidad se topa siempre al stock
                 * real de la bodega de esa línea, porque el "max" del
                 * stepper de Preline no siempre alcanza a frenar lo que se
                 * escribe a mano. El selector de bodega de cada línea usa el
                 * mismo patrón visual que "Lista de precios" (ícono chiquito
                 * con un menú, no un <select> con el nombre a la vista): al
                 * cambiarla, la cantidad se topa al stock de la nueva
                 * bodega. Si el precio se escribe a mano, se toma tal cual
                 * (ya no corresponde a ningún tipo de precio del catálogo,
                 * así que el selector de tipo de precio vuelve a "Precio
                 * base"). El listener "change.hs.inputNumber" del stepper de
                 * cantidad se engancha DESPUÉS de construir el componente
                 * HSInputNumber: Preline dispara su propio evento de
                 * inicialización al construirse, y si el listener ya
                 * estuviera puesto, ese evento llamaba a renderCart(), que
                 * reconstruía TODO, que volvía a construir el componente,
                 * que volvía a disparar el evento... un stack overflow por
                 * recursión infinita.
                 * @returns {void}
                 */
                function renderCartUnsafe() {
                    const cart = activeTicket().cart;
                    const body = document.getElementById('pos-cart-body');
                    const empty = document.getElementById('pos-cart-empty');
                    body.innerHTML = '';
                    empty.classList.toggle('hidden', cart.length > 0);

                    cart.forEach((line, index) => {
                        const row = document.createElement('div');
                        row.className = 'p-2.5';

                        const priceOptions = line.prices
                            .map((p) => ({ id: p.price_type_id, name: p.price_type_name }))
                            .map((option) => `
                                <button type="button" data-price-type-id="${option.id}" class="pos-line-price-type-option w-full text-start px-3 py-1.5 text-sm rounded-lg ${(line.priceTypeId || '') === option.id ? 'bg-accent/10 text-accent' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10'} focus:outline-hidden">
                                    ${escapeHtml(option.name)}
                                </button>
                            `).join('');

                        const warehouseOptions = line.availableWarehouses.map((w) => `
                            <button type="button" data-warehouse-id="${w.warehouse_id}" data-stock="${w.stock}" class="pos-line-warehouse-option w-full text-start px-3 py-1.5 text-sm rounded-lg ${line.warehouseId === w.warehouse_id ? 'bg-accent/10 text-accent' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10'} focus:outline-hidden">
                                ${escapeHtml(w.warehouse_name)} (${w.stock})
                            </button>
                        `).join('');

                        row.innerHTML = `
                            <div class="grid flex-1 min-w-0" style="grid-template-columns: 1fr 76px ${line.availableWarehouses.length > 0 ? '40px' : ''} 112px 108px 40px;">
                                <div class="h-9 flex items-center px-2.5 border border-e-0 border-zinc-200 dark:border-white/10 rounded-s-lg bg-white dark:bg-white/10 text-sm text-gray-800 dark:text-white truncate" title="${escapeHtml(line.description)}">
                                    ${escapeHtml(line.description)}
                                </div>

                                <div class="pos-line-qty-wrapper bg-white dark:bg-white/10 border border-e-0 border-zinc-200 dark:border-white/10 h-9" data-hs-input-number='${JSON.stringify(line.warehouseStock !== null && line.warehouseStock !== undefined ? { step: 1, min: 1, max: line.warehouseStock } : { step: 1, min: 1 })}'>
                                    <div class="w-full h-full flex justify-between items-center">
                                        <div class="grow px-1 text-center">
                                            <input class="pos-line-qty w-full h-full p-0 bg-transparent border-0 text-sm text-center text-zinc-700 dark:text-zinc-300 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" style="-moz-appearance: textfield;" type="number" aria-roledescription="{{ __('Quantity') }}" value="${line.qty}" ${line.warehouseStock !== null && line.warehouseStock !== undefined ? `max="${line.warehouseStock}"` : ''} data-hs-input-number-input>
                                        </div>
                                        <div class="flex flex-col h-full divide-y divide-zinc-200 dark:divide-white/10 border-s border-zinc-200 dark:border-white/10">
                                            <button type="button" class="flex-1 w-6 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Increase') }}" data-hs-input-number-increment>
                                                <svg class="shrink-0 size-2.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                            </button>
                                            <button type="button" class="flex-1 w-6 inline-flex justify-center items-center text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" aria-label="{{ __('Decrease') }}" data-hs-input-number-decrement>
                                                <svg class="shrink-0 size-2.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                ${line.availableWarehouses.length > 0 ? `
                                    <div class="hs-dropdown [--auto-close:false] relative h-9 flex items-center justify-center border border-e-0 border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10" data-warehouse-dropdown>
                                        <button type="button" class="hs-dropdown-toggle size-6 inline-flex items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 dark:text-neutral-400 dark:hover:bg-white/10" title="{{ __('Warehouse') }}: ${escapeHtml(line.warehouseName || '')}">
                                            <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/></svg>
                                        </button>
                                        <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-48 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 space-y-0.5 dark:bg-neutral-800 dark:border-neutral-700">
                                            ${warehouseOptions}
                                        </div>
                                    </div>
                                ` : ''}

                                <div class="relative h-9">
                                    <input type="text" inputmode="decimal" data-action="price" value="${line.unit_price.toLocaleString('es-CO')}"
                                        class="h-9 w-full border border-e-0 border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 text-sm ps-5 ${line.prices.length > 0 ? 'pe-7' : 'pe-2'} focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                                    <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-2">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                    </div>
                                    ${line.prices.length > 0 ? `
                                        <div class="hs-dropdown [--auto-close:false] absolute inset-y-0 end-0 flex items-center pe-1" data-price-type-dropdown>
                                            <button type="button" class="hs-dropdown-toggle size-6 inline-flex items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 dark:text-neutral-400 dark:hover:bg-white/10" title="{{ __('Price list') }}">
                                                <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                            <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-40 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 space-y-0.5 dark:bg-neutral-800 dark:border-neutral-700">
                                                ${priceOptions}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>

                                <div class="relative h-9">
                                    <input type="text" readonly disabled value="${formatMoney(line.unit_price * line.qty)}"
                                        class="h-9 w-full border border-e-0 border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 text-zinc-800 dark:text-neutral-100 text-sm font-semibold text-end px-2.5 disabled:opacity-100">
                                </div>

                                <button type="button" data-action="remove" class="h-9 w-full inline-flex items-center justify-center rounded-e-lg border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 text-gray-400 hover:bg-red-50 hover:text-red-600 hover:border-red-200 dark:hover:bg-red-900/20 dark:hover:text-red-400 dark:hover:border-red-900/40 focus:outline-hidden focus:z-10 focus:ring-2 focus:ring-accent" title="{{ __('Delete') }}">
                                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                </button>
                            </div>
                        `;
                        const qtyWrapper = row.querySelector('.pos-line-qty-wrapper');
                        const qtyInput = row.querySelector('.pos-line-qty');
                        const applyQtyChange = () => {
                            let qty = parseFloat(qtyInput.value) || 0;
                            if (line.warehouseStock !== null && line.warehouseStock !== undefined && qty > line.warehouseStock) {
                                qty = line.warehouseStock;
                            }
                            if (qty <= 0) {
                                cart.splice(index, 1);
                            } else {
                                line.qty = qty;
                            }
                            renderCart();
                            renderTickets();
                        };
                        qtyInput.addEventListener('input', applyQtyChange);

                        const lineWarehouseDropdown = row.querySelector('[data-warehouse-dropdown]');
                        row.querySelectorAll('.pos-line-warehouse-option').forEach((option) => {
                            option.addEventListener('click', () => {
                                line.warehouseId = option.dataset.warehouseId;
                                line.warehouseName = line.availableWarehouses.find((w) => w.warehouse_id === line.warehouseId)?.warehouse_name ?? null;
                                line.warehouseStock = parseFloat(option.dataset.stock) || 0;
                                if (line.qty > line.warehouseStock) {
                                    line.qty = line.warehouseStock;
                                }
                                if (window.HSDropdown && lineWarehouseDropdown) {
                                    HSDropdown.close(lineWarehouseDropdown);
                                }
                                renderCart();
                                renderTickets();
                            });
                        });

                        row.querySelector('[data-action="remove"]').addEventListener('click', () => { cart.splice(index, 1); renderCart(); renderTickets(); });

                        row.querySelector('[data-action="price"]').addEventListener('change', (event) => {
                            line.unit_price = parseFloat(event.target.value.replace(/[^0-9.-]/g, '')) || 0;
                            line.priceTypeId = null;
                            renderCart();
                            renderTickets();
                        });

                        const linePriceTypeDropdown = row.querySelector('[data-price-type-dropdown]');
                        row.querySelectorAll('.pos-line-price-type-option').forEach((option) => {
                            option.addEventListener('click', () => {
                                const priceTypeId = option.dataset.priceTypeId;
                                line.priceTypeId = priceTypeId || null;
                                line.unit_price = priceTypeId
                                    ? (line.prices.find((p) => p.price_type_id === priceTypeId)?.price ?? line.basePrice)
                                    : line.basePrice;
                                if (window.HSDropdown && linePriceTypeDropdown) {
                                    HSDropdown.close(linePriceTypeDropdown);
                                }
                                renderCart();
                                renderTickets();
                            });
                        });

                        body.appendChild(row);
                        initDropdowns(row);
                        if (window.HSInputNumber) {
                            new HSInputNumber(qtyWrapper);
                        }
                        qtyWrapper.addEventListener('change.hs.inputNumber', applyQtyChange);
                    });

                    updateTotal();
                }

                /**
                 * Instancia a mano los dropdowns de Preline dentro de
                 * container (tipo de precio general del carrito, tipo de
                 * precio por línea, bodega por línea): el autoInit global de
                 * Preline no los detecta porque se insertan dinámicamente,
                 * igual que ya hace documents/create.blade.php con sus
                 * propios popovers dinámicos (impuestos por línea, etc.).
                 * @param {HTMLElement} container
                 * @returns {void}
                 */
                function initDropdowns(container) {
                    if (! window.HSDropdown) {
                        return;
                    }
                    container.querySelectorAll('.hs-dropdown').forEach((el) => new HSDropdown(el));
                }

                /**
                 * Cierra cualquier dropdown abierto si el clic fue
                 * realmente afuera. Los popovers usan "--auto-close:false"
                 * para que Preline no los cierre solo con cualquier clic
                 * adentro (como al escribir en el precio), así que hace
                 * falta este cierre manual; es un solo listener delegado (no
                 * uno por fila, que se reconstruye entera en cada
                 * renderCart()) para no acumular listeners duplicados. Un
                 * dropdown "huérfano" (su fila ya se reconstruyó en otro
                 * renderCart()) no debe frenar el resto de este mismo clic
                 * global, por eso el try/catch por elemento.
                 * @returns {void}
                 */
                function bindDropdownOutsideClose() {
                    document.addEventListener('click', (event) => {
                        document.querySelectorAll('.hs-dropdown.open').forEach((el) => {
                            try {
                                if (! el.contains(event.target) && window.HSDropdown) {
                                    HSDropdown.close(el);
                                }
                            } catch (error) {
                                console.error('HSDropdown.close failed', error);
                            }
                        });
                    });
                }

                /**
                 * Aplica un tipo de precio a todas las líneas del carrito
                 * cuyo producto sí lo tenga -- las demás se quedan con lo
                 * que tenían (mismo criterio que la facturación
                 * electrónica).
                 * @param {string} priceTypeId
                 * @returns {void}
                 */
                function applyPriceTypeToCart(priceTypeId) {
                    const cart = activeTicket().cart;
                    cart.forEach((line) => {
                        const match = line.prices.find((p) => p.price_type_id === priceTypeId);
                        if (! match) {
                            return;
                        }
                        line.priceTypeId = priceTypeId;
                        line.unit_price = match.price;
                    });
                    renderCart();
                    renderTickets();
                }

                function cartTotal() {
                    return activeTicket().cart.reduce((sum, line) => sum + (line.unit_price * line.qty), 0);
                }

                /**
                 * Recalcula y pinta el total del carrito. Con un solo medio
                 * de pago (el caso normal) el monto se mantiene igual al
                 * total del carrito solo -- con varios ya es el usuario
                 * quien reparte, no se le pisa lo que haya repartido.
                 * @returns {void}
                 */
                function updateTotal() {
                    document.getElementById('pos-total-display').textContent = formatMoney(cartTotal());

                    const ticket = activeTicket();
                    if (ticket && ticket.payments.length === 1) {
                        ticket.payments[0].amount = cartTotal();
                        renderPaymentLines();
                    } else if (ticket) {
                        updatePaymentRemaining();
                    }

                    updatePosCashSectionVisibility();
                }

                // --- Cliente ---

                function selectClient(client) {
                    activeTicket().client = client;
                    document.getElementById('pos-client-search').value = client.name;
                    document.getElementById('pos-client-identificacion').textContent = client.identificacion;
                    document.getElementById('pos-client-results').classList.add('hidden');
                }

                /**
                 * Engancha la búsqueda de cliente y el alta de uno nuevo.
                 * Usa el mismo panel completo de alta de cliente que la
                 * pantalla de Clientes
                 * (third-parties/partials/form-panel.blade.php);
                 * window.thirdPartyPanelOnSave hace que, al guardar, el
                 * cliente quede seleccionado en la pre-cuenta activa en vez
                 * de navegar a otra página.
                 * @returns {void}
                 */
                function bindClientControls() {
                    document.getElementById('pos-client-search').addEventListener('input', debounce(async (event) => {
                        const query = event.target.value.trim();
                        const results = document.getElementById('pos-client-results');
                        if (query === '') {
                            results.classList.add('hidden');
                            return;
                        }

                        const response = await fetch(clientSearchUrl + '?q=' + encodeURIComponent(query));
                        const data = await response.json();
                        const clients = data.clients || [];

                        results.innerHTML = '';
                        if (clients.length === 0) {
                            const emptyItem = document.createElement('button');
                            emptyItem.type = 'button';
                            emptyItem.className = 'w-full text-start px-3 py-2 text-sm text-accent hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10';
                            emptyItem.textContent = '{{ __('No results.') }} {{ __('Create client') }} "' + query + '"';
                            emptyItem.addEventListener('click', () => {
                                results.classList.add('hidden');
                                window.openThirdPartyPanel({ identificacion: query });
                            });
                            results.appendChild(emptyItem);
                        } else {
                            clients.forEach((client) => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'w-full text-start px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10';
                                item.innerHTML = `<span class="block font-medium text-gray-800 dark:text-white">${escapeHtml(client.name)}</span><span class="block text-xs text-zinc-500 dark:text-neutral-400">${escapeHtml(client.identificacion)}</span>`;
                                item.addEventListener('click', () => selectClient(client));
                                results.appendChild(item);
                            });
                        }
                        results.classList.remove('hidden');
                    }, 300));

                    document.getElementById('pos-client-add-btn').addEventListener('click', () => {
                        window.openThirdPartyPanel({ identificacion: document.getElementById('pos-client-search').value });
                    });

                    window.thirdPartyPanelOnSave = selectClient;
                }

                // --- Pago ---
                // Una venta puede repartirse entre varios medios de pago
                // (ticket.payments = [{paymentMethodId, amount}, ...]); con
                // una sola línea (el caso normal) el monto se sincroniza solo
                // con el total del carrito, igual que antes.

                function paymentMethodById(id) {
                    return paymentMethods.find((m) => m.id === id) || null;
                }

                function parseAmount(value) {
                    return parseFloat((value || '0').replace(/[^0-9.-]/g, '')) || 0;
                }

                function assignedPaymentsTotal(ticket) {
                    return ticket.payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
                }

                function cashPaymentsTotal(ticket) {
                    return ticket.payments
                        .filter((p) => paymentMethodById(p.paymentMethodId)?.dianCode === '10')
                        .reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
                }

                function renderPaymentLines() {
                    const ticket = activeTicket();
                    const container = document.getElementById('pos-payment-lines');
                    container.innerHTML = '';

                    ticket.payments.forEach((payment, index) => {
                        const row = document.createElement('div');
                        row.className = 'grid gap-2 items-center';
                        row.style.gridTemplateColumns = '1fr 130px 32px';

                        const selectId = `pos-payment-method-${index}`;
                        const optionsHtml = paymentMethods.map((m) => `<option value="${m.id}" ${m.id === payment.paymentMethodId ? 'selected' : ''}>${escapeHtml(m.name)}</option>`).join('');
                        row.innerHTML = `
                            <select id="${selectId}" data-hs-select='${paymentSelectConfigAttr}' class="hidden">${optionsHtml}</select>
                            <div class="relative">
                                <input type="text" inputmode="decimal" data-payment-amount
                                    class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0" value="${payment.amount ? payment.amount : ''}">
                                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-2">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                                </div>
                            </div>
                            <button type="button" data-remove class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 ${ticket.payments.length <= 1 ? 'invisible' : ''}" aria-label="{{ __('Remove') }}">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            </button>
                        `;
                        container.appendChild(row);

                        const select = row.querySelector('select');
                        if (window.HSSelect) {
                            new HSSelect(select);
                        }
                        select.addEventListener('change', () => {
                            payment.paymentMethodId = select.value;
                            updatePosCashSectionVisibility();
                            updatePaymentRemaining();
                        });

                        const amountInput = row.querySelector('[data-payment-amount]');
                        amountInput.addEventListener('input', () => {
                            payment.amount = parseAmount(amountInput.value);
                            updatePosCashSectionVisibility();
                            updatePaymentRemaining();
                        });

                        const removeBtn = row.querySelector('[data-remove]');
                        if (ticket.payments.length > 1) {
                            removeBtn.addEventListener('click', () => {
                                ticket.payments.splice(index, 1);
                                renderPaymentLines();
                                updatePosCashSectionVisibility();
                                updatePaymentRemaining();
                            });
                        }
                    });

                    updatePaymentRemaining();
                }

                function updatePaymentRemaining() {
                    const ticket = activeTicket();
                    const remaining = Math.round((cartTotal() - assignedPaymentsTotal(ticket)) * 100) / 100;
                    const label = document.getElementById('pos-payment-remaining');
                    if (! label) {
                        return;
                    }
                    if (remaining === 0) {
                        label.classList.add('hidden');
                        return;
                    }
                    label.textContent = remaining > 0
                        ? '{{ __('Missing to assign') }}: ' + formatMoney(remaining)
                        : '{{ __('Assigned amount exceeds the total by') }} ' + formatMoney(Math.abs(remaining));
                    label.classList.remove('hidden');
                }

                function updatePosCashSectionVisibility() {
                    const ticket = activeTicket();
                    const isCash = cashPaymentsTotal(ticket) > 0;
                    document.getElementById('pos-cash-section').classList.toggle('hidden', ! isCash);
                    if (! isCash) {
                        document.getElementById('pos-efectivo-display').value = '';
                        ticket.efectivoRecibido = '';
                    }
                    updateChange();
                }

                function updateChange() {
                    const ticket = activeTicket();
                    const recibido = parseAmount(document.getElementById('pos-efectivo-display').value);
                    document.getElementById('pos-change-display').textContent = formatMoney(Math.max(recibido - cashPaymentsTotal(ticket), 0));
                }

                function bindPaymentControls() {
                    document.getElementById('pos-payment-add-btn')?.addEventListener('click', () => {
                        const ticket = activeTicket();
                        const remaining = Math.max(cartTotal() - assignedPaymentsTotal(ticket), 0);
                        const unusedMethod = paymentMethods.find((m) => ! ticket.payments.some((p) => p.paymentMethodId === m.id)) || paymentMethods[0];
                        ticket.payments.push({ paymentMethodId: unusedMethod?.id || null, amount: remaining });
                        renderPaymentLines();
                        updatePosCashSectionVisibility();
                    });

                    document.getElementById('pos-efectivo-display').addEventListener('input', (event) => {
                        activeTicket().efectivoRecibido = event.target.value;
                        updateChange();
                    });
                }

                // --- Checkout ---

                /**
                 * Engancha el botón de cobrar: manda la venta por fetch y,
                 * si sale bien, cierra esta pre-cuenta sola (sin pedir
                 * confirmación, el carrito ya no representa nada pendiente)
                 * y deja lista otra para la siguiente venta.
                 * @returns {void}
                 */
                /**
                 * Envía la edición de una venta ya guardada (modo
                 * editSaleId): manda cliente, vendedor, formas de pago y
                 * líneas por PUT -- mismo body y misma validación de saldo
                 * asignado que el cobro normal (ver bindCheckout() más
                 * abajo), solo que apunta a pos.sales.update en vez de
                 * pos.checkout. Al terminar va directo al detalle de la
                 * venta en vez de abrir el modal de "venta cobrada" (no hay
                 * recibo nuevo que mostrar, la venta ya estaba cobrada).
                 * @returns {Promise<void>}
                 */
                async function submitEditSale() {
                    const btn = document.getElementById('pos-checkout-btn');
                    document.getElementById('pos-checkout-error').classList.add('hidden');

                    const ticket = activeTicket();
                    const cart = ticket.cart;

                    if (cart.length === 0) {
                        showError('{{ __('Add at least one product to the cart.') }}');
                        return;
                    }

                    const remaining = Math.round((cartTotal() - assignedPaymentsTotal(ticket)) * 100) / 100;
                    if (remaining !== 0) {
                        showError(remaining > 0
                            ? '{{ __('Missing to assign') }}: ' + formatMoney(remaining)
                            : '{{ __('Assigned amount exceeds the total by') }} ' + formatMoney(Math.abs(remaining)));
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = '{{ __('Processing...') }}';

                    const client = ticket.client;

                    const body = new URLSearchParams();
                    body.append('cliente_tipo_identificacion', client.identification_type || '13');
                    body.append('cliente_identificacion', client.identificacion || '');
                    body.append('cliente_nombre', client.name || '');
                    body.append('cliente_tipo_persona', client.person_type || '2');
                    body.append('cliente_direccion', client.address || '');
                    body.append('cliente_departamento_codigo', client.department_code || '');
                    body.append('cliente_ciudad_codigo', client.city_code || '');
                    body.append('cliente_telefono', client.phone || '');
                    body.append('cliente_email', client.email || '');
                    if (ticket.sellerId) {
                        body.append('seller_id', ticket.sellerId);
                    }
                    ticket.payments.forEach((payment, index) => {
                        if (! payment.paymentMethodId) {
                            return;
                        }
                        body.append(`payment_method_id[${index}]`, payment.paymentMethodId);
                        body.append(`payment_method_amount[${index}]`, payment.amount || 0);
                    });
                    cart.forEach((line, index) => {
                        body.append(`items[${index}][codigo]`, line.code);
                        body.append(`items[${index}][codigo_barras]`, line.barcode || '');
                        body.append(`items[${index}][descripcion]`, line.description);
                        body.append(`items[${index}][unidad_medida]`, line.unit_code || 'EA');
                        body.append(`items[${index}][cantidad]`, line.qty);
                        body.append(`items[${index}][precio_unitario]`, line.unit_price);
                        if (line.warehouseId) {
                            body.append(`items[${index}][bodega_id]`, line.warehouseId);
                        }
                    });

                    try {
                        const response = await fetch(editSaleUpdateUrl, {
                            method: 'PUT',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: body.toString(),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            throw new Error(data.message || '{{ __('Could not update the sale.') }}');
                        }

                        window.location.href = data.show_url;
                    } catch (error) {
                        showError(error.message || '{{ __('Could not update the sale.') }}');
                        btn.disabled = false;
                        btn.textContent = '{{ __('Save changes') }}';
                    }
                }

                function bindCheckout() {
                document.getElementById('pos-checkout-btn').addEventListener('click', async () => {
                    if (editSaleId) {
                        await submitEditSale();
                        return;
                    }

                    const btn = document.getElementById('pos-checkout-btn');
                    document.getElementById('pos-checkout-error').classList.add('hidden');

                    const ticket = activeTicket();
                    const cart = ticket.cart;

                    if (cart.length === 0) {
                        showError('{{ __('Add at least one product to the cart.') }}');
                        return;
                    }

                    const remaining = Math.round((cartTotal() - assignedPaymentsTotal(ticket)) * 100) / 100;
                    if (remaining !== 0) {
                        showError(remaining > 0
                            ? '{{ __('Missing to assign') }}: ' + formatMoney(remaining)
                            : '{{ __('Assigned amount exceeds the total by') }} ' + formatMoney(Math.abs(remaining)));
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = '{{ __('Processing...') }}';

                    const client = ticket.client;
                    const efectivoDisplay = document.getElementById('pos-efectivo-display').value;
                    const efectivoRecibido = efectivoDisplay ? parseFloat(efectivoDisplay.replace(/[^0-9.-]/g, '')) : '';

                    const body = new URLSearchParams();
                    body.append('cliente_tipo_identificacion', client.identification_type || '13');
                    body.append('cliente_identificacion', client.identificacion || '');
                    body.append('cliente_nombre', client.name || '');
                    body.append('cliente_tipo_persona', client.person_type || '2');
                    body.append('cliente_direccion', client.address || '');
                    body.append('cliente_departamento_codigo', client.department_code || '');
                    body.append('cliente_ciudad_codigo', client.city_code || '');
                    body.append('cliente_telefono', client.phone || '');
                    body.append('cliente_email', client.email || '');
                    if (ticket.sellerId) {
                        body.append('seller_id', ticket.sellerId);
                    }
                    ticket.payments.forEach((payment, index) => {
                        if (! payment.paymentMethodId) {
                            return;
                        }
                        body.append(`payment_method_id[${index}]`, payment.paymentMethodId);
                        body.append(`payment_method_amount[${index}]`, payment.amount || 0);
                    });
                    if (efectivoRecibido !== '') {
                        body.append('efectivo_recibido', efectivoRecibido);
                    }
                    if (quotationId) {
                        body.append('quotation_id', quotationId);
                    }
                    cart.forEach((line, index) => {
                        body.append(`items[${index}][codigo]`, line.code);
                        body.append(`items[${index}][codigo_barras]`, line.barcode || '');
                        body.append(`items[${index}][descripcion]`, line.description);
                        body.append(`items[${index}][unidad_medida]`, line.unit_code || 'EA');
                        body.append(`items[${index}][cantidad]`, line.qty);
                        body.append(`items[${index}][precio_unitario]`, line.unit_price);
                        if (line.warehouseId) {
                            body.append(`items[${index}][bodega_id]`, line.warehouseId);
                        }
                    });

                    try {
                        const response = await fetch(checkoutUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: body.toString(),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            throw new Error(data.message || '{{ __('Could not issue the document.') }}');
                        }

                        currentSale = data;
                        document.getElementById('pos-result-numeral').textContent = data.numeral;
                        document.getElementById('pos-result-message').classList.add('hidden');
                        document.getElementById('pos-result-preview').src = data.receipt_preview_url;
                        const issueBtn = document.getElementById('pos-result-issue-electronic-btn');
                        document.getElementById('pos-result-issue-electronic-wrapper').classList.toggle('hidden', ! data.can_issue_electronic);
                        issueBtn.disabled = false;
                        issueBtn.textContent = '{{ __('Issue electronic invoice') }}';

                        tickets = tickets.filter((t) => t.id !== ticket.id);
                        if (tickets.length === 0) {
                            makeTicket();
                        }
                        activeTicketId = tickets[0].id;
                        renderTickets();
                        renderActiveTicket();

                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#pos-result-modal');
                        }
                    } catch (error) {
                        showError(error.message || '{{ __('Could not issue the document.') }}');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = '{{ __('Charge sale') }}';
                    }
                });
                }

                window.posIssueElectronic = async function () {
                    if (! currentSale) {
                        return;
                    }

                    const issueBtn = document.getElementById('pos-result-issue-electronic-btn');
                    const messageBox = document.getElementById('pos-result-message');
                    issueBtn.disabled = true;
                    issueBtn.textContent = '{{ __('Processing...') }}';

                    try {
                        const response = await fetch(currentSale.issue_electronic_url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            throw new Error(data.message || '{{ __('Could not issue the electronic invoice.') }}');
                        }

                        messageBox.className = 'mb-3 rounded-md p-3 text-sm ' + (data.accepted
                            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                            : 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400');
                        messageBox.textContent = data.accepted
                            ? '{{ __('Sale completed and accepted by the DIAN.') }}'
                            : '{{ __('The sale was sent, but the DIAN did not accept the electronic invoice yet.') }}';
                        document.getElementById('pos-result-issue-electronic-wrapper').classList.add('hidden');
                        document.getElementById('pos-result-preview').src = currentSale.receipt_preview_url;
                    } catch (error) {
                        /**
                         * No se vuelve a mostrar el botón: todo lo que
                         * puede rechazar esto (módulo desactivado, sin
                         * resolución electrónica, medio de pago sin
                         * mapeo DIAN, cliente sin dirección) necesita que
                         * el usuario vaya a arreglar algo en otra pantalla
                         * -- reintentar con el mismo clic nunca lo
                         * resuelve, así que dejar el botón visible solo
                         * invita a un segundo clic que va a fallar igual.
                         */
                        messageBox.className = 'mb-3 rounded-md p-3 text-sm bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        messageBox.textContent = error.message || '{{ __('Could not issue the electronic invoice.') }}';
                        document.getElementById('pos-result-issue-electronic-wrapper').classList.add('hidden');
                    }

                    messageBox.classList.remove('hidden');
                };

                window.posDownloadReceipt = function () {
                    if (currentSale) {
                        window.location.href = currentSale.receipt_url;
                    }
                };

                window.posPrintReceipt = function () {
                    if (currentSale) {
                        window.open(currentSale.receipt_preview_url, '_blank');
                    }
                };

                /**
                 * Navega a la lista de ventas. "Ir a ventas" navega de
                 * verdad (sale de esta pantalla), así que las pre-cuentas
                 * que sigan abiertas con productos se perderían sin avisar
                 * -- se pierde solo si el cajero confirma que quiere salir
                 * de todas formas.
                 * @returns {void}
                 */
                window.posGoToSales = function () {
                    const hasPendingTickets = tickets.some((t) => t.cart.length > 0);
                    if (hasPendingTickets && ! confirm('{{ __('You have other pre-bills open with products. If you leave, you will lose them. Continue?') }}')) {
                        return;
                    }
                    window.location.href = '{{ route('pos.sales.index') }}';
                };

                /**
                 * Cierra el modal de resultado. Ya quedó una pre-cuenta
                 * nueva lista desde que se cobró (ver el checkout de
                 * arriba), así que "Nueva venta" solo cierra el modal, sin
                 * recargar la página ni perder las demás pre-cuentas que
                 * sigan abiertas.
                 * @returns {void}
                 */
                window.posNewSale = function () {
                    if (window.HSOverlay) {
                        HSOverlay.close('#pos-result-modal');
                    }
                };

                function init() {
                    if (document.getElementById('pos-checkout-btn')?.dataset.bound === 'true') {
                        return;
                    }
                    const btn = document.getElementById('pos-checkout-btn');
                    if (! btn) {
                        return;
                    }
                    btn.dataset.bound = 'true';

                    const ticket = makeTicket();
                    activeTicketId = ticket.id;
                    if (quotationPrefill) {
                        ticket.client = { ...quotationPrefill.client };
                        ticket.cart = quotationPrefill.lines.map((line) => {
                            const warehouse = line.warehouse_id
                                ? (line.product.warehouses || []).find((w) => w.warehouse_id === line.warehouse_id)
                                : null;
                            return {
                                ...makeCartLine(line.product, warehouse?.warehouse_id ?? null, warehouse?.warehouse_name ?? null, warehouse?.stock ?? null),
                                qty: line.qty,
                                // El precio siempre es el que quedó guardado en
                                // la cotización, no el que makeCartLine tomó
                                // del catálogo actual (que puede ser otra
                                // lista de precios distinta a la que se usó
                                // al cotizar) -- por eso también se limpia
                                // priceTypeId, igual que cuando el cajero
                                // escribe un precio a mano.
                                unit_price: line.unit_price,
                                priceTypeId: null,
                            };
                        });
                    }
                    if (editSalePrefill) {
                        ticket.client = { ...editSalePrefill.client };
                        ticket.sellerId = editSalePrefill.seller_id || '';
                        ticket.cart = editSalePrefill.lines.map((line) => {
                            const warehouse = line.warehouse_id
                                ? (line.product.warehouses || []).find((w) => w.warehouse_id === line.warehouse_id)
                                : null;
                            return {
                                ...makeCartLine(line.product, warehouse?.warehouse_id ?? null, warehouse?.warehouse_name ?? null, warehouse?.stock ?? null),
                                qty: line.qty,
                                unit_price: line.unit_price,
                                priceTypeId: null,
                            };
                        });
                    }
                    if (editSaleId) {
                        document.getElementById('pos-ticket-add-btn')?.classList.add('hidden');
                        const checkoutBtn = document.getElementById('pos-checkout-btn');
                        if (checkoutBtn) {
                            checkoutBtn.textContent = '{{ __('Save changes') }}';
                        }
                    }
                    renderTickets();
                    renderActiveTicket();

                    document.getElementById('pos-ticket-add-btn').addEventListener('click', addTicket);

                    const bulkPriceTypeDropdown = document.getElementById('pos-bulk-price-type-btn')?.closest('.hs-dropdown');
                    initDropdowns(document);
                    bindDropdownOutsideClose();
                    document.querySelectorAll('.pos-bulk-price-type-option').forEach((option) => {
                        option.addEventListener('click', () => {
                            applyPriceTypeToCart(option.dataset.priceTypeId);
                            if (window.HSDropdown && bulkPriceTypeDropdown) {
                                HSDropdown.close(bulkPriceTypeDropdown);
                            }
                        });
                    });

                    bindProductSearch();
                    bindClientControls();
                    bindPaymentControls();
                    bindCheckout();
                    bindSellerSelect();

                    renderProducts(initialProducts);
                    updatePosCashSectionVisibility();

                    document.addEventListener('click', (event) => {
                        if (! event.target.closest('#pos-client-search') && ! event.target.closest('#pos-client-results')) {
                            document.getElementById('pos-client-results').classList.add('hidden');
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', init);
                document.addEventListener('livewire:navigated', init);
            })();
        </script>

        @include('third-parties.partials.form-panel-script', ['storeRoute' => 'clients.store'])

        <x-dian-acquirer-lookup-script />
    @endpush
</x-layouts.app>
