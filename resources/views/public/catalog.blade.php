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

    $initialProductsJs = $products;
@endphp

<x-layouts.public :title="$company->name . ' — ' . __('Catalog')">
    <div class="mb-6 flex items-center gap-3">
        @if ($company->logo_url)
            <img src="{{ $company->logo_url }}" alt="" class="size-12 rounded-lg object-cover shrink-0">
        @endif
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white">{{ $company->name }}</h1>
            <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('Browse the catalog and build your own quotation.') }}</p>
        </div>
    </div>

    <div id="catalog-error" class="hidden mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>

    <!-- El catálogo y el carrito se ven de una, sin pedir nada primero
         (como cualquier tienda): la identificación solo se pide al final,
         al hacer clic en "Enviar cotización" (ver #catalog-client-modal más
         abajo), no antes de poder ni mirar los productos. -->
    <div id="catalog-shop">
        <div id="catalog-client-summary" class="hidden mb-4 flex items-center justify-between gap-3 max-w-md">
            <div class="text-sm">
                <span class="text-zinc-500 dark:text-neutral-400">{{ __('Client') }}:</span>
                <span id="catalog-client-name-display" class="font-medium text-gray-800 dark:text-white"></span>
            </div>
            <button type="button" id="catalog-client-change-btn" class="text-xs font-medium text-accent hover:underline focus:outline-hidden">{{ __('Change client') }}</button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-6 items-start">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="p-4 border-b border-gray-200 dark:border-neutral-700">
                    <div class="relative">
                        <input type="text" id="catalog-product-search" autocomplete="off"
                            placeholder="{{ __('Code, barcode or description') }}"
                            class="w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base shadow-xs h-10 py-2 px-3 ps-10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>
                    </div>
                </div>
                <div id="catalog-product-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[75vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                <p id="catalog-product-grid-empty" class="hidden p-4 text-sm text-zinc-500 dark:text-neutral-400">{{ __('No products found.') }}</p>
            </div>

            <div class="flex flex-col gap-4">
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-2.5 py-2.5 border-b border-gray-200 dark:border-neutral-700">
                        <div class="grid" style="grid-template-columns: 1fr 76px 108px 40px;">
                            <span class="px-2.5 text-xs font-medium text-zinc-500 dark:text-neutral-400 self-center">{{ __('Description') }}</span>
                            <span class="text-xs font-medium text-zinc-500 dark:text-neutral-400 text-center self-center">{{ __('Quantity') }}</span>
                            <span class="text-xs font-medium text-zinc-500 dark:text-neutral-400 text-end px-2.5 self-center">{{ __('Subtotal') }}</span>
                            <span></span>
                        </div>
                    </div>
                    <div id="catalog-cart-body" class="divide-y divide-gray-200 dark:divide-neutral-700 max-h-[50vh] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"></div>
                    <p id="catalog-cart-empty" class="p-4 text-sm text-zinc-500 dark:text-neutral-400">
                        <svg class="mx-auto mb-2 size-8 text-zinc-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        <span class="block text-center">{{ __("You don't have any products in the cart yet.") }}</span>
                    </p>
                </div>

                <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 flex flex-col gap-3">
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('Total') }}</span>
                        <span id="catalog-total-display" class="text-xl font-bold text-gray-800 dark:text-neutral-200">$0.00</span>
                    </div>
                    <flux:button type="button" variant="primary" id="catalog-submit-btn" class="w-full">{{ __('Send quotation') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: identificación del cliente, solo se abre al enviar la
         cotización (no antes de poder ver/agregar productos). -->
    <div id="catalog-client-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="catalog-client-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="catalog-client-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Enter your identification to continue') }}</h3>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <div id="catalog-client-modal-error" class="hidden rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"></div>
                    <div class="flex gap-2">
                        <input type="text" id="catalog-client-identificacion" autocomplete="off" inputmode="numeric"
                            class="flex-1 bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs h-10 py-2 px-3 focus:outline-hidden focus:ring-2 focus:ring-accent"
                            placeholder="{{ __('Identification') }}">
                        <flux:button type="button" variant="primary" id="catalog-client-search-btn">{{ __('Continue') }}</flux:button>
                    </div>

                    <div id="catalog-client-new-form" class="hidden flex flex-col gap-3">
                        <p class="text-sm text-zinc-600 dark:text-neutral-400">{{ __('No client found with that name. Fill in the fields below to create a new one.') }}</p>
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Identification type') }}</label>
                            <select id="catalog-client-new-type" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                                @foreach ($identificationTypes as $code => $label)
                                    <option value="{{ $code }}" @selected($code === '13')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <flux:input id="catalog-client-new-name" :label="__('Name')" />
                        <flux:input id="catalog-client-new-phone" :label="__('Phone')" />
                        <flux:input id="catalog-client-new-email" type="email" :label="__('Email')" />
                        <flux:button type="button" variant="primary" id="catalog-client-create-btn">{{ __('Create client') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: resultado -->
    <div id="catalog-result-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="catalog-result-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 id="catalog-result-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Quotation sent') }}</h3>
                    <span id="catalog-result-numeral" class="text-sm text-zinc-500 dark:text-zinc-400"></span>
                </div>
                <div class="p-4">
                    <p class="text-sm text-zinc-600 dark:text-neutral-400 mb-3">{{ __('We received your quotation. You can download it below.') }}</p>
                    <iframe id="catalog-result-preview" class="w-full rounded-lg border border-gray-200 dark:border-neutral-700" style="height: 50vh;" title="{{ __('Quotation preview') }}"></iframe>
                </div>
                <div class="p-4 pt-0 grid grid-cols-1 gap-2">
                    <flux:button type="button" variant="filled" icon="arrow-down-tray" onclick="window.catalogDownload()">{{ __('Download') }}</flux:button>
                    <flux:button type="button" variant="primary" onclick="window.catalogNew()">{{ __('New quotation') }}</flux:button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const productSearchUrl = @json(route('public.catalog.products', $token));
                const clientShowUrl = @json(route('public.catalog.client.show', $token));
                const clientStoreUrl = @json(route('public.catalog.client.store', $token));
                const storeUrl = @json(route('public.catalog.quotations.store', $token));
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                const initialProducts = @json($initialProductsJs);

                let client = null;
                let cart = [];
                let currentQuotation = null;

                function formatMoney(value) {
                    return '$' + (value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function showError(message) {
                    const box = document.getElementById('catalog-error');
                    box.textContent = message;
                    box.classList.remove('hidden');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // Mismo tooltip flotante de inventario/precio que usa el
                // POS (pos/sell.blade.php), calcado tal cual.
                function ensureFloatingTooltip() {
                    let el = document.getElementById('catalog-floating-tooltip');
                    if (! el) {
                        el = document.createElement('div');
                        el.id = 'catalog-floating-tooltip';
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
                    document.getElementById('catalog-floating-tooltip')?.classList.add('hidden');
                }

                function tooltipBadge(label, lines, badgeClasses) {
                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-white/10 px-2 py-0.5 cursor-default ${badgeClasses}`;
                    badge.textContent = label;
                    badge.addEventListener('mouseenter', () => showFloatingTooltip(badge, lines));
                    badge.addEventListener('mouseleave', hideFloatingTooltip);
                    return badge;
                }

                // --- Cliente ---
                // El modal de identificación solo se abre al hacer clic en
                // "Enviar cotización" (ver bindSubmit()), nunca antes -- el
                // cliente final puede mirar el catálogo y armar el carrito
                // libremente sin que nada se lo pida primero.

                function showClientModalError(message) {
                    const box = document.getElementById('catalog-client-modal-error');
                    box.textContent = message;
                    box.classList.remove('hidden');
                }

                function setClient(resolvedClient) {
                    client = resolvedClient;
                    document.getElementById('catalog-client-summary').classList.remove('hidden');
                    document.getElementById('catalog-client-name-display').textContent = client.name;
                    if (window.HSOverlay) {
                        HSOverlay.close('#catalog-client-modal');
                    }
                }

                function bindClientStep() {
                    document.getElementById('catalog-client-search-btn').addEventListener('click', async () => {
                        const identificacion = document.getElementById('catalog-client-identificacion').value.trim();
                        if (! identificacion) {
                            return;
                        }

                        const response = await fetch(clientShowUrl + '?identificacion=' + encodeURIComponent(identificacion));
                        const data = await response.json();

                        if (data.client) {
                            setClient(data.client);
                            submitQuotation();
                        } else {
                            document.getElementById('catalog-client-new-form').classList.remove('hidden');
                        }
                    });

                    document.getElementById('catalog-client-create-btn').addEventListener('click', async () => {
                        const identificacion = document.getElementById('catalog-client-identificacion').value.trim();
                        const name = document.getElementById('catalog-client-new-name').value.trim();
                        if (! identificacion || ! name) {
                            showClientModalError('{{ __('Complete the required fields.') }}');
                            return;
                        }

                        const body = new URLSearchParams();
                        body.append('identification_type', document.getElementById('catalog-client-new-type').value || '13');
                        body.append('identificacion', identificacion);
                        body.append('name', name);
                        body.append('phone', document.getElementById('catalog-client-new-phone').value.trim());
                        body.append('email', document.getElementById('catalog-client-new-email').value.trim());

                        try {
                            const response = await fetch(clientStoreUrl, {
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
                                throw new Error(data.message || '{{ __('Could not create the client.') }}');
                            }

                            setClient(data.client);
                            submitQuotation();
                        } catch (error) {
                            showClientModalError(error.message || '{{ __('Could not create the client.') }}');
                        }
                    });

                    document.getElementById('catalog-client-change-btn').addEventListener('click', () => {
                        client = null;
                        document.getElementById('catalog-client-summary').classList.add('hidden');
                    });
                }

                function openClientModal() {
                    document.getElementById('catalog-client-modal-error').classList.add('hidden');
                    document.getElementById('catalog-client-new-form').classList.add('hidden');
                    document.getElementById('catalog-client-identificacion').value = '';
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#catalog-client-modal');
                    }
                }

                // --- Grid de productos ---

                function renderProducts(products) {
                    const grid = document.getElementById('catalog-product-grid');
                    const empty = document.getElementById('catalog-product-grid-empty');
                    grid.innerHTML = '';
                    empty.classList.toggle('hidden', products.length > 0);

                    products.forEach((product) => {
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
                        card.addEventListener('click', () => addToCart(product));

                        const badgesRow = card.querySelector('[data-badges]');
                        // Sin tooltip de desglose por bodega acá -- a
                        // diferencia del POS/Cotizaciones (uso interno del
                        // staff), un cliente final no debe poder deducir
                        // cuántas bodegas tiene la empresa ni sus nombres,
                        // solo la cantidad total disponible para él.
                        const inventoryBadge = document.createElement('span');
                        inventoryBadge.className = 'inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-white/10 px-2 py-0.5 text-xs text-zinc-600 dark:text-zinc-300';
                        inventoryBadge.textContent = `{{ __('Inventory') }}: ${(product.stock ?? 0).toLocaleString('es-CO')}`;
                        badgesRow.appendChild(inventoryBadge);
                        const displayPrice = (product.prices && product.prices.length > 0) ? product.prices[0].price : product.unit_price;
                        badgesRow.appendChild(tooltipBadge(formatMoney(displayPrice), priceLines, 'text-sm font-semibold text-gray-800 dark:text-neutral-200'));

                        grid.appendChild(card);
                    });
                }

                async function searchProducts(query) {
                    const params = new URLSearchParams({ q: query });
                    const response = await fetch(productSearchUrl + '?' + params.toString());
                    const data = await response.json();
                    renderProducts(data.products || []);
                }

                function debounce(fn, wait) {
                    let timeout;
                    return (...args) => {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => fn(...args), wait);
                    };
                }

                // --- Carrito ---
                // Sin selector de bodega ni de tipo de precio por línea: el
                // link ya define de dónde sale el stock (o "todas juntas"),
                // y el cliente final ve un solo precio, no las listas
                // internas de la empresa.

                function addToCart(product) {
                    const existing = cart.find((line) => line.code === product.code);
                    if (existing) {
                        existing.qty += 1;
                    } else {
                        const defaultPrice = (product.prices && product.prices.length > 0) ? product.prices[0].price : product.unit_price;
                        cart.push({
                            code: product.code,
                            barcode: product.barcode,
                            description: product.description,
                            unit_code: product.unit_code || 'EA',
                            unit_price: defaultPrice,
                            qty: 1,
                        });
                    }
                    renderCart();
                }

                function renderCart() {
                    const body = document.getElementById('catalog-cart-body');
                    const empty = document.getElementById('catalog-cart-empty');
                    body.innerHTML = '';
                    empty.classList.toggle('hidden', cart.length > 0);

                    cart.forEach((line, index) => {
                        const row = document.createElement('div');
                        row.className = 'p-2.5';
                        row.innerHTML = `
                            <div class="grid flex-1 min-w-0" style="grid-template-columns: 1fr 76px 108px 40px;">
                                <div class="h-9 flex items-center px-2.5 border border-e-0 border-zinc-200 dark:border-white/10 rounded-s-lg bg-white dark:bg-white/10 text-sm text-gray-800 dark:text-white truncate" title="${escapeHtml(line.description)}">
                                    ${escapeHtml(line.description)}
                                </div>
                                <div class="h-9 flex items-center justify-center border border-e-0 border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10">
                                    <input type="number" min="1" value="${line.qty}" class="catalog-line-qty w-full h-full p-0 bg-transparent border-0 text-sm text-center text-zinc-700 dark:text-zinc-300 focus:ring-0 focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
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
                        row.querySelector('.catalog-line-qty').addEventListener('input', (event) => {
                            const qty = parseFloat(event.target.value) || 0;
                            if (qty <= 0) {
                                cart.splice(index, 1);
                            } else {
                                line.qty = qty;
                            }
                            renderCart();
                        });
                        row.querySelector('[data-action="remove"]').addEventListener('click', () => { cart.splice(index, 1); renderCart(); });
                        body.appendChild(row);
                    });

                    updateTotal();
                }

                function cartTotal() {
                    return cart.reduce((sum, line) => sum + (line.unit_price * line.qty), 0);
                }

                function updateTotal() {
                    document.getElementById('catalog-total-display').textContent = formatMoney(cartTotal());
                }

                // --- Envío ---

                /**
                 * Manda la cotización de verdad -- se llama después de
                 * resolver el cliente, ya sea directo (si ya estaba
                 * identificado) o justo después de cerrarse el modal de
                 * identificación (ver bindClientStep()).
                 * @returns {void}
                 */
                async function submitQuotation() {
                    const btn = document.getElementById('catalog-submit-btn');
                    document.getElementById('catalog-error').classList.add('hidden');

                    btn.disabled = true;
                    btn.textContent = '{{ __('Processing...') }}';

                    const body = new URLSearchParams();
                    body.append('cliente_tipo_identificacion', client.identification_type || '13');
                    body.append('cliente_identificacion', client.identificacion || '');
                    body.append('cliente_nombre', client.name || '');
                    body.append('cliente_tipo_persona', client.person_type || '2');
                    body.append('cliente_telefono', client.phone || '');
                    body.append('cliente_email', client.email || '');
                    cart.forEach((line, index) => {
                        body.append(`items[${index}][codigo]`, line.code);
                        body.append(`items[${index}][codigo_barras]`, line.barcode || '');
                        body.append(`items[${index}][descripcion]`, line.description);
                        body.append(`items[${index}][unidad_medida]`, line.unit_code || 'EA');
                        body.append(`items[${index}][cantidad]`, line.qty);
                        body.append(`items[${index}][precio_unitario]`, line.unit_price);
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
                        document.getElementById('catalog-result-numeral').textContent = data.numeral;
                        document.getElementById('catalog-result-preview').src = data.pdf_url;

                        cart = [];
                        renderCart();

                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#catalog-result-modal');
                        }
                    } catch (error) {
                        showError(error.message || '{{ __('Could not issue the quotation.') }}');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = '{{ __('Send quotation') }}';
                    }
                }

                /**
                 * Click en "Enviar cotización": si ya hay cliente
                 * identificado, manda de una vez; si no, abre el modal de
                 * identificación primero (submitQuotation() se llama solo
                 * después de resolverlo ahí).
                 * @returns {void}
                 */
                function bindSubmit() {
                    document.getElementById('catalog-submit-btn').addEventListener('click', () => {
                        document.getElementById('catalog-error').classList.add('hidden');

                        if (cart.length === 0) {
                            showError('{{ __('Add at least one product to the cart.') }}');
                            return;
                        }

                        if (! client) {
                            openClientModal();
                            return;
                        }

                        submitQuotation();
                    });
                }

                window.catalogDownload = function () {
                    if (currentQuotation) {
                        window.location.href = currentQuotation.pdf_url;
                    }
                };

                window.catalogNew = function () {
                    if (window.HSOverlay) {
                        HSOverlay.close('#catalog-result-modal');
                    }
                };

                function init() {
                    bindClientStep();

                    document.getElementById('catalog-product-search').addEventListener('input', debounce((event) => {
                        searchProducts(event.target.value.trim());
                    }, 300));

                    bindSubmit();

                    renderProducts(initialProducts);
                    renderCart();
                }

                document.addEventListener('DOMContentLoaded', init);
            })();
        </script>
    @endpush
</x-layouts.public>
