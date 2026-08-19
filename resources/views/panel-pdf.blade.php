@php
    $periods = ['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'last_month' => __('Last month'), 'year' => __('This year')];
    $periodLabel = $periods[$period];
@endphp
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

        .header-table td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; margin: 0 0 2px; }
        .company-sub { font-size: 10px; margin: 3px 0 0; color: #6b7280; }
        .panel-title { font-size: 20px; font-weight: bold; margin: 0; color: #1f2933; }
        .panel-sub { font-size: 12px; margin: 3px 0 0; color: #4b5563; }

        .divider { border-top: 1px solid #d1d5db; margin: 10px 0; }

        h2 { font-size: 13px; margin: 18px 0 8px; }

        .kpi-table { margin-bottom: 6px; }
        .kpi-table td { border: 1px solid #e5e7eb; padding: 8px 10px; vertical-align: top; width: 33%; }
        .kpi-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #9ca3af; margin: 0 0 4px; }
        .kpi-value { font-size: 14px; font-weight: bold; margin: 0; }

        .items { border: 1px solid #e5e7eb; }
        .items-head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #4b5563; padding: 6px 10px; }
        .items-row td { padding: 6px 10px; font-size: 11px; border-top: 1px solid #e5e7eb; }
        .items-row.alt td { background-color: #fafafa; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if ($company->logo_url)
                    <img src="{{ $company->logo_url }}" alt="" style="max-height: 50px; max-width: 160px; margin-bottom: 6px;">
                @endif
                <p class="company-name">{{ $company->name }}</p>
                <p class="company-sub">NIT {{ $company->identificacion }}{{ $company->dv ? '-' . $company->dv : '' }}</p>
            </td>
            <td class="end" style="width: 40%;">
                <p class="panel-title">{{ __('Panel') }}</p>
                <p class="panel-sub">{{ $periodLabel }}</p>
                <p class="panel-sub">{{ now('America/Bogota')->format('Y-m-d H:i') }}</p>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    @if (isset($metrics['invoicing']))
        @php $m = $metrics['invoicing']; @endphp
        <h2>{{ config('modules.invoicing.name') }}</h2>
        <table class="kpi-table">
            <tr>
                <td><p class="kpi-label">{{ __('Issued today') }}</p><p class="kpi-value">{{ $m['today_count'] }} &middot; ${{ number_format($m['today_total'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Issued') }} ({{ $periodLabel }})</p><p class="kpi-value">{{ $m['period_count'] }} &middot; ${{ number_format($m['period_total'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Issues') }} ({{ $periodLabel }})</p><p class="kpi-value">{{ $m['period_issues'] }}</p></td>
            </tr>
        </table>
    @endif

    @if (isset($metrics['pos']))
        @php $m = $metrics['pos']; @endphp
        <h2>{{ config('modules.pos.name') }}</h2>
        <table class="kpi-table">
            <tr>
                <td><p class="kpi-label">{{ __('Open shifts') }}</p><p class="kpi-value">{{ $m['open_shifts'] }}</p></td>
                <td><p class="kpi-label">{{ __('Sales today') }}</p><p class="kpi-value">{{ $m['today_count'] }} &middot; ${{ number_format($m['today_total'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Sales') }} ({{ $periodLabel }})</p><p class="kpi-value">{{ $m['period_count'] }} &middot; ${{ number_format($m['period_total'], 2) }}</p></td>
            </tr>
        </table>
    @endif

    @if (isset($metrics['cotizaciones']))
        @php $m = $metrics['cotizaciones']; @endphp
        <h2>{{ config('modules.cotizaciones.name') }}</h2>
        <table class="kpi-table">
            <tr>
                <td><p class="kpi-label">{{ __('Pending') }}</p><p class="kpi-value">{{ $m['pending_count'] }}</p></td>
                <td><p class="kpi-label">{{ __('Converted') }} ({{ $periodLabel }})</p><p class="kpi-value">{{ $m['converted_period_count'] }}</p></td>
                <td><p class="kpi-label">{{ __('Quoted') }} ({{ $periodLabel }})</p><p class="kpi-value">${{ number_format($m['period_total'], 2) }}</p></td>
            </tr>
        </table>
    @endif

    @if ($utility)
        <h2>{{ __('Gross profit') }}</h2>
        <table class="kpi-table">
            <tr>
                <td><p class="kpi-label">{{ __('Revenue') }}</p><p class="kpi-value">${{ number_format($utility['revenue'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Cost') }}</p><p class="kpi-value">${{ number_format($utility['cogs'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Profit') }} ({{ $utility['margin_pct'] }}%)</p><p class="kpi-value">${{ number_format($utility['profit'], 2) }}</p></td>
            </tr>
        </table>
    @endif

    @if (! empty($topProductsInvoicing))
        <h2>{{ __('Top products') }} &middot; {{ config('modules.invoicing.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Product') }}</td>
                <td class="end">{{ __('Quantity') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($topProductsInvoicing as $index => $product)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $product['descripcion'] }}</td>
                    <td class="end">{{ rtrim(rtrim(number_format($product['cantidad'], 2), '0'), '.') }}</td>
                    <td class="end">${{ number_format($product['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($topProductsPos))
        <h2>{{ __('Top products') }} &middot; {{ config('modules.pos.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Product') }}</td>
                <td class="end">{{ __('Quantity') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($topProductsPos as $index => $product)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $product['descripcion'] }}</td>
                    <td class="end">{{ rtrim(rtrim(number_format($product['cantidad'], 2), '0'), '.') }}</td>
                    <td class="end">${{ number_format($product['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($topClientsInvoicing)
        <h2>{{ __('Top clients') }} &middot; {{ config('modules.invoicing.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Client') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($topClientsInvoicing as $index => $client)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $client['name'] }}</td>
                    <td class="end">${{ number_format($client['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($topClientsPos)
        <h2>{{ __('Top clients') }} &middot; {{ config('modules.pos.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Client') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($topClientsPos as $index => $client)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $client['name'] }}</td>
                    <td class="end">${{ number_format($client['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($warehouseComparison)
        <h2>{{ __('Sales by warehouse') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Warehouse') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($warehouseComparison['labels'] as $index => $label)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $label }}</td>
                    <td class="end">${{ number_format($warehouseComparison['values'][$index], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($cashierComparison)
        <h2>{{ __('Sales by cashier') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Cashier') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($cashierComparison['labels'] as $index => $label)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $label }}</td>
                    <td class="end">${{ number_format($cashierComparison['values'][$index], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($paymentMethodBreakdown['invoicing'])
        <h2>{{ __('Payment methods') }} &middot; {{ config('modules.invoicing.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Payment method') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($paymentMethodBreakdown['invoicing']['labels'] as $index => $label)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $label }}</td>
                    <td class="end">${{ number_format($paymentMethodBreakdown['invoicing']['values'][$index], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($paymentMethodBreakdown['pos'])
        <h2>{{ __('Payment methods') }} &middot; {{ config('modules.pos.name') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Payment method') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($paymentMethodBreakdown['pos']['labels'] as $index => $label)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $label }}</td>
                    <td class="end">${{ number_format($paymentMethodBreakdown['pos']['values'][$index], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($receivables)
        <h2>{{ __('Accounts receivable') }} &middot; {{ config('modules.invoicing.name') }}</h2>
        <table class="kpi-table">
            <tr>
                <td><p class="kpi-label">{{ __('Pending collection') }}</p><p class="kpi-value">{{ $receivables['pending_count'] }} &middot; ${{ number_format($receivables['total_pending'], 2) }}</p></td>
                <td><p class="kpi-label">{{ __('Overdue') }}</p><p class="kpi-value">{{ $receivables['overdue_count'] }} &middot; ${{ number_format($receivables['total_overdue'], 2) }}</p></td>
            </tr>
        </table>
    @endif

    @if (! empty($receivables['top_overdue']))
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Invoice') }}</td>
                <td>{{ __('Client') }}</td>
                <td>{{ __('Due date') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($receivables['top_overdue'] as $index => $item)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $item['numeral'] }}</td>
                    <td>{{ $item['client'] }}</td>
                    <td class="muted">{{ $item['due_date']->setTimezone('America/Bogota')->format('Y-m-d') }}</td>
                    <td class="end">${{ number_format($item['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($recentActivity))
        <h2>{{ __('Recent activity') }}</h2>
        <table class="items">
            <tr class="items-head">
                <td>{{ __('Date') }}</td>
                <td>{{ __('Document') }}</td>
                <td class="end">{{ __('Total') }}</td>
            </tr>
            @foreach ($recentActivity as $index => $item)
                <tr class="items-row {{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td class="muted">{{ $item['created_at']->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</td>
                    <td>{{ $item['label'] }} {{ $item['title'] }}</td>
                    <td class="end">${{ number_format($item['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
