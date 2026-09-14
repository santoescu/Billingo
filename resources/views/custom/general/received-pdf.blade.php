<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .end { text-align: right; }
        .muted { color: #6b7280; }

        .doc-title { font-size: 18px; font-weight: bold; margin: 0; color: #1f2933; }
        .doc-numeral { font-size: 12px; margin: 3px 0 0; color: #4b5563; }
        .doc-uuid { font-size: 8px; word-break: break-all; color: #9ca3af; margin: 8px 0 0; }

        .divider { border-top: 1px solid #d1d5db; margin: 14px 0; }

        .info-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; margin-top: 12px; }
        .info-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #9ca3af; margin: 0 0 4px; }
        .info-value { font-size: 12px; margin: 0; }

        .items { border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; margin-top: 14px; }
        .items-head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #4b5563; padding: 6px 10px; }
        .items-row td { padding: 7px 10px; font-size: 10.5px; border-top: 1px solid #e5e7eb; }

        .totals { width: 45%; margin-left: 55%; margin-top: 18px; }
        .totals td { padding: 4px 10px; font-size: 12px; }
        .totals .total-row td { border-top: 1.5px solid #111827; padding-top: 8px; font-size: 15px; font-weight: bold; }

        .footer-note { margin-top: 24px; padding: 10px 14px; background-color: #f9fafb; border-radius: 4px; font-size: 9.5px; color: #6b7280; }
    </style>
</head>
<body>
    <p class="doc-title">{{ __('Received document') }}</p>
    <p class="doc-numeral">{{ $documento->numeral }}</p>
    <p class="doc-numeral">{{ optional($documento->issue_date)->format('Y-m-d') ?? '—' }}</p>
    @if ($documento->uuid)
        <p class="doc-uuid">{{ $documento->uuid }}</p>
    @endif

    <div class="divider"></div>

    @php $emisor = $documento->payload['accounting_supplier_party'] ?? []; @endphp
    <div class="info-box">
        <p class="info-label">{{ __('Provider') }}</p>
        <p class="info-value">{{ $documento->proveedor->name ?? ($emisor['razon_social'] ?? '—') }}</p>
        <p class="info-value muted">{{ __('Identification') }}: {{ $emisor['identificacion'] ?? '—' }}{{ ! empty($emisor['dv']) ? '-' . $emisor['dv'] : '' }}</p>
        @if (! empty($emisor['direccion']))
            <p class="info-value muted">{{ $emisor['direccion'] }}</p>
        @endif
        @if (! empty($emisor['telefono']) || ! empty($emisor['email']))
            <p class="info-value muted">{{ $emisor['telefono'] ?? '' }}{{ ! empty($emisor['telefono']) && ! empty($emisor['email']) ? ' · ' : '' }}{{ $emisor['email'] ?? '' }}</p>
        @endif
    </div>

    @php $lineas = $documento->payload['lineas'] ?? []; @endphp
    @if (! empty($lineas))
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Description') }}</td>
                <td class="end">{{ __('Quantity') }}</td>
                <td class="end">{{ __('Unit price') }}</td>
                <td class="end">{{ __('Subtotal') }}</td>
            </tr>
            @foreach ($lineas as $linea)
                <tr class="items-row">
                    <td>{{ $linea['descripcion'] ?? '—' }}</td>
                    <td class="end">{{ $linea['cantidad'] ?? '—' }}</td>
                    <td class="end">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                    <td class="end">{{ number_format((float) ($linea['subtotal'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="totals">
        <tr>
            <td class="muted">{{ __('Subtotal') }}</td>
            <td class="end">{{ number_format((float) $documento->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Tax') }}</td>
            <td class="end">{{ number_format((float) $documento->tax_total, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('Total') }}</td>
            <td class="end">{{ $documento->total_formatted }}</td>
        </tr>
    </table>

    @php
        $paymentMeansList = $documento->payload['payment_means_list'] ?? [];
        $paymentFormLabels = ['1' => __('Cash'), '2' => __('Credit')];
        $paymentMeansCodeCatalog = \App\Models\PaymentMeansCode::all()->keyBy('codigo');
        $notas = $documento->payload['notas'] ?? [];
    @endphp

    @if (! empty($paymentMeansList))
        <table class="items" style="margin-top: 14px;">
            <tr class="items-head">
                <td>{{ __('Payment form') }}</td>
                <td>{{ __('Payment method') }}</td>
                <td class="end">{{ __('Due date') }}</td>
            </tr>
            @foreach ($paymentMeansList as $pago)
                <tr class="items-row">
                    <td>{{ $paymentFormLabels[$pago['id'] ?? ''] ?? $pago['id'] ?? '—' }}</td>
                    <td>{{ $paymentMeansCodeCatalog[$pago['codigo'] ?? '']->medio ?? $pago['codigo'] ?? '—' }}</td>
                    <td class="end">{{ $pago['fecha_vencimiento'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($notas))
        <div class="info-box">
            <p class="info-label">{{ __('Notes') }}</p>
            @foreach ($notas as $nota)
                <p class="info-value">{{ $nota }}</p>
            @endforeach
        </div>
    @endif

    <p class="footer-note">{{ __('This is not the graphic representation the provider generated -- they did not send one. Billingo built this page from the data in the XML they sent.') }}</p>
</body>
</html>
