@php
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Electronic sales invoice (export)'),
        '03' => __('Electronic transmission instrument (type 03)'),
        '04' => __('Electronic sales invoice (type 04)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];

    $emisor = $documento->payload['accounting_supplier_party'] ?? [];
    $lineas = $documento->payload['lineas'] ?? [];
    $notas = $documento->payload['notas'] ?? [];
    $cargosDescuentos = $documento->payload['cargos_descuentos'] ?? [];
    $ordenReferencia = $documento->payload['orden_referencia'] ?? null;
    $facturaReferencia = $documento->payload['referencias'] ?? null;
    $paymentMeansList = $documento->payload['payment_means_list'] ?? [];

    $paymentFormLabels = [
        '1' => __('Cash'),
        '2' => __('Credit'),
    ];

    $paymentMeansCodeCatalog = \App\Models\PaymentMeansCode::all()->keyBy('codigo');
@endphp

<x-layouts.app :title="$documento->numeral">
    @include('partials.tittle', [
        'title' => $documento->numeral,
        'subheading' => $documentTypeLabels[$documento->tipo_documento ?? ''] ?? $documento->tipo_documento,
    ])

    <div class="mb-6">
        <a href="{{ route('received-documents.index') }}" class="text-sm font-medium text-accent hover:underline">&larr; {{ __('Back to received documents') }}</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Provider') }}</h3>
                </div>
                <div class="p-4 flex flex-col gap-4 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $documento->proveedor->name ?? ($emisor['razon_social'] ?? '—') }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $emisor['identificacion'] ?? '—' }}{{ ! empty($emisor['dv']) ? '-' . $emisor['dv'] : '' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Phone') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $emisor['telefono'] ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Email') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $emisor['email'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Department') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $emisor['departamento'] ?? '—' }}{{ ! empty($emisor['ciudad']) ? ' · ' . $emisor['ciudad'] : '' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Address') }}</div>
                            <div class="text-gray-800 dark:text-neutral-200">{{ $emisor['direccion'] ?? '—' }}</div>
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
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Unit price') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @forelse ($lineas as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $linea['codigo'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200">{{ $linea['descripcion'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $linea['cantidad'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($linea['subtotal'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Lines')]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (filled($cargosDescuentos))
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Charges and discounts') }}</h3>
                    </div>
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Type') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Reason') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Percentage') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($cargosDescuentos as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ ($item['tipo'] ?? '') === 'cargo' ? __('Charge') : __('Discount') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-neutral-200">{{ $item['motivo'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ ($item['porcentaje'] ?? 0) > 0 ? number_format((float) $item['porcentaje'], 2) . '%' : '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($ordenReferencia || $facturaReferencia)
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('References') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-4 text-sm">
                        @if ($ordenReferencia)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Purchase order') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ $ordenReferencia['id'] ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Order date') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ $ordenReferencia['issue_date'] ?? '—' }}</div>
                                </div>
                            </div>
                        @endif

                        @if ($facturaReferencia)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Referenced invoice') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ $facturaReferencia['factura_id'] ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Referenced invoice date') }}</div>
                                    <div class="text-gray-800 dark:text-neutral-200">{{ $facturaReferencia['factura_fecha'] ?? '—' }}</div>
                                </div>
                                @if (! empty($facturaReferencia['periodo_desde']) || ! empty($facturaReferencia['periodo_hasta']))
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Period') }}</div>
                                        <div class="text-gray-800 dark:text-neutral-200">{{ $facturaReferencia['periodo_desde'] ?? '—' }} &rarr; {{ $facturaReferencia['periodo_hasta'] ?? '—' }}</div>
                                    </div>
                                @endif
                                @if (! empty($facturaReferencia['concepto_descripcion']))
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('Correction concept') }}</div>
                                        <div class="text-gray-800 dark:text-neutral-200">{{ $facturaReferencia['concepto_codigo'] ?? '' }} — {{ $facturaReferencia['concepto_descripcion'] }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if (filled($paymentMeansList))
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Payment means') }}</h3>
                    </div>
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Payment form') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Payment method') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Due date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($paymentMeansList as $pago)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $paymentFormLabels[$pago['id'] ?? ''] ?? $pago['id'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $paymentMeansCodeCatalog[$pago['codigo'] ?? '']->medio ?? $pago['codigo'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $pago['fecha_vencimiento'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (filled($notas))
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Notes') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-2 text-sm">
                        @foreach ($notas as $nota)
                            <div class="text-gray-800 dark:text-neutral-200">{{ $nota }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Status') }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->status_badge_classes }}">{{ $documento->status_label }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Prefix') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->prefix ?: '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Sequential') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ $documento->secuencial ?: '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Issue date') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ optional($documento->issue_date)->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-500">{{ __('Due date') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ optional($documento->due_date)->format('Y-m-d') ?? '—' }}</span>
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
                    @if ($documento->is_credit)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-neutral-500">{{ __('Payment status') }}</span>
                            <div class="flex items-center gap-2">
                                <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->payment_status_badge_classes }}">{{ $documento->payment_status_label }}</span>
                                <form method="POST" action="{{ route('received-documents.toggle-paid', $documento->_id) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-accent hover:underline">
                                        {{ $documento->is_paid ? __('Mark as pending') : __('Mark as paid') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                    <flux:separator variant="subtle" />
                    <div>
                        <div class="text-xs text-gray-500 uppercase dark:text-neutral-500">{{ __('UUID') }}</div>
                        <div class="text-xs text-gray-800 break-all dark:text-neutral-200">{{ $documento->uuid ?: '—' }}</div>
                    </div>
                </div>
            </div>

            @include('partials.radian-events', ['radianEventsUrl' => route('received-documents.radian-events', $documento->_id)])

            @if ($documento->xml)
                <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Downloads') }}</h3>
                    </div>
                    <div class="p-4 flex flex-row flex-wrap gap-3">
                        <a href="{{ route('received-documents.pdf', $documento->_id) }}" target="_blank">
                            <flux:button type="button" variant="filled" icon="document-text">
                                {{ __('View PDF') }}
                            </flux:button>
                        </a>
                        @unless ($documento->pdf)
                            <span class="self-center text-xs text-gray-400 dark:text-neutral-500">({{ __('generated, the provider did not send one') }})</span>
                        @endunless

                        <a
                            href="data:application/xml;charset=utf-8,{{ rawurlencode($documento->xml) }}"
                            download="{{ $documento->numeral }}.xml"
                        >
                            <flux:button type="button" variant="filled" icon="arrow-down-tray">
                                {{ __('XML') }}
                            </flux:button>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
