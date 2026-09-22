{{--
    Sección de eventos RADIAN (acuse de recibo, recibo del bien/servicio, aceptación
    expresa/tácita, inscripción como título valor, etc. -- Resolución DIAN 000015 de 2021) de un
    documento -- compartida entre documents/show.blade.php y received-documents/show.blade.php.
    Usa el mismo render de tarjetas (window.renderDianInfo, ver partials/dian-document-info.blade.php)
    que ya usaba documents/create.blade.php al validar un UUID de referencia -- si ya se consultó
    antes ($documento->radian_info), se pinta de una vez con lo guardado; el botón vuelve a
    consultar en vivo contra DIAN, ya que a diferencia del historial de correo esto SÍ pega contra
    un servicio externo. $documento y $radianEventsUrl los define quien incluye este partial.
--}}
@if ($documento->uuid)
    @include('partials.dian-document-info')

    <div id="doc-show-radian-events" class="border border-gray-200 rounded-lg dark:border-neutral-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700 flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('RADIAN events') }}</h3>
            <button type="button" id="doc-radian-events-btn" class="flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none" data-url="{{ $radianEventsUrl }}">
                <svg id="doc-radian-events-icon" class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                {{ __('Check events') }}
            </button>
        </div>
        <div class="p-4 flex flex-col gap-3">
            <p id="doc-radian-events-synced" class="text-xs text-zinc-500 dark:text-neutral-400">
                @if ($documento->radian_synced_at)
                    {{ __('Last checked') }}: {{ $documento->radian_synced_at->setTimezone('America/Bogota')->format('Y-m-d H:i') }}
                @else
                    {{ __('Not checked yet.') }}
                @endif
            </p>
            <div id="doc-radian-events-body" class="text-sm text-gray-700 dark:text-neutral-300"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                /**
                 * Consulta DIAN en vivo y reemplaza lo que se ve -- se llama tanto al hacer clic
                 * en el botón como automáticamente al cargar la página (ver
                 * initRadianEventsButtons()), en los dos casos deja lo ya guardado a la vista
                 * mientras responde, en vez de mostrar un estado de carga vacío.
                 * @param {string} url
                 * @returns {Promise<void>}
                 */
                async function checkRadianEvents(url) {
                    const button = document.getElementById('doc-radian-events-btn');
                    const icon = document.getElementById('doc-radian-events-icon');
                    if (button.disabled) {
                        return;
                    }

                    button.disabled = true;
                    if (icon) icon.classList.add('animate-spin');

                    try {
                        const response = await fetch(url, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json();

                        if (! data.success) {
                            window.appConfirmDialog?.notify(data.message || @json(__('Could not check RADIAN events.')), @json(__('RADIAN events')));
                            return;
                        }

                        document.getElementById('doc-radian-events-body').innerHTML = window.renderDianInfo(data.info, false);

                        const synced = document.getElementById('doc-radian-events-synced');
                        if (synced) synced.textContent = @json(__('Last checked') . ': ') + data.synced_at;
                    } catch (error) {
                        window.appConfirmDialog?.notify(@json(__('Could not check RADIAN events.')), @json(__('RADIAN events')));
                    } finally {
                        button.disabled = false;
                        if (icon) icon.classList.remove('animate-spin');
                    }
                }

                function initRadianEventsButtons() {
                    const body = document.getElementById('doc-radian-events-body');
                    if (body && ! body.dataset.rendered) {
                        body.dataset.rendered = 'true';
                        const initialInfo = @json($documento->radian_info);
                        body.innerHTML = initialInfo
                            ? window.renderDianInfo(initialInfo, false)
                            : '<p class="text-zinc-500 dark:text-neutral-400">{{ __('Not checked yet.') }}</p>';

                        checkRadianEvents(document.getElementById('doc-radian-events-btn').dataset.url);
                    }

                    if (document.body.dataset.docRadianEventsBound === 'true') {
                        return;
                    }
                    document.body.dataset.docRadianEventsBound = 'true';

                    document.addEventListener('click', function (event) {
                        const button = event.target.closest('#doc-radian-events-btn');
                        if (button && ! button.disabled) {
                            checkRadianEvents(button.dataset.url);
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', initRadianEventsButtons);
                document.addEventListener('livewire:navigated', initRadianEventsButtons);
            })();
        </script>
    @endpush
@endif
