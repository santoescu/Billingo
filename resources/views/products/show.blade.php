@php
    $measurementUnitsByCode = $measurementUnits->keyBy('codigo');
    $unitLabel = $measurementUnitsByCode->get($product->unit_code)?->descripcion ?? $product->unit_code;

    $priceTypesById = $priceTypes->keyBy(fn ($priceType) => (string) $priceType->_id);
    $priceRows = collect($product->extra_prices ?? [])
        ->map(function ($entry) use ($priceTypesById) {
            $priceType = $priceTypesById->get($entry['price_type_id'] ?? null);

            return $priceType ? ['name' => $priceType->name, 'price' => (float) ($entry['price'] ?? 0)] : null;
        })
        ->filter()
        ->values();

    $warehousesById = $warehouses->keyBy(fn ($warehouse) => (string) $warehouse->_id);
    $warehouseStockRows = collect($product->warehouse_stocks ?? [])
        ->map(function ($entry) use ($warehousesById) {
            $warehouse = $warehousesById->get($entry['warehouse_id'] ?? null);

            return $warehouse ? ['name' => $warehouse->name, 'stock' => (float) ($entry['stock'] ?? 0)] : null;
        })
        ->filter()
        ->values();
@endphp

<x-layouts.app :title="$product->description">
    @include('partials.tittle', [
        'title' => $product->description,
        'subheading' => $product->code,
    ])

    <div class="mb-6">
        <a href="{{ route('products.index') }}" class="text-sm font-medium text-accent hover:underline">&larr; {{ __('Back to products') }}</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('General') }}</h3>
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</div>
                        <div class="text-gray-800 dark:text-neutral-200">{{ $product->code ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Barcode') }}</div>
                        <div class="text-gray-800 dark:text-neutral-200">{{ $product->barcode ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Unit') }}</div>
                        <div class="text-gray-800 dark:text-neutral-200">{{ $unitLabel ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Prices') }}</h3>
                </div>
                <div class="p-4">
                    @if ($priceRows->isEmpty())
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This product has no price types assigned yet.') }}</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($priceRows as $row)
                                    <tr>
                                        <td class="py-2 text-sm text-gray-800 dark:text-neutral-200">{{ $row['name'] }}</td>
                                        <td class="py-2 text-sm text-end text-gray-600 dark:text-neutral-400">${{ number_format($row['price'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Kardex') }}</h3>
                </div>
                <div id="product-kardex-body" class="p-4 max-h-[32rem] overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Loading...') }}</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 flex justify-center">
                <button type="button" onclick="document.getElementById('pd-image-input').click()" class="relative block w-full aspect-square max-w-72 rounded-lg overflow-hidden focus:outline-hidden focus:ring-2 focus:ring-accent" title="{{ __('Image') }}">
                    <img id="pd-image-preview" src="{{ $product->image_url }}" alt="" class="size-full object-cover {{ $product->image_url ? '' : 'hidden' }}">
                    <span id="pd-image-placeholder" class="flex items-center justify-center size-full bg-accent/10 text-accent {{ $product->image_url ? 'hidden' : '' }}">
                        <svg class="shrink-0 size-16" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    </span>
                </button>
                <input type="file" id="pd-image-input" accept="image/*" class="hidden">
            </div>

            @if ($product->tracks_inventory)
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Stock') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-4 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Total stock') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ rtrim(rtrim(number_format((float) $product->stock, 2, '.', ','), '0'), '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Unit cost') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $product->average_cost_formatted }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Warehouse') }}</div>
                            @if ($warehouseStockRows->isEmpty())
                                <div class="text-gray-800 dark:text-neutral-200">—</div>
                            @else
                                <ul class="text-gray-800 dark:text-neutral-200">
                                    @foreach ($warehouseStockRows as $row)
                                        <li class="flex justify-between gap-2">
                                            <span>{{ $row['name'] }}</span>
                                            <span>{{ rtrim(rtrim(number_format($row['stock'], 2, '.', ','), '0'), '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Unassigned') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ rtrim(rtrim(number_format($product->unassigned_stock, 2, '.', ','), '0'), '.') }}</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700 p-4 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('This product does not track inventory.') }}
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                function escapeHtml(value) {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
                }

                function formatMoneyCop(value) {
                    return '$' + Number(value ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderKardexTimeline(movements) {
                    if (! movements || movements.length === 0) {
                        return `<p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This product has no movements yet.') }}</p>`;
                    }

                    const groups = [];
                    movements.forEach((movement) => {
                        const last = groups[groups.length - 1];
                        if (last && last.date_label === movement.date_label) {
                            last.items.push(movement);
                        } else {
                            groups.push({ date_label: movement.date_label, items: [movement] });
                        }
                    });

                    const visibleGroups = groups.slice(0, 5);
                    const olderGroups = groups.slice(5);

                    /**
                     * Pinta una fila del timeline del kardex. rowClasses usa
                     * p-2 -m-2 (se cancelan visualmente, mismo espacio que
                     * antes) para darle aire al fondo resaltado del hover sin
                     * que quede pegado al texto -- mismo patrón de fila
                     * clickeable que notifications-bell.blade.php.
                     * @param {object} movement
                     * @returns {string} HTML de la fila.
                     */
                    const renderItem = (movement) => {
                        const isIn = movement.type === 'in';
                        const sign = isIn ? '+' : '-';
                        const dotClasses = isIn ? 'bg-accent' : 'bg-gray-300 dark:bg-neutral-600';
                        const title = `${isIn ? '{{ __('Entry') }}' : '{{ __('Exit') }}'} — ${sign}${movement.quantity} ({{ __('at') }} ${formatMoneyCop(movement.unit_cost)})`;
                        const descriptionParts = [movement.reason_label, movement.warehouse_name, `{{ __('Balance') }}: ${movement.balance_after}`].filter(Boolean);

                        const rowClasses = 'relative p-2 -m-2 rounded-lg'
                            + (movement.url ? ' group hover:bg-gray-100 dark:hover:bg-neutral-700' : '');

                        return `
                            <div class="flex gap-x-3 ${rowClasses}">
                                ${movement.url ? `<a class="z-1 absolute inset-0" href="${movement.url}"></a>` : ''}
                                <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:-translate-x-[0.5px] after:border-s after:border-gray-200 dark:after:border-neutral-700">
                                    <div class="relative z-10 size-7 flex justify-center items-center">
                                        <div class="size-2 rounded-full ${dotClasses}"></div>
                                    </div>
                                </div>
                                <div class="grow pt-0.5 pb-8">
                                    <h3 class="font-medium text-gray-800 dark:text-neutral-200">${escapeHtml(title)}</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400">${escapeHtml(descriptionParts.join(' · '))}</p>
                                    ${movement.user_name ? `<p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">${escapeHtml(movement.user_name)}</p>` : ''}
                                </div>
                            </div>
                        `;
                    };

                    const renderGroup = (group) => `
                        <div class="ps-2 my-2 first:mt-0">
                            <h3 class="text-xs font-medium uppercase text-gray-500 dark:text-neutral-500">${escapeHtml(group.date_label)}</h3>
                        </div>
                        ${group.items.map(renderItem).join('')}
                    `;

                    let html = `<div class="w-full">${visibleGroups.map(renderGroup).join('')}`;

                    if (olderGroups.length > 0) {
                        html += `
                            <div id="kardex-older" class="hidden">${olderGroups.map(renderGroup).join('')}</div>
                            <div class="ps-2 -ms-px flex gap-x-3">
                                <button type="button" class="text-start inline-flex items-center gap-x-1 text-sm text-accent font-medium hover:underline" onclick="document.getElementById('kardex-older').classList.remove('hidden'); this.remove();">
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    {{ __('Show older') }}
                                </button>
                            </div>
                        `;
                    }

                    html += `</div>`;

                    return html;
                }

                async function loadKardex() {
                    const body = document.getElementById('product-kardex-body');

                    try {
                        const response = await fetch(@json(route('products.kardex', ['product' => $product->_id])), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await response.json();
                        body.innerHTML = renderKardexTimeline(data.movements);
                    } catch (error) {
                        body.innerHTML = `<p class="text-sm text-red-600 dark:text-red-400">{{ __('Could not load the movement history.') }}</p>`;
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', loadKardex);
                } else {
                    loadKardex();
                }

                document.getElementById('pd-image-input').addEventListener('change', async (event) => {
                    const file = event.target.files?.[0];
                    if (! file) {
                        return;
                    }

                    const body = new FormData();
                    body.append('image', file);
                    body.append('_token', @json(csrf_token()));

                    const response = await fetch(@json(route('products.image.update', ['product' => $product->_id])), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body,
                    });

                    if (! response.ok) {
                        return;
                    }

                    const data = await response.json();
                    document.getElementById('pd-image-preview').src = data.image_url;
                    document.getElementById('pd-image-preview').classList.remove('hidden');
                    document.getElementById('pd-image-placeholder').classList.add('hidden');
                });
            })();
        </script>
    @endpush
</x-layouts.app>
