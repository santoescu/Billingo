<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .end { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #6b7280; }

        .header-table td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; margin: 0 0 2px; }
        .company-sub { font-size: 10px; margin: 3px 0 0; color: #6b7280; }
        .quote-title { font-size: 20px; font-weight: bold; margin: 0; color: #1f2933; }
        .quote-numeral { font-size: 12px; margin: 3px 0 0; color: #4b5563; }

        .divider { border-top: 1px solid #d1d5db; margin: 10px 0; }

        .info-table { margin-top: 12px; }
        .info-table td { vertical-align: top; padding: 0; }
        .info-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; }
        .info-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #9ca3af; margin: 0 0 4px; }
        .info-value { font-size: 12px; margin: 0; }

        .items { border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; }
        .items-head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #4b5563; padding: 8px 10px; }
        .items-row td { padding: 9px 10px; font-size: 11px; border-top: 1px solid #e5e7eb; }
        .items-row.alt td { background-color: #fafafa; }
        .col-description { width: 44%; }
        .col-warehouse { width: 16%; }
        .col-qty { width: 12%; }
        .col-price { width: 14%; }
        .col-subtotal { width: 14%; }

        .totals { width: 45%; margin-left: 55%; margin-top: 14px; }
        .totals td { padding: 4px 10px; font-size: 12px; }
        .totals .total-row td { border-top: 1.5px solid #111827; padding-top: 8px; font-size: 15px; font-weight: bold; }

        .footer-note { margin-top: 26px; padding: 10px 14px; background-color: #f9fafb; border-radius: 4px; font-size: 9.5px; color: #6b7280; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <p class="company-name">{{ $company->name }}</p>
                <p class="company-sub">NIT {{ $company->identificacion }}{{ $company->dv ? '-' . $company->dv : '' }}</p>
                @if ($company->address)
                    <p class="company-sub">{{ $company->address }}</p>
                @endif
                @if ($company->phone || $company->email)
                    <p class="company-sub">{{ $company->phone }}{{ $company->phone && $company->email ? ' · ' : '' }}{{ $company->email }}</p>
                @endif
            </td>
            <td class="end" style="width: 40%;">
                <p class="quote-title">{{ __('QUOTATION') }}</p>
                <p class="quote-numeral">{{ $documento->numeral }}</p>
                <p class="quote-numeral">{{ optional($documento->issue_date)->format('Y-m-d H:i') }}</p>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    @php $cliente = $documento->payload['accounting_customer_party'] ?? []; @endphp
    <table class="info-table">
        <tr>
            <td>
                <div class="info-box">
                    <p class="info-label">{{ __('Client') }}</p>
                    <p class="info-value bold">{{ $cliente['razon_social'] ?? '—' }}</p>
                    <p class="info-value muted">
                        {{ __('Identification') }}: {{ $cliente['identificacion'] ?? '—' }}{{ isset($cliente['dv']) && $cliente['dv'] !== null ? '-' . $cliente['dv'] : '' }}
                    </p>
                    @if (! empty($cliente['direccion']))
                        <p class="info-value muted">{{ $cliente['direccion'] }}</p>
                    @endif
                    @if (! empty($cliente['telefono']) || ! empty($cliente['email']))
                        <p class="info-value muted">{{ $cliente['telefono'] ?? '' }}{{ ! empty($cliente['telefono']) && ! empty($cliente['email']) ? ' · ' : '' }}{{ $cliente['email'] ?? '' }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 18px;">
        <tr class="items-head">
            <td class="col-description">{{ __('Description') }}</td>
            @if ($warehousesById->isNotEmpty())
                <td class="col-warehouse">{{ __('Warehouse') }}</td>
            @endif
            <td class="col-qty center">{{ __('Qty') }}</td>
            <td class="col-price center">{{ __('Price') }}</td>
            <td class="col-subtotal center">{{ __('Subtotal') }}</td>
        </tr>
        @foreach (($documento->payload['lineas'] ?? []) as $index => $linea)
            @php $lineTotal = (float) ($linea['cantidad'] ?? 0) * (float) ($linea['precio_unitario'] ?? 0); @endphp
            <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                <td class="col-description">{{ $linea['descripcion'] ?? '' }}</td>
                @if ($warehousesById->isNotEmpty())
                    <td class="col-warehouse muted">{{ $warehousesById->get($linea['bodega_id'] ?? null)?->name ?? '—' }}</td>
                @endif
                <td class="col-qty center">{{ rtrim(rtrim(number_format((float) ($linea['cantidad'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
                <td class="col-price center">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                <td class="col-subtotal center">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

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

    <p class="footer-note">{{ __('This is a quotation, not a sales invoice. Prices are subject to change until the sale is confirmed.') }}</p>
</body>
</html>
