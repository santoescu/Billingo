{{-- $items: array de productos (codigo/descripcion/cantidad/total) o
     clientes (name/total), según $type. Compartido entre las 4 tarjetas de
     "más vendido"/"que más compra" del panel. --}}
@if (empty($items))
    <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('No data yet.') }}</p>
@else
    <ul class="space-y-3">
        @foreach ($items as $item)
            <li class="flex justify-between items-start gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-800 dark:text-white truncate">{{ $type === 'product' ? $item['descripcion'] : $item['name'] }}</p>
                    @if ($type === 'product')
                        <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Quantity') }}: {{ rtrim(rtrim(number_format($item['cantidad'], 2), '0'), '.') }}</p>
                    @endif
                </div>
                <span class="shrink-0 text-sm font-medium text-gray-800 dark:text-white">${{ number_format($item['total'], 2) }}</span>
            </li>
        @endforeach
    </ul>
@endif
