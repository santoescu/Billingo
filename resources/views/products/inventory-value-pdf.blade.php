<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 10px; margin: 0; }
        .end { text-align: right; }
        .summary { margin: 14px 0 12px; }
        .summary td { width: 33%; padding: 8px 10px; border: 1px solid #e5e7eb; }
        .summary .label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin: 0 0 3px; }
        .summary .value { font-size: 15px; font-weight: bold; margin: 0; }
        .head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; color: #4b5563; padding: 7px 8px; }
        .row td { padding: 6px 8px; border-top: 1px solid #e5e7eb; }
        .total td { padding: 8px; border-top: 1.5px solid #111827; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>
    @php
        $money = fn ($value) => '$' . number_format((float) $value, 2, ',', '.');
        $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',') ?: '0';
    @endphp

    @if ($isFirst)
        <h1>{{ __('Inventory value') }}</h1>
        <p class="muted">{{ $company->name }} &middot; {{ now('America/Bogota')->format('d/m/Y H:i') }}</p>

        <table class="summary">
            <tr>
                <td>
                    <p class="label">{{ __('Inventory cost') }}</p>
                    <p class="value">{{ $money($totals['cost']) }}</p>
                </td>
                <td>
                    <p class="label">{{ __('Products in stock') }}</p>
                    <p class="value">{{ number_format($totals['products'], 0, ',', '.') }}</p>
                </td>
                <td>
                    <p class="label">{{ __('Units in stock') }}</p>
                    <p class="value">{{ $qty($totals['units']) }}</p>
                </td>
            </tr>
        </table>
    @endif

    <table>
        <tr class="head">
            <td style="width: 14%;">{{ __('Code') }}</td>
            <td>{{ __('Product') }}</td>
            <td class="end" style="width: 12%;">{{ __('Stock') }}</td>
            <td class="end" style="width: 18%;">{{ __('Average cost') }}</td>
            <td class="end" style="width: 20%;">{{ __('Total cost') }}</td>
        </tr>
        @foreach ($rows as $row)
            <tr class="row">
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="end">{{ $qty($row['stock']) }}</td>
                <td class="end">{{ $money($row['average_cost']) }}</td>
                <td class="end">{{ $money($row['total']) }}</td>
            </tr>
        @endforeach
        @if ($isLast)
            <tr class="total">
                <td colspan="2">{{ __('Totals') }}</td>
                <td class="end">{{ $qty($totals['units']) }}</td>
                <td></td>
                <td class="end">{{ $money($totals['cost']) }}</td>
            </tr>
        @endif
    </table>
</body>
</html>
