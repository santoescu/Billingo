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
        .doc-title { font-size: 18px; font-weight: bold; margin: 0; color: #1f2933; white-space: nowrap; }
        .doc-numeral { font-size: 12px; margin: 3px 0 0; color: #4b5563; }
        .doc-env-tag { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; background-color: #fef3c7; color: #92400e; }
        .doc-uuid { font-size: 8px; word-break: break-all; color: #9ca3af; margin: 0 0 10px; }

        .divider { border-top: 1px solid #d1d5db; margin: 10px 0; }

        .info-table { margin-top: 12px; }
        .info-table td { vertical-align: top; padding: 0; }
        .info-table td.gap { width: 3%; }
        .info-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; }
        .info-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #9ca3af; margin: 0 0 4px; }
        .info-value { font-size: 12px; margin: 0; }

        .meta-table { margin-top: 12px; }
        .meta-table td { padding: 2px 0; font-size: 10.5px; }
        .meta-table .meta-label { color: #6b7280; width: 45%; }
        .meta-table .meta-value { text-align: right; }

        .items { border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; margin-top: 18px; }
        .items-head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #4b5563; padding: 8px 10px; }
        .items-row td { padding: 9px 10px; font-size: 11px; border-top: 1px solid #e5e7eb; }
        .items-row.alt td { background-color: #fafafa; }
        .col-description { width: 40%; }
        .col-qty { width: 12%; }
        .col-price { width: 16%; }
        .col-tax { width: 16%; }
        .col-subtotal { width: 16%; }

        .tax-summary { width: 55%; margin-top: 14px; }
        .tax-summary td { padding: 3px 10px; font-size: 10.5px; }
        .tax-summary th { padding: 3px 10px; font-size: 9px; text-transform: uppercase; color: #6b7280; text-align: left; }

        .totals { width: 45%; margin-left: 55%; margin-top: 14px; }
        .totals td { padding: 4px 10px; font-size: 12px; }
        .totals .total-row td { border-top: 1.5px solid #111827; padding-top: 8px; font-size: 15px; font-weight: bold; }

        .footer-note { margin-top: 16px; padding: 10px 14px; background-color: #f9fafb; border-radius: 4px; font-size: 9.5px; color: #6b7280; }
    </style>
</head>
@php
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Electronic sales invoice (export)'),
        '03' => __('Electronic transmission instrument (type 03)'),
        '04' => __('Electronic sales invoice (type 04)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];

    $paymentFormLabels = [
        '1' => __('Cash'),
        '2' => __('Credit'),
    ];
@endphp
<body>
    <table class="header-table">
        <tr>
            <td style="width: 46%;">
                <table>
                    <tr>
                        @if ($company->logo_url)
                            <td style="width: 1%; white-space: nowrap; padding-right: 12px;">
                                <img src="{{ $company->logo_url }}" alt="" style="max-height: 90px; max-width: 160px;">
                            </td>
                        @endif
                        <td>
                            <p class="company-name">{{ $company->name }}</p>
                            <p class="company-sub">NIT {{ $company->identificacion }}{{ $company->dv ? '-' . $company->dv : '' }}</p>
                            @if ($company->address)
                                <p class="company-sub">{{ $company->address }}</p>
                            @endif
                            @if ($company->phone)
                                <p class="company-sub">{{ $company->phone }}</p>
                            @endif
                            @if ($company->email)
                                <p class="company-sub">{{ $company->email }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="end" style="width: 54%;">
                <table>
                    <tr>
                        <td class="end">
                            <p class="doc-title">{{ $documentTypeLabels[$documento->tipo_documento ?? ''] ?? $documento->tipo_documento }}</p>
                            <p class="doc-numeral">{{ $documento->numeral }}</p>
                            <p class="doc-numeral">{{ optional($documento->issue_date)->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</p>
                            @if ($documento->ambiente !== \App\Models\Company::DIAN_AMBIENTE_PRODUCCION)
                                <span class="doc-env-tag">{{ __('Test environment') }}</span>
                            @endif
                        </td>
                        @if ($qrDataUri)
                            <td class="end" style="width: 1%; white-space: nowrap; padding-left: 12px;">
                                <img src="{{ $qrDataUri }}" alt="QR" width="95" height="95" style="width: 2.5cm; height: 2.5cm;">
                            </td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="doc-uuid center">{{ $documento->uuid }}</p>

    <div class="divider"></div>

    @php $cliente = $documento->payload['accounting_customer_party'] ?? []; @endphp
    <table class="info-table">
        <tr>
            <td style="width: 48.5%;">
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
            <td class="gap"></td>
            <td style="width: 48.5%;">
                <div class="info-box">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">{{ __('DIAN authorization number') }}</td>
                            <td class="meta-value">{{ $documento->resolution?->resolution_number ?? '—' }}</td>
                        </tr>
                        @if ($documento->resolution)
                            <tr>
                                <td class="meta-label">{{ __('Range') }}</td>
                                <td class="meta-value">{{ $documento->resolution->prefix }}{{ $documento->resolution->range_from }} - {{ $documento->resolution->prefix }}{{ $documento->resolution->range_to ?? __('no limit') }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">{{ __('Valid') }}</td>
                                <td class="meta-value">{{ $documento->resolution->valid_from?->format('Y-m-d') }} — {{ $documento->resolution->valid_to?->format('Y-m-d') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="meta-label">{{ __('Payment form') }}</td>
                            <td class="meta-value">{{ $paymentFormLabels[$documento->payment_means_id ?? ''] ?? $documento->payment_means_id ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">{{ __('Payment method') }}</td>
                            <td class="meta-value">{{ $documento->payment_method_name ?? $paymentMeansCode->medio ?? '—' }}</td>
                        </tr>
                        @if ($documento->is_credit)
                            <tr>
                                <td class="meta-label">{{ __('Due date') }}</td>
                                <td class="meta-value">{{ optional($documento->due_date)->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <tr class="items-head">
            <td class="col-description">{{ __('Description') }}</td>
            <td class="col-qty center">{{ __('Qty') }}</td>
            <td class="col-price center">{{ __('Price') }}</td>
            <td class="col-tax center">{{ __('Tax') }}</td>
            <td class="col-subtotal center">{{ __('Subtotal') }}</td>
        </tr>
        @foreach (($documento->payload['lineas'] ?? []) as $index => $linea)
            @php
                $lineTotal = (float) ($linea['cantidad'] ?? 0) * (float) ($linea['precio_unitario'] ?? 0);
                $lineTaxes = collect($linea['impuestos'] ?? [])
                    ->map(fn ($impuesto) => ($impuesto['nombre'] ?? '') . ' ' . rtrim(rtrim(number_format((float) ($impuesto['porcentaje'] ?? 0), 2, '.', ''), '0'), '.') . '%')
                    ->implode(', ');
            @endphp
            <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                <td class="col-description">{{ $linea['descripcion'] ?? '' }}</td>
                <td class="col-qty center">{{ rtrim(rtrim(number_format((float) ($linea['cantidad'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
                <td class="col-price center">{{ number_format((float) ($linea['precio_unitario'] ?? 0), 2) }}</td>
                <td class="col-tax center muted">{{ $lineTaxes ?: '—' }}</td>
                <td class="col-subtotal center">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    @php $impuestos = $documento->payload['impuestos'] ?? []; @endphp
    @if (! empty($impuestos))
        <table class="tax-summary">
            <tr>
                <th>{{ __('Tax') }}</th>
                <th class="end">{{ __('Rate') }}</th>
                <th class="end">{{ __('Taxable base') }}</th>
                <th class="end">{{ __('Value') }}</th>
            </tr>
            @foreach ($impuestos as $impuesto)
                <tr>
                    <td>{{ $impuesto['nombre'] ?? '—' }}</td>
                    <td class="end">{{ rtrim(rtrim(number_format((float) ($impuesto['porcentaje'] ?? 0), 2, '.', ''), '0'), '.') }}%</td>
                    <td class="end">{{ number_format((float) ($impuesto['taxable_amount'] ?? 0), 2) }}</td>
                    <td class="end">{{ number_format((float) ($impuesto['tax_amount'] ?? 0), 2) }}</td>
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

    <p class="footer-note">{{ __('This document is the graphic representation of a :type, generated in accordance with DIAN regulations. Scan the QR code to verify it in the DIAN catalog.', ['type' => $documentTypeLabels[$documento->tipo_documento ?? ''] ?? $documento->tipo_documento]) }}</p>
</body>
</html>
