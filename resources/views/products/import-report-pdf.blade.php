<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .muted { color: #6b7280; font-size: 10px; margin: 0; }
        .end { text-align: right; }
        .head td { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; color: #4b5563; padding: 7px 8px; }
        .row td { padding: 7px 8px; border-top: 1px solid #e5e7eb; }
        .badge-add { color: #047857; font-weight: bold; }
        .badge-over { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('Product import report') }}</h1>
    <p class="muted">{{ $company->name }} · {{ now()->format('d/m/Y H:i') }}</p>
    <p class="muted">{{ __('Created') }}: {{ $created }} · {{ __('Updated') }}: {{ $updated }}</p>

    @foreach (['created' => __('Created products'), 'updated' => __('Updated products')] as $action => $title)
        @php($items = collect($report)->where('action', $action))
        @if ($items->isNotEmpty())
            <h2>{{ $title }} ({{ $items->count() }})</h2>
            <table>
                <tr class="head">
                    <td style="width: 14%;">{{ __('Code') }}</td>
                    <td>{{ __('Description') }}</td>
                    <td style="width: 20%;">{{ __('Stock action') }}</td>
                    <td class="end" style="width: 11%;">{{ __('Imported quantity') }}</td>
                    <td class="end" style="width: 11%;">{{ __('Final stock') }}</td>
                </tr>
                @foreach ($items as $item)
                    <tr class="row">
                        <td>{{ $item['code'] }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td>
                            @switch($item['stock_action'])
                                @case('added')<span class="badge-add">{{ __('Added to stock') }}</span>@break
                                @case('overwritten')<span class="badge-over">{{ __('Overwrote stock') }}</span>@break
                                @case('new'){{ __('New product') }}@break
                                @default<span class="muted">{{ __('Stock not modified') }}</span>
                            @endswitch
                        </td>
                        <td class="end">{{ $item['imported_stock'] !== null ? rtrim(rtrim(number_format($item['imported_stock'], 2, ',', '.'), '0'), ',') : '-' }}</td>
                        <td class="end">{{ rtrim(rtrim(number_format($item['final_stock'], 2, ',', '.'), '0'), ',') ?: '0' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach
</body>
</html>
