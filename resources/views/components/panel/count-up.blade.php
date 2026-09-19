@props(['target' => 0, 'prefix' => '', 'decimals' => 0])

{{--
    Anima el número de 0 hasta $target apenas Livewire termina de montar la
    tarjeta que lo contiene -- cada tarjeta del Panel carga de forma
    independiente (ver App\Livewire\Panel\PanelWidget), así que este conteo
    refuerza visualmente que cada una llegó por su cuenta, no todas a la vez.
    x-init corre una sola vez al insertarse el nodo en el DOM (primer render
    real del componente Livewire, no el placeholder).
--}}
<span
    x-data="{
        display: 0,
        init() {
            const target = {{ (float) $target }};
            const duration = 700;
            const start = performance.now();
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                this.display = target * eased;
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    this.display = target;
                }
            };
            requestAnimationFrame(step);
        }
    }"
    x-text="'{{ $prefix }}' + display.toLocaleString('es-CO', { minimumFractionDigits: {{ $decimals }}, maximumFractionDigits: {{ $decimals }} })"
>{{ $prefix }}{{ number_format($target, $decimals) }}</span>
