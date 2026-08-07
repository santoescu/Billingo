@php
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Invoice (export)'),
        '03' => __('Invoice (contingency, paper)'),
        '04' => __('Invoice (DIAN contingency)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];

    $customer = $documento->payload['accounting_customer_party'] ?? [];
    $lineas = $documento->payload['lineas'] ?? [];

    // Tabla oficial DIAN: "Forma de Pago" (cbc:ID dentro de cac:PaymentMeans)
    // solo tiene estos 2 valores.
    $paymentFormLabels = [
        '1' => __('Cash'),
        '2' => __('Credit'),
    ];
@endphp

<x-layouts.app :title="$documento->numeral">
    @include('partials.tittle', [
        'title' => $documento->numeral,
        'subheading' => $documentTypeLabels[$documento->tipo_documento ?? ''] ?? $documento->tipo_documento,
    ])

    <div class="mb-6">
        <a href="{{ route('documents.index') }}" class="text-sm font-medium text-accent hover:underline">&larr; {{ __('Back to issued documents') }}</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Customer') }}</h3>
                </div>
                <div class="p-4 flex flex-col gap-4 text-sm">
                    @php
                        $personTypeLabels = ['1' => __('Legal entity'), '2' => __('Natural person')];
                        $customerPersonType = $personTypeLabels[$customer['tipo_persona'] ?? ''] ?? null;
                        $customerResponsibilities = is_array($customer['responsabilidades_fiscales'] ?? null)
                            ? implode(', ', $customer['responsabilidades_fiscales'])
                            : ($customer['responsabilidades_fiscales'] ?? null);
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customer['razon_social'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customer['identificacion'] ?? '—' }}{{ isset($customer['dv']) && $customer['dv'] !== null ? '-' . $customer['dv'] : '' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Person type') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customerPersonType ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Phone') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customer['telefono'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Email') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customer['email'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Fiscal responsibilities') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customerResponsibilities ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Department') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customerDepartmentName ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('City') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customerCityName ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Address') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $customer['direccion'] ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Lines') }}</h3>
                </div>
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Unit price') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($lineas as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200">{{ $linea['descripcion'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $linea['cantidad'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Lines')]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($documento->status_message)
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('DIAN message') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-3 text-sm">
                        @if (data_get($documento->status_message, 'resumen'))
                            <div class="text-gray-800 dark:text-neutral-200">{{ data_get($documento->status_message, 'resumen') }}</div>
                        @endif

                        @if (filled(data_get($documento->status_message, 'reglas')))
                            <ul class="list-disc list-inside text-xs text-gray-600 dark:text-neutral-400 space-y-1">
                                @foreach (data_get($documento->status_message, 'reglas') as $regla)
                                    <li>{{ $regla }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
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
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Expedition date') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->fecha_expedicion?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Due date') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->due_date?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</span>
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
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Payment method') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $paymentMeansCode->medio ?? $documento->payment_means_code ?? '—' }}</span>
                    </div>
                    <flux:separator variant="subtle" />
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('UUID') }}</div>
                        <div class="text-xs text-gray-800 break-all dark:text-neutral-200">{{ $documento->uuid ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Downloads') }}</h3>
                </div>
                <div class="p-4 flex flex-row flex-wrap gap-3">
                    <a
                        href="data:application/xml;charset=utf-8,{{ rawurlencode($documento->xml ?? '') }}"
                        download="{{ $documento->numeral }}.xml"
                    >
                        <flux:button type="button" variant="filled" icon="arrow-down-tray">
                            {{ __('XML') }}
                        </flux:button>
                    </a>

                    @if ($documento->response)
                        <a
                            href="data:application/xml;charset=utf-8,{{ rawurlencode($documento->response) }}"
                            download="{{ $documento->numeral }}-dian.xml"
                        >
                            <flux:button type="button" variant="filled" icon="arrow-down-tray">
                                {{ __('XML RESPONSE') }}
                            </flux:button>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
