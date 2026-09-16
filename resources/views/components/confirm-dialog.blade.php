<div id="app-confirm-dialog" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="app-confirm-dialog-title">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <div class="p-4">
                <h3 id="app-confirm-dialog-title" class="font-bold text-gray-800 dark:text-white mb-1">{{ __('Are you sure?') }}</h3>
                <p id="app-confirm-dialog-message" class="text-sm text-neutral-600 dark:text-neutral-400 mb-4 whitespace-pre-line max-h-[65vh] overflow-y-auto text-start"></p>

                {{-- El "hidden" va en el <div> envolvente de cada botón, no
                     directo en el flux:button: flux:button siempre trae su
                     propia clase "inline-flex" (aunque se le pase "hidden"
                     en $attributes), y en el CSS compilado esa clase queda
                     definida DESPUÉS de ".hidden" -- mismo nivel de
                     especificidad, empate que gana la que está más abajo en
                     la hoja, así que el botón nunca se llegaba a ocultar de
                     verdad por más que se le alternara la clase por JS
                     (mismo problema ya resuelto así en el modal de
                     resultado del POS). --}}
                <div class="flex justify-end gap-3">
                    <div id="app-confirm-dialog-cancel-wrapper">
                        <flux:button type="button" variant="filled" id="app-confirm-dialog-cancel-btn" onclick="window.appConfirmDialog.cancel()">{{ __('Cancel') }}</flux:button>
                    </div>
                    {{-- Dos botones de aceptar, uno por variante (danger para borrar, primary
                         para confirmar cualquier otra acción) -- flux:button hornea sus clases de
                         color en tiempo de compilación, así que no se puede "cambiar de variante"
                         por JS sobre un mismo botón; se renderizan ambos y se alterna cuál queda
                         visible según la variante que pida cada open()/ask() (ver
                         window.appConfirmDialog más abajo). --}}
                    <div id="app-confirm-dialog-accept-danger-wrapper">
                        <flux:button type="button" variant="danger" class="app-confirm-dialog-accept-btn" onclick="window.appConfirmDialog.accept()">
                            <span class="app-confirm-dialog-accept-label">{{ __('Delete') }}</span>
                            <span class="app-confirm-dialog-accept-spinner hidden">
                                <span class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                    {{ __('Processing...') }}
                                </span>
                            </span>
                        </flux:button>
                    </div>
                    <div id="app-confirm-dialog-accept-primary-wrapper" class="hidden">
                        <flux:button type="button" variant="primary" class="app-confirm-dialog-accept-btn" onclick="window.appConfirmDialog.accept()">
                            <span class="app-confirm-dialog-accept-label">{{ __('Confirm') }}</span>
                            <span class="app-confirm-dialog-accept-spinner hidden">
                                <span class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                    {{ __('Processing...') }}
                                </span>
                            </span>
                        </flux:button>
                    </div>
                    <div id="app-confirm-dialog-ok-wrapper" class="hidden">
                        <flux:button type="button" variant="primary" id="app-confirm-dialog-ok-btn" onclick="window.appConfirmDialog.accept()">{{ __('OK') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Mientras un modal está "procesando" (enviando un form, esperando un
     * fetch), no debería poder cerrarse -- ni con clic afuera, ni con el
     * botón "X", ni con Escape -- porque si no, alguien impaciente lo cierra
     * pensando que no pasó nada mientras la petición sigue en el servidor.
     *
     * El backdrop de Preline cierra el modal SIN revisar ninguna bandera
     * (backdropClick() llama this.close() directo), así que interceptar el
     * evento de clic no es confiable. En vez de eso, se reemplaza el método
     * close() de la instancia del overlay por uno que no hace nada mientras
     * está "bloqueado" -- así, sin importar qué lo dispare (fondo, X,
     * Escape), no se cierra hasta llamar a stop().
     *
     * @returns {object} start(selector), stop(selector)
     */
    window.appModalProcessing = (function () {
        function overlayInstance(selector) {
            if (!window.HSOverlay || !selector) return null;
            const found = HSOverlay.getInstance(selector, true);

            return found ? found.element : null;
        }

        return {
            start(selector) {
                const instance = overlayInstance(selector);
                if (!instance || instance.__appGuardedClose) return;

                instance.__appGuardedClose = instance.close.bind(instance);
                instance.close = () => Promise.resolve();
            },
            stop(selector) {
                const instance = overlayInstance(selector);
                if (!instance || !instance.__appGuardedClose) return;

                instance.close = instance.__appGuardedClose;
                delete instance.__appGuardedClose;
            },
        };
    })();

    /**
     * Reemplaza confirm()/alert() nativos del navegador (feos y no
     * personalizables) por un modal propio, reutilizado en toda la app.
     * Tres formas de usarlo:
     *   - En un <form onsubmit="return window.appConfirmDialog.open(event, this, '...', options)">
     *     -- al aceptar, deshabilita el botón, muestra un spinner de
     *     "Procesando..." y envía el formulario de verdad.
     *   - window.appConfirmDialog.ask('...', title, options).then(ok => ...) -- para
     *     confirmar/cancelar en flujos con fetch() que ya manejan su propio
     *     estado de carga.
     *   - window.appConfirmDialog.notify('...') -- reemplazo directo de
     *     alert(): un solo botón "OK", sin opción de cancelar.
     *
     * "options" es { label, variant } -- variant 'danger' (por defecto, botón
     * rojo + "Eliminar") solo para acciones destructivas; cualquier otra
     * confirmación (enviar correos, etc.) debe pasar variant: 'primary' para
     * que el botón no diga "Eliminar" ni se vea rojo cuando no se está
     * borrando nada.
     *
     * @returns {object} open(event, form, message, options), ask(message, title, options), notify(message), cancel(), accept()
     */
    window.appConfirmDialog = (function () {
        let pendingForm = null;
        let pendingResolve = null;

        function acceptWrapper(variant) {
            return document.getElementById(variant === 'primary' ? 'app-confirm-dialog-accept-primary-wrapper' : 'app-confirm-dialog-accept-danger-wrapper');
        }

        function reset() {
            document.querySelectorAll('.app-confirm-dialog-accept-label').forEach((el) => el.classList.remove('hidden'));
            document.querySelectorAll('.app-confirm-dialog-accept-spinner').forEach((el) => el.classList.add('hidden'));
            document.querySelectorAll('.app-confirm-dialog-accept-btn').forEach((el) => el.disabled = false);
            document.getElementById('app-confirm-dialog-cancel-btn').disabled = false;
        }

        function show(message, isAlert, title, options = {}) {
            const variant = options.variant === 'primary' ? 'primary' : 'danger';

            document.getElementById('app-confirm-dialog-title').textContent = title || '{{ __('Are you sure?') }}';
            document.getElementById('app-confirm-dialog-message').textContent = message || '';
            document.getElementById('app-confirm-dialog-cancel-wrapper').classList.toggle('hidden', isAlert);
            document.getElementById('app-confirm-dialog-accept-danger-wrapper').classList.toggle('hidden', isAlert || variant !== 'danger');
            document.getElementById('app-confirm-dialog-accept-primary-wrapper').classList.toggle('hidden', isAlert || variant !== 'primary');
            document.getElementById('app-confirm-dialog-ok-wrapper').classList.toggle('hidden', ! isAlert);
            reset();

            const acceptLabelEl = acceptWrapper(variant)?.querySelector('.app-confirm-dialog-accept-label');
            if (options.label && acceptLabelEl) {
                acceptLabelEl.textContent = options.label;
            }

            window.appModalProcessing.stop('#app-confirm-dialog');

            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#app-confirm-dialog');
            }
        }

        function open(event, form, message, options = {}) {
            event.preventDefault();
            pendingForm = form;
            pendingResolve = null;
            show(message, false, null, options);

            return false;
        }

        function ask(message, title, options = {}) {
            return new Promise((resolve) => {
                pendingForm = null;
                pendingResolve = resolve;
                show(message, false, title, options);
            });
        }

        function notify(message, title) {
            return new Promise((resolve) => {
                pendingForm = null;
                pendingResolve = resolve;
                show(message, true, title);
            });
        }

        function cancel() {
            const resolve = pendingResolve;
            pendingForm = null;
            pendingResolve = null;

            if (window.HSOverlay) HSOverlay.close('#app-confirm-dialog');
            if (resolve) resolve(false);
        }

        function accept() {
            if (pendingForm) {
                document.querySelectorAll('.app-confirm-dialog-accept-label').forEach((el) => el.classList.add('hidden'));
                document.querySelectorAll('.app-confirm-dialog-accept-spinner').forEach((el) => el.classList.remove('hidden'));
                document.querySelectorAll('.app-confirm-dialog-accept-btn').forEach((el) => el.disabled = true);
                document.getElementById('app-confirm-dialog-cancel-btn').disabled = true;
                window.appModalProcessing.start('#app-confirm-dialog');

                const form = pendingForm;
                pendingForm = null;
                form.submit();

                return;
            }

            if (pendingResolve) {
                const resolve = pendingResolve;
                pendingResolve = null;
                if (window.HSOverlay) HSOverlay.close('#app-confirm-dialog');
                resolve(true);
            }
        }

        return { open, ask, notify, cancel, accept };
    })();
</script>
