<div id="app-confirm-dialog" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="app-confirm-dialog-title">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-sm sm:w-full m-3 sm:mx-auto">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <div class="p-4">
                <h3 id="app-confirm-dialog-title" class="font-bold text-gray-800 dark:text-white mb-1">{{ __('Are you sure?') }}</h3>
                <p id="app-confirm-dialog-message" class="text-sm text-neutral-600 dark:text-neutral-400 mb-4"></p>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="filled" id="app-confirm-dialog-cancel-btn" onclick="window.appConfirmDialog.cancel()">{{ __('Cancel') }}</flux:button>
                    <flux:button type="button" variant="danger" id="app-confirm-dialog-accept-btn" onclick="window.appConfirmDialog.accept()">
                        <span id="app-confirm-dialog-accept-label">{{ __('Delete') }}</span>
                        <span id="app-confirm-dialog-accept-spinner" class="hidden">
                            <span class="inline-flex items-center gap-2">
                                <span class="animate-spin inline-block size-4 border-2 border-current border-t-transparent rounded-full" role="status" aria-label="{{ __('Loading') }}"></span>
                                {{ __('Processing...') }}
                            </span>
                        </span>
                    </flux:button>
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
     * @returns {{start: function(string): void, stop: function(string): void}}
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
     * personalizables) por un modal propio, reutilizado en toda la app para
     * confirmar borrados. Dos formas de usarlo:
     *   - En un <form onsubmit="return window.appConfirmDialog.open(event, this, '...')">
     *     -- al aceptar, deshabilita el botón, muestra un spinner de
     *     "Procesando..." y envía el formulario de verdad.
     *   - window.appConfirmDialog.ask('...').then(ok => ...) -- para flujos
     *     con fetch() que ya manejan su propio estado de carga.
     *
     * @returns {{open: function, ask: function, cancel: function, accept: function}}
     */
    window.appConfirmDialog = (function () {
        let pendingForm = null;
        let pendingResolve = null;

        function reset() {
            document.getElementById('app-confirm-dialog-accept-label').classList.remove('hidden');
            document.getElementById('app-confirm-dialog-accept-spinner').classList.add('hidden');
            document.getElementById('app-confirm-dialog-accept-btn').disabled = false;
            document.getElementById('app-confirm-dialog-cancel-btn').disabled = false;
        }

        function show(message) {
            document.getElementById('app-confirm-dialog-message').textContent = message || '';
            reset();
            window.appModalProcessing.stop('#app-confirm-dialog');

            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#app-confirm-dialog');
            }
        }

        function open(event, form, message) {
            event.preventDefault();
            pendingForm = form;
            pendingResolve = null;
            show(message);

            return false;
        }

        function ask(message) {
            return new Promise((resolve) => {
                pendingForm = null;
                pendingResolve = resolve;
                show(message);
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
                document.getElementById('app-confirm-dialog-accept-label').classList.add('hidden');
                document.getElementById('app-confirm-dialog-accept-spinner').classList.remove('hidden');
                document.getElementById('app-confirm-dialog-accept-btn').disabled = true;
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

        return { open, ask, cancel, accept };
    })();
</script>
