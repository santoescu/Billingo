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

    $initialProductsJs = $products;
@endphp

<x-layouts.app :title="__('New quotation')">
    @include('partials.tittle', [
        'title' => __('Quotations'),
        'subheading' => __('Search a product and add it to build a quotation for a client.'),
    ])

    @unless ($hasResolution)
        <div class="mb-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
            {{ __('There is no active quotation numbering resolution. Create one from Resolutions before issuing a quotation.') }}
        </div>
    @endunless

    <div id="quote-error" class="hidden mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>

    <!-- Pre-cotizaciones: cada una es un cliente + carrito independiente,
         igual que las pre-cuentas del POS. -->
    <div class="mb-4 flex items-center gap-1 overflow-x-auto">
        <div id="quote-tickets-bar" class="flex items-center gap-1"></div>
        <button type="button" id="quote-ticket-add-btn"
            class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-accent dark:text-neutral-400 dark:hover:bg-neutral-700 focus:outline-hidden"
            aria-label="{{ __('New quotation') }}" title="{{ __('New quotation') }}">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-6 items-start">
        <!-- Columna izquierda: buscador + grid de productos -->
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="p-4 border-b border-gray-200 dark:border-neutral-700 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input type="text" id="quote-product-search" autocomplete="off"
                        placeholder="{{ __('Code, barcode or description') }}"
                        class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base shadow-xs h-10 py-2 px-3 ps-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                </div>
                <div class="sm:w-56 shrink-0">
                    {{-- "all" es un valor real seleccionable (no la cadena vacía),
                         mismo motivo que en pos/sell.blade.php: con "" como
                         valor Preline lo trata como el placeholder y ya no se
                         puede volver a elegir desde el dropdown. --}}
                    <select id="quote-warehouse-filter" data-hs-select='{!! \App\Support\SelectConfig::searchable() !!}' class="hidden">
                        <option value="all" selected>{{ __('All warehouses') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="quote-product-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[70vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
            <p id="quote-product-grid-empty" class="hidden p-4 text-sm text-zinc-500 dark:text-neutral-400">{{ __('No products found.') }}</p>
        </div>

        <!-- Columna derecha: cliente + carrito -->
        <div class="flex flex-col gap-4">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4">
                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Client') }}</label>
                <div class="relative">
                    <input type="text" id="quote-client-search" autocomplete="off"
                        class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs h-10 py-2 px-3 pe-9 focus:outline-hidden focus:ring-2 focus:ring-accent"
                        placeholder="{{ __('Search by name or identification') }}" value="{{ $defaultClient->name }}">
                    <button type="button" id="quote-client-add-btn"
                        class="absolute inset-y-0 end-0 flex items-center justify-center w-9 text-zinc-400 hover:text-accent focus:outline-hidden"
                        aria-label="{{ __('New client') }}" title="{{ __('New client') }}">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    </button>
                    <div id="quote-client-results" class="hidden absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-white/10 rounded-lg shadow-xl"></div>
                </div>
                <p id="quote-client-identificacion" class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ $defaultClient->identificacion }}</p>
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
                                    <button type="button" id="quote-bulk-price-type-btn" class="hs-dropdown-toggle inline-flex items-center gap-1 px-2 text-xs font-medium text-zinc-500 hover:text-accent dark:text-neutral-400 focus:outline-hidden" title="{{ __('Apply price type to all lines') }}">
                                        {{ __('Price list') }}
                                        <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 w-56 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 space-y-0.5 dark:bg-neutral-800 dark:border-neutral-700">
                                        @foreach ($priceTypes as $priceType)
                                            <button type="button" class="quote-bulk-price-type-option w-full text-start px-3 py-1.5 text-sm rounded-lg text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10" data-price-type-id="{{ $priceType->_id }}">
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
                <div id="quote-cart-body" class="divide-y divide-gray-200 dark:divide-neutral-700 max-h-[50vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                <p id="quote-cart-empty" class="p-4 text-sm text-zinc-500 dark:text-neutral-400">
                    <svg class="mx-auto mb-2 size-8 text-zinc-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span class="block text-center">{{ __("You don't have any products in the cart yet.") }}</span>
                </p>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 flex flex-col gap-3">
                <div class="flex justify-between items-center pt-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('Total') }}</span>
                    <span id="quote-total-display" class="text-xl font-bold text-gray-800 dark:text-neutral-200">$0.00</span>
                </div>

                <flux:button type="button" variant="primary" id="quote-submit-btn" class="w-full" :disabled="! $hasResolution">{{ __('Issue quotation') }}</flux:button>
            </div>
        </div>
    </div>

    @include('third-parties.partials.form-panel', ['panelLabel' => __('Client'), 'storeRoute' => 'clients.store'])

    <!-- Modal: resultado de la cotización -->
    <div id="quote-result-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="quote-result-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="quote-result-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Quotation created') }}</h3>
                    <span id="quote-result-numeral" class="text-sm text-zinc-500 dark:text-zinc-400"></span>
                </div>
                <div class="p-4">
                    <iframe id="quote-result-preview" class="w-full rounded-lg border border-gray-200 dark:border-neutral-700" style="height: 50vh;" title="{{ __('Quotation preview') }}"></iframe>
                </div>
                <div class="p-4 pt-0 grid grid-cols-2 gap-2">
                    <flux:button type="button" variant="filled" icon="printer" onclick="window.quotePrint()">{{ __('Print') }}</flux:button>
                    <flux:button type="button" variant="filled" icon="arrow-down-tray" onclick="window.quoteDownload()">{{ __('Download') }}</flux:button>
                    <flux:button type="button" variant="filled" icon="document-text" class="col-span-2" onclick="window.quoteGoToList()">{{ __('Go to quotations') }}</flux:button>
                    <flux:button type="button" variant="primary" class="col-span-2" onclick="window.quoteNew()">{{ __('New quotation') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const productSearchUrl = @json(route('documents.create-product-search'));
                const clientSearchUrl = @json(route('documents.create-client-search'));
                const storeUrl = @json(route('quotations.store'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                const defaultClient = @json($defaultClientJs);
                const initialProducts = @json($initialProductsJs);

                let nextTicketNumber = 1;
                let tickets = [];
                let activeTicketId = null;
                let currentQuotation = null;

                function makeTicket() {
                    const ticket = {
                        id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
                        number: nextTicketNumber++,
                        client: { ...defaultClient },
                        cart: [],
                        warehouseId: 'all',
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
                    if (ticket.cart.length > 0 && ! confirm('{{ __('This quotation has products in the cart. Close it anyway?') }}')) {
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
                    const bar = document.getElementById('quote-tickets-bar');
                    bar.innerHTML = '';

                    tickets.forEach((ticket) => {
                        const isActive = ticket.id === activeTicketId;
                        const tab = document.createElement('div');
                        tab.className = 'shrink-0 flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium cursor-pointer ' + (isActive
                            ? 'bg-accent/10 text-accent'
                            : 'text-zinc-500 hover:bg-zinc-100 dark:text-neutral-400 dark:hover:bg-neutral-700');
                        tab.innerHTML = `
                            <span>{{ __('Quotation') }} ${ticket.number}${ticket.cart.length ? ' (' + ticket.cart.length + ')' : ''}</span>
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

                    document.getElementById('quote-client-search').value = ticket.client.name;
                    document.getElementById('quote-client-identificacion').textContent = ticket.client.identificacion;
                    document.getElementById('quote-client-results').classList.add('hidden');

                    const warehouseSelect = document.getElementById('quote-warehouse-filter');
                    const warehouseInstance = window.HSSelect && HSSelect.getInstance(warehouseSelect);
                    if (warehouseInstance) {
                        warehouseInstance.setValue(ticket.warehouseId || 'all');
                    } else {
                        warehouseSelect.value = ticket.warehouseId || 'all';
                    }
                    searchProducts(document.getElementById('quote-product-search').value.trim());

                    renderCart();
                }

                function formatMoney(value) {
                    return '$' + (value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function showError(message) {
                    const box = document.getElementById('quote-error');
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

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                /**
                 * Tooltip flotante compartido por los badges de inventario
                 * (mismo recurso que usa pos/sell.blade.php): que una
                 * cotización no descuente stock no significa que no deba
                 * mostrarlo -- se ve exactamente igual que en el POS, solo
                 * que sin topar la cantidad a lo que hay.
                 * @returns {HTMLElement}
                 */
                function ensureFloatingTooltip() {
                    let el = document.getElementById('quote-floating-tooltip');
                    if (! el) {
                        el = document.createElement('div');
                        el.id = 'quote-floating-tooltip';
                        el.className = 'hidden fixed z-50 w-56 bg-white border border-zinc-200 rounded-lg shadow-xl p-2 text-xs dark:bg-zinc-700 dark:border-white/10';
                        document.body.appendChild(el);
                    }
                    return el;
                }

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
                    document.getElementById('quote-floating-tooltip')?.classList.add('hidden');
                }

                function tooltipBadge(label, lines, badgeClasses) {
                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-white/10 px-2 py-0.5 cursor-default ${badgeClasses}`;
                    badge.textContent = label;
                    badge.addEventListener('mouseenter', () => showFloatingTooltip(badge, lines));
                    badge.addEventListener('mouseleave', hideFloatingTooltip);
                    return badge;
                }

                // --- Grid de productos ---

                function renderProducts(products) {
                    const grid = document.getElementById('quote-product-grid');
                    const empty = document.getElementById('quote-product-grid-empty');
                    grid.innerHTML = '';
                    empty.classList.toggle('hidden', products.length > 0);

                    const warehouseId = document.getElementById('quote-warehouse-filter').value;
                    const hasWarehouseFilter = warehouseId && warehouseId !== 'all';

                    products.forEach((product) => {
                        const priceLines = (product.prices && product.prices.length > 0)
                            ? product.prices.map((p) => [p.price_type_name, formatMoney(p.price)])
                            : [['{{ __('Base price') }}', formatMoney(product.unit_price)]];

                        const warehouseStock = hasWarehouseFilter
                            ? product.warehouses?.find((w) => w.warehouse_id === warehouseId)?.stock
                            : null;
                        const stockToShow = warehouseStock !== null && warehouseStock !== undefined ? warehouseStock : product.stock;

                        const inventoryLines = (product.warehouses || [])
                            .map((w) => [w.warehouse_name, (w.stock ?? 0).toLocaleString('es-CO')]);
                        if (inventoryLines.length === 0) {
                            inventoryLines.push(['{{ __('No warehouse breakdown available.') }}', '']);
                        }

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

                async function searchProducts(query) {
                    // warehouse_id (aunque sea "all") hace que el mismo
                    // endpoint de búsqueda de facturación exija más de 1
                    // unidad en total/en esa bodega puntual (igual que el
                    // POS) -- sin este parámetro no filtra por stock, porque
                    // facturación sí necesita poder referenciar productos sin
                    // existencias.
                    const warehouseId = document.getElementById('quote-warehouse-filter').value || 'all';
                    const params = new URLSearchParams({ q: query, warehouse_id: warehouseId });
                    const response = await fetch(productSearchUrl + '?' + params.toString());
                    const data = await response.json();
                    renderProducts(data.products || []);
                }

                function bindProductSearch() {
                    document.getElementById('quote-product-search').addEventListener('input', debounce((event) => {
                        searchProducts(event.target.value.trim());
                    }, 300));

                    const warehouseSelect = document.getElementById('quote-warehouse-filter');
                    const onWarehouseChange = () => {
                        activeTicket().warehouseId = warehouseSelect.value;
                        searchProducts(document.getElementById('quote-product-search').value.trim());
                    };
                    warehouseSelect.addEventListener('change', onWarehouseChange);
                    warehouseSelect.addEventListener('change.hs.select', onWarehouseChange);
                }

                // --- Carrito ---
                // El comportamiento de bodega es idéntico al del POS
                // (auto-asignar, topar cantidad al stock, partir en otra
                // línea cuando se agota): que una cotización no descuente
                // ese stock al guardarse no significa que deba comportarse
                // distinto mientras se arma -- la bodega elegida igual se
                // lleva al convertir la cotización en venta de POS o factura
                // electrónica (ver mapQuotationLinesForJs() en
                // QuotationController).

                function warehousesWithStock(product) {
                    return (product.warehouses || []).filter((w) => w.warehouse_id && (w.stock ?? 0) > 0);
                }

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

                function addToCart(product, cardEl) {
                    try {
                        addToCartUnsafe(product, cardEl);
                    } catch (error) {
                        console.error('addToCart failed', error);
                        showError('{{ __('Could not add the product to the cart.') }}');
                    }
                }

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

                    const filterWarehouseId = document.getElementById('quote-warehouse-filter').value;
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
                            continue;
                        }

                        cart.push(makeCartLine(product, w.warehouse_id, w.warehouse_name, w.stock));
                        renderCart();
                        renderTickets();
                        return;
                    }

                    flashCardError(cardEl, '{{ __('There is no more stock of this product in any warehouse.') }}');
                }

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

                function renderCartUnsafe() {
                    const cart = activeTicket().cart;
                    const body = document.getElementById('quote-cart-body');
                    const empty = document.getElementById('quote-cart-empty');
                    body.innerHTML = '';
                    empty.classList.toggle('hidden', cart.length > 0);

                    cart.forEach((line, index) => {
                        const row = document.createElement('div');
                        row.className = 'p-2.5';

                        const priceOptions = line.prices
                            .map((p) => ({ id: p.price_type_id, name: p.price_type_name }))
                            .map((option) => `
                                <button type="button" data-price-type-id="${option.id}" class="quote-line-price-type-option w-full text-start px-3 py-1.5 text-sm rounded-lg ${(line.priceTypeId || '') === option.id ? 'bg-accent/10 text-accent' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10'} focus:outline-hidden">
                                    ${escapeHtml(option.name)}
                                </button>
                            `).join('');

                        const warehouseOptions = line.availableWarehouses.map((w) => `
                            <button type="button" data-warehouse-id="${w.warehouse_id}" data-stock="${w.stock}" class="quote-line-warehouse-option w-full text-start px-3 py-1.5 text-sm rounded-lg ${line.warehouseId === w.warehouse_id ? 'bg-accent/10 text-accent' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10'} focus:outline-hidden">
                                ${escapeHtml(w.warehouse_name)} (${w.stock})
                            </button>
                        `).join('');

                        row.innerHTML = `
                            <div class="grid flex-1 min-w-0" style="grid-template-columns: 1fr 76px ${line.availableWarehouses.length > 0 ? '40px' : ''} 112px 108px 40px;">
                                <div class="h-9 flex items-center px-2.5 border border-e-0 border-zinc-200 dark:border-white/10 rounded-s-lg bg-white dark:bg-white/10 text-sm text-gray-800 dark:text-white truncate" title="${escapeHtml(line.description)}">
                                    ${escapeHtml(line.description)}
                                </div>

                                <div class="quote-line-qty-wrapper bg-white dark:bg-white/10 border border-e-0 border-zinc-200 dark:border-white/10 h-9" data-hs-input-number='${JSON.stringify(line.warehouseStock !== null && line.warehouseStock !== undefined ? { step: 1, min: 1, max: line.warehouseStock } : { step: 1, min: 1 })}'>
                                    <div class="w-full h-full flex justify-between items-center">
                                        <div class="grow px-1 text-center">
                                            <input class="quote-line-qty w-full h-full p-0 bg-transparent border-0 text-sm text-center text-zinc-700 dark:text-zinc-300 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" style="-moz-appearance: textfield;" type="number" aria-roledescription="{{ __('Quantity') }}" value="${line.qty}" ${line.warehouseStock !== null && line.warehouseStock !== undefined ? `max="${line.warehouseStock}"` : ''} data-hs-input-number-input>
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
                        const qtyWrapper = row.querySelector('.quote-line-qty-wrapper');
                        const qtyInput = row.querySelector('.quote-line-qty');
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
                        row.querySelectorAll('.quote-line-warehouse-option').forEach((option) => {
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
                        row.querySelectorAll('.quote-line-price-type-option').forEach((option) => {
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

                function initDropdowns(container) {
                    if (! window.HSDropdown) {
                        return;
                    }
                    container.querySelectorAll('.hs-dropdown').forEach((el) => new HSDropdown(el));
                }

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

                function updateTotal() {
                    document.getElementById('quote-total-display').textContent = formatMoney(cartTotal());
                }

                // --- Cliente ---

                function selectClient(client) {
                    activeTicket().client = client;
                    document.getElementById('quote-client-search').value = client.name;
                    document.getElementById('quote-client-identificacion').textContent = client.identificacion;
                    document.getElementById('quote-client-results').classList.add('hidden');
                }

                function bindClientControls() {
                    document.getElementById('quote-client-search').addEventListener('input', debounce(async (event) => {
                        const query = event.target.value.trim();
                        const results = document.getElementById('quote-client-results');
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

                    document.getElementById('quote-client-add-btn').addEventListener('click', () => {
                        window.openThirdPartyPanel({ identificacion: document.getElementById('quote-client-search').value });
                    });

                    window.thirdPartyPanelOnSave = selectClient;
                }

                // --- Envío ---

                function bindSubmit() {
                    document.getElementById('quote-submit-btn').addEventListener('click', async () => {
                        const btn = document.getElementById('quote-submit-btn');
                        document.getElementById('quote-error').classList.add('hidden');

                        const ticket = activeTicket();
                        const cart = ticket.cart;

                        if (cart.length === 0) {
                            showError('{{ __('Add at least one product to the cart.') }}');
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
                            const response = await fetch(storeUrl, {
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
                                throw new Error(data.message || '{{ __('Could not issue the quotation.') }}');
                            }

                            currentQuotation = data;
                            document.getElementById('quote-result-numeral').textContent = data.numeral;
                            document.getElementById('quote-result-preview').src = data.preview_url;

                            tickets = tickets.filter((t) => t.id !== ticket.id);
                            if (tickets.length === 0) {
                                makeTicket();
                            }
                            activeTicketId = tickets[0].id;
                            renderTickets();
                            renderActiveTicket();

                            if (window.HSOverlay) {
                                HSOverlay.autoInit();
                                HSOverlay.open('#quote-result-modal');
                            }
                        } catch (error) {
                            showError(error.message || '{{ __('Could not issue the quotation.') }}');
                        } finally {
                            btn.disabled = false;
                            btn.textContent = '{{ __('Issue quotation') }}';
                        }
                    });
                }

                window.quoteDownload = function () {
                    if (currentQuotation) {
                        window.location.href = currentQuotation.pdf_url;
                    }
                };

                window.quotePrint = function () {
                    if (currentQuotation) {
                        window.open(currentQuotation.preview_url, '_blank');
                    }
                };

                window.quoteGoToList = function () {
                    const hasPendingTickets = tickets.some((t) => t.cart.length > 0);
                    if (hasPendingTickets && ! confirm('{{ __('You have other quotations open with products. If you leave, you will lose them. Continue?') }}')) {
                        return;
                    }
                    window.location.href = '{{ route('quotations.index') }}';
                };

                window.quoteNew = function () {
                    if (window.HSOverlay) {
                        HSOverlay.close('#quote-result-modal');
                    }
                };

                function init() {
                    if (document.getElementById('quote-submit-btn')?.dataset.bound === 'true') {
                        return;
                    }
                    const btn = document.getElementById('quote-submit-btn');
                    if (! btn) {
                        return;
                    }
                    btn.dataset.bound = 'true';

                    const ticket = makeTicket();
                    activeTicketId = ticket.id;
                    renderTickets();
                    renderActiveTicket();

                    document.getElementById('quote-ticket-add-btn').addEventListener('click', addTicket);

                    const bulkPriceTypeDropdown = document.getElementById('quote-bulk-price-type-btn')?.closest('.hs-dropdown');
                    initDropdowns(document);
                    bindDropdownOutsideClose();
                    document.querySelectorAll('.quote-bulk-price-type-option').forEach((option) => {
                        option.addEventListener('click', () => {
                            applyPriceTypeToCart(option.dataset.priceTypeId);
                            if (window.HSDropdown && bulkPriceTypeDropdown) {
                                HSDropdown.close(bulkPriceTypeDropdown);
                            }
                        });
                    });

                    bindProductSearch();
                    bindClientControls();
                    bindSubmit();

                    renderProducts(initialProducts);

                    document.addEventListener('click', (event) => {
                        if (! event.target.closest('#quote-client-search') && ! event.target.closest('#quote-client-results')) {
                            document.getElementById('quote-client-results').classList.add('hidden');
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
