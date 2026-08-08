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

    // Armados acá (no inline en @json dentro del <script>): el directive
    // @json de Blade no procesa bien expresiones multilínea con paréntesis
    // anidados (closures, arrow functions), así que cualquier dato complejo
    // para JS se prepara antes en una variable simple.
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

    $initialProductsJs = $products->map(fn ($product) => [
        'id' => (string) $product->_id,
        'code' => $product->code,
        'barcode' => $product->barcode,
        'description' => $product->description,
        'unit_code' => $product->unit_code,
        'unit_price' => (float) $product->unit_price,
        'stock' => (float) $product->stock,
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

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 items-start">
        <!-- Columna izquierda: buscador + grid de productos -->
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="p-4 border-b border-gray-200 dark:border-neutral-700">
                <div class="relative">
                    <input type="text" id="pos-product-search" autocomplete="off"
                        placeholder="{{ __('Search by product, reference or code') }}"
                        class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base shadow-xs h-11 py-2 px-3 ps-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
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
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Cart') }}</h3>
                </div>
                <div id="pos-cart-body" class="divide-y divide-gray-200 dark:divide-neutral-700 max-h-[40vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                <p id="pos-cart-empty" class="p-4 text-sm text-zinc-500 dark:text-neutral-400">
                    <svg class="mx-auto mb-2 size-8 text-zinc-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span class="block text-center">{{ __("You don't have any products in the cart yet.") }}</span>
                </p>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 flex flex-col gap-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="pos-payment-method">{{ __('Payment method') }}</label>
                    <select id="pos-payment-method" class="h-10 py-2 px-3 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent">
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->_id }}" data-dian-code="{{ $paymentMethod->dian_payment_means_code }}">{{ $paymentMethod->name }}</option>
                        @endforeach
                    </select>
                    @if ($paymentMethods->isEmpty())
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('You have no payment methods configured yet.') }}</p>
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

                <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-neutral-700">
                    <span class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('Total') }}</span>
                    <span id="pos-total-display" class="text-xl font-bold text-gray-800 dark:text-neutral-200">$0.00</span>
                </div>

                <flux:button type="button" variant="primary" id="pos-checkout-btn" class="w-full">{{ __('Charge') }}</flux:button>
            </div>
        </div>
    </div>

    <!-- Modal: nuevo cliente rápido -->
    <div id="pos-client-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="pos-client-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="pos-client-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('New client') }}</h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#pos-client-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 flex flex-col gap-4">
                    <div id="pos-client-modal-error" class="hidden rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>

                    <div class="flex gap-3">
                        <div class="w-40 shrink-0">
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Identification type') }}</label>
                            <select id="pos-client-type" class="h-10 py-2 px-3 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent">
                                @foreach ($identificationTypes as $code => $label)
                                    <option value="{{ $code }}" @selected($code === '13')>{{ $code }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <flux:input id="pos-client-identification" :label="__('Identification')" required />
                        </div>
                    </div>

                    <flux:input id="pos-client-name" :label="__('Name')" required />
                    <flux:input id="pos-client-address" :label="__('Address')" />
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="text" inputmode="numeric" data-numeric-only id="pos-client-phone" :label="__('Phone')" />
                        <flux:input id="pos-client-email" type="email" :label="__('Email')" />
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('If this client will be issued an electronic invoice later, you can complete address, city and department from the Clients screen.') }}</p>
                </div>
                <div class="flex justify-end gap-2 p-4 pt-0">
                    <flux:button type="button" variant="filled" data-hs-overlay="#pos-client-modal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="button" variant="primary" id="pos-client-save-btn">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

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
                    <flux:button type="button" id="pos-result-issue-electronic-btn" variant="primary" icon="bolt" class="col-span-2 hidden" onclick="window.posIssueElectronic()">
                        {{ __('Issue electronic invoice') }}
                    </flux:button>
                    <flux:button type="button" variant="filled" icon="printer" onclick="window.posPrintReceipt()">{{ __('Print') }}</flux:button>
                    <flux:button type="button" variant="filled" icon="arrow-down-tray" onclick="window.posDownloadReceipt()">{{ __('Download') }}</flux:button>
                    <a href="{{ route('pos.sales.index') }}" class="col-span-2">
                        <flux:button type="button" variant="filled" icon="receipt-percent" class="w-full">{{ __('Go to sales') }}</flux:button>
                    </a>
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
                const clientStoreUrl = @json(route('clients.store'));
                const checkoutUrl = @json(route('pos.checkout'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                const defaultClient = @json($defaultClientJs);

                const initialProducts = @json($initialProductsJs);

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

                function makeTicket() {
                    const ticket = {
                        id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
                        number: nextTicketNumber++,
                        client: { ...defaultClient },
                        cart: [], // [{code, barcode, description, unit_code, unit_price, qty}]
                        paymentMethodId: null,
                        efectivoRecibido: '',
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

                function renderActiveTicket() {
                    const ticket = activeTicket();
                    if (! ticket) {
                        return;
                    }

                    document.getElementById('pos-client-search').value = ticket.client.name;
                    document.getElementById('pos-client-identificacion').textContent = ticket.client.identificacion;
                    document.getElementById('pos-client-results').classList.add('hidden');

                    const paymentSelect = document.getElementById('pos-payment-method');
                    if (ticket.paymentMethodId) {
                        paymentSelect.value = ticket.paymentMethodId;
                    } else {
                        paymentSelect.selectedIndex = 0;
                    }
                    document.getElementById('pos-efectivo-display').value = ticket.efectivoRecibido;

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

                function renderProducts(products) {
                    const grid = document.getElementById('pos-product-grid');
                    const empty = document.getElementById('pos-product-grid-empty');
                    grid.innerHTML = '';
                    empty.classList.toggle('hidden', products.length > 0);

                    products.forEach((product) => {
                        const card = document.createElement('button');
                        card.type = 'button';
                        card.className = 'flex flex-col gap-2 rounded-lg border border-zinc-200 dark:border-neutral-700 p-3 text-start hover:border-accent hover:shadow-sm focus:outline-hidden focus:ring-2 focus:ring-accent transition';
                        card.innerHTML = `
                            <div class="flex items-start gap-3">
                                <span class="flex items-center justify-center shrink-0 size-16 rounded-lg bg-accent/10 text-accent">
                                    <svg class="shrink-0 size-7" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                </span>
                                <span class="text-sm font-medium text-gray-800 dark:text-white line-clamp-3">${escapeHtml(product.description || '')}</span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-zinc-500 dark:text-neutral-400">
                                <span>${escapeHtml(product.code || '—')}</span>
                                <span>${escapeHtml(product.barcode || '—')}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Stock') }}: ${(product.stock ?? 0).toLocaleString('es-CO')}</span>
                                <span class="text-sm font-semibold text-gray-800 dark:text-neutral-200">${formatMoney(product.unit_price)}</span>
                            </div>
                        `;
                        card.addEventListener('click', () => addToCart(product));
                        grid.appendChild(card);
                    });
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                async function searchProducts(query) {
                    const response = await fetch(productSearchUrl + '?q=' + encodeURIComponent(query));
                    const data = await response.json();
                    renderProducts(data.products || []);
                }

                document.getElementById('pos-product-search').addEventListener('input', debounce((event) => {
                    searchProducts(event.target.value.trim());
                }, 300));

                // --- Carrito ---

                function addToCart(product) {
                    const cart = activeTicket().cart;
                    const existing = cart.find((line) => line.code === product.code);
                    if (existing) {
                        existing.qty += 1;
                    } else {
                        cart.push({
                            code: product.code,
                            barcode: product.barcode,
                            description: product.description,
                            unit_code: product.unit_code || 'EA',
                            unit_price: product.unit_price,
                            qty: 1,
                        });
                    }
                    renderCart();
                    renderTickets();
                }

                function renderCart() {
                    const cart = activeTicket().cart;
                    const body = document.getElementById('pos-cart-body');
                    const empty = document.getElementById('pos-cart-empty');
                    body.innerHTML = '';
                    empty.classList.toggle('hidden', cart.length > 0);

                    cart.forEach((line, index) => {
                        const row = document.createElement('div');
                        row.className = 'p-3 flex items-center gap-2';
                        row.innerHTML = `
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">${escapeHtml(line.description)}</p>
                                <p class="text-xs text-zinc-500 dark:text-neutral-400">${formatMoney(line.unit_price)}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" data-action="dec" class="size-7 inline-flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700">−</button>
                                <span class="w-8 text-center text-sm">${line.qty}</span>
                                <button type="button" data-action="inc" class="size-7 inline-flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700">+</button>
                            </div>
                            <p class="w-20 shrink-0 text-end text-sm font-semibold text-gray-800 dark:text-neutral-200">${formatMoney(line.unit_price * line.qty)}</p>
                            <button type="button" data-action="remove" class="shrink-0 size-7 inline-flex items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            </button>
                        `;
                        row.querySelector('[data-action="inc"]').addEventListener('click', () => { line.qty += 1; renderCart(); renderTickets(); });
                        row.querySelector('[data-action="dec"]').addEventListener('click', () => {
                            line.qty -= 1;
                            if (line.qty <= 0) { cart.splice(index, 1); }
                            renderCart();
                            renderTickets();
                        });
                        row.querySelector('[data-action="remove"]').addEventListener('click', () => { cart.splice(index, 1); renderCart(); renderTickets(); });
                        body.appendChild(row);
                    });

                    updateTotal();
                }

                function cartTotal() {
                    return activeTicket().cart.reduce((sum, line) => sum + (line.unit_price * line.qty), 0);
                }

                function updateTotal() {
                    document.getElementById('pos-total-display').textContent = formatMoney(cartTotal());
                    updateChange();
                }

                // --- Cliente ---

                function selectClient(client) {
                    activeTicket().client = client;
                    document.getElementById('pos-client-search').value = client.name;
                    document.getElementById('pos-client-identificacion').textContent = client.identificacion;
                    document.getElementById('pos-client-results').classList.add('hidden');
                }

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
                        results.innerHTML = `<div class="p-3 text-sm text-zinc-500 dark:text-neutral-400">{{ __('No results.') }}</div>`;
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
                    document.getElementById('pos-client-modal-error').classList.add('hidden');
                    document.getElementById('pos-client-identification').value = document.getElementById('pos-client-search').value;
                    document.getElementById('pos-client-name').value = '';
                    document.getElementById('pos-client-address').value = '';
                    document.getElementById('pos-client-phone').value = '';
                    document.getElementById('pos-client-email').value = '';
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#pos-client-modal');
                    }
                });

                document.getElementById('pos-client-save-btn').addEventListener('click', async () => {
                    const errorBox = document.getElementById('pos-client-modal-error');
                    errorBox.classList.add('hidden');

                    const payload = new URLSearchParams({
                        identification_type: document.getElementById('pos-client-type').value,
                        identificacion: document.getElementById('pos-client-identification').value,
                        name: document.getElementById('pos-client-name').value,
                        address: document.getElementById('pos-client-address').value,
                        phone: document.getElementById('pos-client-phone').value,
                        email: document.getElementById('pos-client-email').value,
                        person_type: '2',
                    });

                    try {
                        const response = await fetch(clientStoreUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: payload.toString(),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            const message = data.message || Object.values(data.errors || {}).flat().join(' ') || '{{ __('Could not save the client.') }}';
                            throw new Error(message);
                        }

                        selectClient(data.client);
                        if (window.HSOverlay) {
                            HSOverlay.close('#pos-client-modal');
                        }
                    } catch (error) {
                        errorBox.textContent = error.message;
                        errorBox.classList.remove('hidden');
                    }
                });

                // --- Pago ---

                function selectedPaymentOption() {
                    const select = document.getElementById('pos-payment-method');
                    return select.options[select.selectedIndex] || null;
                }

                function updatePosCashSectionVisibility() {
                    const option = selectedPaymentOption();
                    const isCash = option && option.dataset.dianCode === '10';
                    document.getElementById('pos-cash-section').classList.toggle('hidden', ! isCash);
                    if (! isCash) {
                        document.getElementById('pos-efectivo-display').value = '';
                    }
                    updateChange();
                }

                function updateChange() {
                    const recibido = parseFloat((document.getElementById('pos-efectivo-display').value || '0').replace(/[^0-9.-]/g, '')) || 0;
                    document.getElementById('pos-change-display').textContent = formatMoney(Math.max(recibido - cartTotal(), 0));
                }

                document.getElementById('pos-payment-method').addEventListener('change', () => {
                    activeTicket().paymentMethodId = selectedPaymentOption()?.value || null;
                    updatePosCashSectionVisibility();
                });
                document.getElementById('pos-efectivo-display').addEventListener('input', (event) => {
                    activeTicket().efectivoRecibido = event.target.value;
                    updateChange();
                });

                // --- Checkout ---

                document.getElementById('pos-checkout-btn').addEventListener('click', async () => {
                    const btn = document.getElementById('pos-checkout-btn');
                    document.getElementById('pos-checkout-error').classList.add('hidden');

                    const ticket = activeTicket();
                    const cart = ticket.cart;

                    if (cart.length === 0) {
                        showError('{{ __('Add at least one product to the cart.') }}');
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = '{{ __('Processing...') }}';

                    const client = ticket.client;
                    const paymentOption = selectedPaymentOption();
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
                    if (paymentOption) {
                        body.append('payment_method_id[0]', paymentOption.value);
                    }
                    if (efectivoRecibido !== '') {
                        body.append('efectivo_recibido', efectivoRecibido);
                    }
                    cart.forEach((line, index) => {
                        body.append(`items[${index}][codigo]`, line.code);
                        body.append(`items[${index}][codigo_barras]`, line.barcode || '');
                        body.append(`items[${index}][descripcion]`, line.description);
                        body.append(`items[${index}][unidad_medida]`, line.unit_code || 'EA');
                        body.append(`items[${index}][cantidad]`, line.qty);
                        body.append(`items[${index}][precio_unitario]`, line.unit_price);
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
                        document.getElementById('pos-result-preview').src = data.receipt_url + '?inline=1';
                        const issueBtn = document.getElementById('pos-result-issue-electronic-btn');
                        issueBtn.classList.toggle('hidden', ! data.can_issue_electronic);
                        issueBtn.disabled = false;
                        issueBtn.textContent = '{{ __('Issue electronic invoice') }}';

                        // La venta ya quedó registrada: esta pre-cuenta terminó
                        // su ciclo, se cierra sola (sin pedir confirmación,
                        // el carrito ya no representa nada pendiente) y se
                        // deja lista otra para la siguiente venta.
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
                        btn.textContent = '{{ __('Charge') }}';
                    }
                });

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
                        issueBtn.classList.add('hidden');
                        document.getElementById('pos-result-preview').src = currentSale.receipt_url + '?inline=1';
                    } catch (error) {
                        messageBox.className = 'mb-3 rounded-md p-3 text-sm bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        messageBox.textContent = error.message || '{{ __('Could not issue the electronic invoice.') }}';
                        issueBtn.disabled = false;
                        issueBtn.textContent = '{{ __('Issue electronic invoice') }}';
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
                        window.open(currentSale.receipt_url + '?inline=1', '_blank');
                    }
                };

                window.posNewSale = function () {
                    window.location.href = '{{ route('pos.create') }}';
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

                    renderProducts(initialProducts);
                    renderCart();
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
    @endpush
</x-layouts.app>
