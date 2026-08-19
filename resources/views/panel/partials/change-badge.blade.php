{{-- $pct: porcentaje de cambio vs el periodo anterior de igual largo, o
     null si no hay referencia (el periodo anterior dio 0). --}}
@if ($pct !== null)
    <span class="ms-1 text-xs font-medium {{ $pct >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
        {{ $pct >= 0 ? '+' : '' }}{{ $pct }}%
    </span>
@endif
