@php
    $customer = $documento->payload['accounting_customer_party'] ?? [];
    $lineas = $documento->payload['lineas'] ?? [];

    $paymentFormLabels = [
        '1' => __('Cash'),
        '2' => __('Credit'),
    ];
@endphp

<x-layouts.app :title="$documento->numeral">
    @include('partials.tittle', [
        'title' => $documento->numeral,
        'subheading' => $documento->status_label,
    ])

    <div class="mb-6">
        <a href="{{ route('pos.sales.index') }}" class="text-sm font-medium text-accent hover:underline">&larr; {{ __('Back to sales') }}</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div id="sale-show-customer" class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Customer') }}</h3>
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</div>
                        <div class="text-gray-800 dark:text-neutral-200">{{ $customer['razon_social'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</div>
                        <div class="text-gray-800 dark:text-neutral-200">{{ $customer['identificacion'] ?? '—' }}{{ isset($customer['dv']) && $customer['dv'] !== null ? '-' . $customer['dv'] : '' }}</div>
                    </div>
                </div>
            </div>

            <div id="sale-show-lines" class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Lines') }}</h3>
                    @if (! $documento->is_electronic && $isAdmin)
                        <a href="{{ route('pos.create', ['edit_sale' => $documento->_id]) }}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                <path d="m15 5 4 4"></path>
                            </svg>
                        </a>
                    @endif
                </div>
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Unit price') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subtotal') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Warehouse') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($lineas as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $linea['codigo'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200">{{ $linea['descripcion'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $linea['cantidad'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($linea['cantidad'] ?? 0) * (float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $warehouseNames[$linea['bodega_id'] ?? ''] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Lines')]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div id="sale-show-summary" class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Summary') }}</h3>
                </div>
                <div class="p-4 flex flex-col gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Status') }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->status_badge_classes }}">{{ $documento->status_label }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Issue date') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->issue_date?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Subtotal') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ number_format((float) $documento->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Tax') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ number_format((float) $documento->tax_total, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span class="text-gray-800 dark:text-neutral-200">{{ __('Total') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->total_formatted }}</span>
                    </div>
                    <flux:separator variant="subtle" />
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Payment form') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $paymentFormLabels[$documento->payment_means_id ?? ''] ?? $documento->payment_means_id ?? '—' }}</span>
                    </div>
                    @if (count($documento->payments ?? []) > 1)
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-500 dark:text-neutral-500">{{ __('Payment method') }}</span>
                            @foreach ($documento->payments as $payment)
                                <div class="flex justify-between">
                                    <span class="text-gray-800 dark:text-neutral-200">{{ $payment['payment_method_name'] ?? '—' }}</span>
                                    <span class="text-gray-800 dark:text-neutral-200">${{ number_format((float) ($payment['amount'] ?? 0), 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-neutral-500">{{ __('Payment method') }}</span>
                            <span class="text-gray-800 dark:text-neutral-200">{{ $documento->payment_method_name ?? $paymentMeansCode->medio ?? '—' }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div id="sale-show-electronic" class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Electronic invoice') }}</h3>
                </div>
                <div class="p-4 flex flex-col gap-3 text-sm">
                    @if ($documento->documento_emitido_id)
                        <p class="text-gray-600 dark:text-neutral-400">{{ __('This sale was also issued as a real electronic invoice.') }}</p>
                        <a href="{{ route('documents.show', $documento->documento_emitido_id) }}">
                            <flux:button type="button" variant="filled" icon="document-text">{{ __('View electronic invoice') }}</flux:button>
                        </a>
                    @elseif ($canIssueElectronic)
                        <div id="sale-issue-electronic-message" class="hidden rounded-md p-3 text-sm"></div>
                        <p class="text-gray-600 dark:text-neutral-400">{{ __('This sale was not issued as an electronic invoice.') }}</p>
                        <flux:button type="button" id="sale-issue-electronic-btn" variant="primary" icon="bolt" onclick="window.saleIssueElectronic()">
                            {{ __('Issue electronic invoice') }}
                        </flux:button>
                    @else
                        <p class="text-gray-600 dark:text-neutral-400">{{ __('This sale was not issued as an electronic invoice.') }}</p>
                    @endif
                </div>
            </div>

            <a id="sale-show-download-btn" href="{{ route('pos.sales.receipt-pdf', $documento->_id) }}">
                <flux:button type="button" variant="filled" icon="arrow-down-tray" class="w-full">{{ __('Download receipt') }}</flux:button>
            </a>
        </div>
    </div>

    @if ($canIssueElectronic)
        @push('scripts')
            <script>
                /**
                 * Emite electrónica una venta ya guardada (pantalla de
                 * detalle, no el modal justo después de cobrar) -- el modal
                 * de "window.posIssueElectronic()" en pos/sell.blade.php
                 * solo existe mientras esa pestaña sigue con la venta que
                 * se acaba de cobrar en memoria (variable "currentSale");
                 * al volver más tarde desde el listado de ventas no hay
                 * ese estado, así que esta pantalla necesita su propio
                 * botón que pegue directo con la URL del documento.
                 * @returns {void}
                 */
                window.saleIssueElectronic = async function () {
                    const btn = document.getElementById('sale-issue-electronic-btn');
                    const messageBox = document.getElementById('sale-issue-electronic-message');
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                    btn.disabled = true;
                    btn.textContent = '{{ __('Processing...') }}';

                    try {
                        const response = await fetch('{{ route('pos.sales.issue-electronic', $documento->_id) }}', {
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

                        if (data.document_url) {
                            window.location.href = data.document_url;
                            return;
                        }

                        window.location.reload();
                    } catch (error) {
                        /**
                         * No se vuelve a habilitar el botón: todo lo que
                         * puede rechazar esto (módulo desactivado, sin
                         * resolución electrónica, medio de pago sin mapeo
                         * DIAN) necesita que el usuario arregle algo en
                         * otra pantalla primero -- reintentar con el mismo
                         * clic nunca lo resuelve.
                         */
                        messageBox.className = 'rounded-md p-3 text-sm bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        messageBox.textContent = error.message || '{{ __('Could not issue the electronic invoice.') }}';
                        messageBox.classList.remove('hidden');
                        btn.classList.add('hidden');
                    }
                };
            </script>
        @endpush
    @endif
</x-layouts.app>
