@php
    $nitIdentificationType = '31';
@endphp

<x-layouts.app :title="__('Quotations')">
    @include('partials.tittle', [
        'title' => __('Quotations'),
        'subheading' => __('Quotations made for clients, not yet converted into a sale.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex justify-between items-center gap-4">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Public catalog links') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Share these links with your clients so they can browse the catalog and build their own quotation, no account needed.') }}</p>
                </div>
                <button type="button" id="catalog-link-add-btn"
                    class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-accent dark:text-neutral-400 dark:hover:bg-neutral-700 focus:outline-hidden"
                    aria-label="{{ __('New link') }}" title="{{ __('New link') }}">
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                </button>
            </div>
            <div class="p-4 flex flex-col gap-3">
                @forelse ($catalogLinks as $link)
                    <div class="flex items-center gap-3">
                        {{-- Input group: label + input + botón de copiar
                             comparten un solo borde, como un solo control. El
                             botón usa el helper de clipboard real de Preline
                             (ClipboardJS + preline/dist/helper-clipboard,
                             clase "js-clipboard" + data-clipboard-target),
                             no un click handler propio -- ver app.js. SIN la
                             clase "hs-tooltip"/burbuja emergente: el propio
                             helper de Preline llama internamente a
                             "window.HSTooltip.show()" en el success callback,
                             y esa llamada revienta con "Cannot read
                             properties of undefined (reading 'show')" en
                             navegación por wire:navigate (el tooltip nunca
                             queda inicializado a tiempo) -- el swap de ícono
                             (lápiz -> check verde) ya es suficiente feedback
                             visual, sin depender de esa burbuja. --}}
                        <div class="flex-1 min-w-0 flex items-stretch rounded-lg border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 focus-within:ring-2 focus-within:ring-accent/40">
                            <span class="shrink-0 max-w-[9rem] truncate inline-flex items-center px-3 border-e border-zinc-200 dark:border-white/10 text-xs font-medium text-zinc-500 dark:text-neutral-400" title="{{ $link->label ?: ($link->warehouse?->name ?? __('All warehouses')) }}">
                                {{ $link->label ?: ($link->warehouse?->name ?? __('All warehouses')) }}
                            </span>
                            <input type="text" readonly id="catalog-link-url-{{ $link->_id }}" value="{{ route('public.catalog.show', $link->token) }}"
                                class="flex-1 min-w-0 bg-transparent border-0 text-zinc-700 dark:text-zinc-300 text-sm h-9 px-3 focus:outline-hidden focus:ring-0">
                            <button type="button" class="js-clipboard relative shrink-0 inline-flex items-center justify-center px-3 border-s border-zinc-200 dark:border-white/10 text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                                data-clipboard-target="#catalog-link-url-{{ $link->_id }}"
                                data-clipboard-action="copy"
                                aria-label="{{ __('Copy') }}" title="{{ __('Copy') }}">
                                <svg class="js-clipboard-default size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                <svg class="js-clipboard-success hidden size-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </button>
                        </div>

                        <form action="{{ route('catalog-links.destroy', $link->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="shrink-0 flex size-9 items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('You have no public catalog links yet.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- Modal: crear link -->
        <div id="catalog-link-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="catalog-link-modal-label">
            <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
                <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                    <form action="{{ route('catalog-links.store') }}" method="POST">
                        @csrf
                        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                            <h3 id="catalog-link-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('New link') }}</h3>
                        </div>
                        <div class="p-4 flex flex-col gap-4">
                            @if ($errors->any())
                                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                    {{ $errors->first() }}
                                </div>
                            @endif
                            <flux:input id="catalog-link-label" name="label" :label="__('Label')" placeholder="{{ __('e.g. Main store') }}" value="{{ old('label') }}" />
                            <div id="catalog-link-warehouse-field">
                                <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Warehouse') }}</label>
                                {{-- "none" es un valor real seleccionable (no la
                                     cadena vacía): con "" como valor, Preline lo
                                     trata como el placeholder y no se manda de
                                     forma confiable (mismo motivo que en
                                     pos/sell.blade.php). --}}
                                <select name="warehouse_id" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                                    <option value="none" selected>{{ __('All warehouses') }}</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->_id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ __('If you pick a warehouse, the catalog only shows and sells stock from there.') }}</p>
                            </div>

                            @if ($priceTypes->isNotEmpty())
                                <div id="catalog-link-primary-price-field">
                                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Main price type') }}</label>
                                    <select name="primary_price_type_id" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                                        @foreach ($priceTypes as $priceType)
                                            <option value="{{ $priceType->_id }}" @selected($loop->first)>{{ $priceType->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ __('This is the price shown on each product card.') }}</p>
                                </div>

                                <div id="catalog-link-visible-prices-field">
                                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Other visible price types') }}</label>
                                    <div class="flex flex-col gap-1.5">
                                        @foreach ($priceTypes as $priceType)
                                            <flux:checkbox name="visible_price_type_ids[]" value="{{ $priceType->_id }}" :label="$priceType->name" />
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ __('These show up in the price list when hovering over a product card, along with the main price.') }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="p-4 pt-0 flex justify-end gap-2">
                            <flux:button type="button" variant="filled" onclick="window.HSOverlay && HSOverlay.close('#catalog-link-modal')">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" id="catalog-link-submit-btn" variant="primary">{{ __('Create link') }}</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <a href="{{ route('quotations.create') }}">
                            <flux:button type="button" variant="primary" icon="plus">{{ __('New quotation') }}</flux:button>
                        </a>
                    </div>

                    <div class="overflow-hidden">
                        <table id="quotationsTable" class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Numeral') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Customer') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Total') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($quotations as $quotation)
                                    @php
                                        $customerParty = $quotation->payload['accounting_customer_party'] ?? [];
                                        $customerName = $quotation->cliente?->name ?? ($customerParty['razon_social'] ?? null);
                                        $customerIdentification = $customerParty['identificacion'] ?? null;
                                        $customerDv = $customerParty['tipo_identificacion'] === $nitIdentificationType ? ($customerParty['dv'] ?? null) : null;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $quotation->issue_date?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $quotation->numeral }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            <div class="text-gray-800 dark:text-neutral-200">{{ $customerName ?? '—' }}</div>
                                            <div>{{ $customerIdentification ?? '—' }}{{ $customerDv ? '-' . $customerDv : '' }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $quotation->total_formatted }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $quotation->status_badge_classes }}">{{ $quotation->status_label }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <div class="flex justify-end gap-1">
                                                <a href="{{ route('quotations.preview', $quotation->_id) }}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View PDF') }}" title="{{ __('View PDF') }}">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                                </a>
                                                <a href="{{ route('quotations.show', $quotation->_id) }}" class="quotation-view-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Quotations')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            /**
             * Se registra tanto en "DOMContentLoaded" (carga real de la
             * página) como en "livewire:navigated" (navegación por
             * wire:navigate desde el sidebar, que NO dispara
             * "DOMContentLoaded" de nuevo) -- sin el segundo listener,
             * entrar a Cotizaciones por un link del sidebar dejaba el botón
             * de copiar el link público sin funcionar (nunca se llamaba a
             * window.hsClipboardHelper). El botón de "Nuevo link" usa un
             * guard con dataset.bound para no acumular listeners si esta
             * función se llama más de una vez sobre el mismo botón.
             * @returns {void}
             */
            function initQuotationsPage() {
                const table = initWorkflowDataTable('#quotationsTable', '#hs-table-with-pagination-search');
                table.order([]).draw();

                const addLinkBtn = document.getElementById('catalog-link-add-btn');
                if (addLinkBtn && ! addLinkBtn.dataset.bound) {
                    addLinkBtn.dataset.bound = 'true';
                    addLinkBtn.addEventListener('click', () => {
                        if (window.HSOverlay) {
                            HSOverlay.open('#catalog-link-modal');
                        }
                    });
                }

                @if ($errors->any())
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#catalog-link-modal');
                    }
                @endif

                // Botones "copiar link" (input group junto a cada URL): usan
                // el helper de clipboard real de Preline (ClipboardJS de
                // verdad, no un click handler propio con
                // navigator.clipboard) -- ver app.js, donde queda expuesto
                // como window.hsClipboardHelper. Se llama a mano en vez de
                // depender de su auto-init en window "load" para que
                // también funcione si esta tarjeta se vuelve a pintar.
                window.hsClipboardHelper?.('.js-clipboard');
            }

            document.addEventListener('DOMContentLoaded', initQuotationsPage);
            document.addEventListener('livewire:navigated', initQuotationsPage);
        </script>
    @endpush
</x-layouts.app>
