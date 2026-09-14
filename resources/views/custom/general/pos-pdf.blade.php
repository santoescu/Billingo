<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #111; margin: 0; }
        .center { text-align: center; }
        .end { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #555; }
        .divider { border-top: 1px dashed #999; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 0; vertical-align: top; }
        .lines th { border-bottom: 1px solid #999; text-align: left; font-size: 9px; text-transform: uppercase; }
        .totals td { padding: 1px 0; }
        h1 { font-size: 13px; margin: 0 0 2px; }
        p { margin: 2px 0; }
    </style>
</head>
<body>
    <div class="center">
        @if ($company->logo_url)
            <img src="{{ $company->logo_url }}" alt="" style="max-height: 40px; max-width: 120px; margin-bottom: 4px;">
        @endif
        <h1>{{ $company->name }}</h1>
        <p class="muted">NIT {{ $company->identificacion }}{{ $company->dv ? '-' . $company->dv : '' }}</p>
        <p class="bold">{{ $documento->numeral }}</p>
        <p class="muted">{{ optional($documento->issue_date)->format('Y-m-d H:i') }}</p>
        @unless ($isElectronic)
            <p class="bold" style="margin-top: 4px;">{{ __('REMISION - not an electronic invoice') }}</p>
        @endunless
    </div>

    <div class="divider"></div>

    @php $cliente = $documento->payload['accounting_customer_party'] ?? []; @endphp
    <p><span class="bold">{{ __('Client') }}:</span> {{ $cliente['razon_social'] ?? '' }}</p>
    <p class="muted">{{ __('Identification') }}: {{ $cliente['identificacion'] ?? '' }}</p>

    <div class="divider"></div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="end">{{ __('Qty') }}</th>
                <th class="end">{{ __('Price') }}</th>
                <th class="end">{{ __('Subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($documento->payload['lineas'] ?? []) as $linea)
                @php $lineTotal = (float) ($linea['cantidad'] ?? 0) * (float) ($linea['precio_unitario'] ?? 0); @endphp
                <tr>
                    <td>{{ $linea['descripcion'] ?? '' }}</td>
                    <td class="end">{{ rtrim(rtrim(number_format((float) ($linea['cantidad'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
                    <td class="end">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                    <td class="end">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="totals">
        <tr>
            <td>{{ __('Subtotal') }}</td>
            <td class="end">{{ number_format((float) $documento->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('Tax') }}</td>
            <td class="end">{{ number_format((float) $documento->tax_total, 2) }}</td>
        </tr>
        <tr>
            <td class="bold">{{ __('Total') }}</td>
            <td class="end bold">{{ $documento->total_formatted }}</td>
        </tr>
        @if (count($documento->payments ?? []) > 1)
            @foreach ($documento->payments as $payment)
                <tr>
                    <td>{{ $payment['payment_method_name'] ?? __('Payment method') }}</td>
                    <td class="end">{{ number_format((float) ($payment['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        @elseif ($documento->payment_method_name || $paymentMeansCode)
            <tr>
                <td>{{ __('Payment method') }}</td>
                <td class="end">{{ $documento->payment_method_name ?? $paymentMeansCode->medio }}</td>
            </tr>
        @endif
        @if ($cashReceived !== null)
            @php
                $cashPortion = count($documento->payments ?? []) > 0
                    ? collect($documento->payments)->where('dian_code', '10')->sum('amount')
                    : (float) $documento->total;
            @endphp
            <tr>
                <td>{{ __('Cash received') }}</td>
                <td class="end">{{ number_format((float) $cashReceived, 2) }}</td>
            </tr>
            <tr>
                <td class="bold">{{ __('Change') }}</td>
                <td class="end bold">{{ number_format(max((float) $cashReceived - $cashPortion, 0), 2) }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    @if ($isElectronic)
        <p class="center muted">CUFE</p>
        <p class="center" style="word-break: break-all; font-size: 7px;">{{ $uuid }}</p>
    @else
        <p class="center muted">{{ __('REMISION - not an electronic invoice') }}</p>
    @endif

    <p class="center muted" style="margin-top: 8px;">{{ __('Thank you for your purchase!') }}</p>
</body>
</html>
