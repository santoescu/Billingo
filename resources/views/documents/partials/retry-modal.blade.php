{{--
    Modal de resultado del botón "Validar" de documentos pendientes/rechazados
    (ver DocumentoEmitidoController::retry()) -- compartido entre
    documents/show.blade.php (un solo botón) y documents/index.blade.php
    (uno por fila), por eso el JS engancha por delegación de eventos sobre
    ".document-retry-btn" en vez de buscar un id puntual.
--}}
<div id="doc-retry-result-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="doc-retry-result-modal-label">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                <h3 id="doc-retry-result-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Validate') }}</h3>
                <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#doc-retry-result-modal">
                    <span class="sr-only">Close</span>
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <div class="flex gap-3">
                    <span id="doc-retry-result-icon" class="shrink-0 flex size-9 items-center justify-center rounded-full"></span>
                    <div id="doc-retry-result-message" class="text-sm text-gray-700 dark:text-neutral-300 w-full"></div>
                </div>
            </div>
            <div class="flex justify-end gap-3 p-4 pt-0">
                <flux:button type="button" id="doc-retry-result-close-btn" variant="primary" data-hs-overlay="#doc-retry-result-modal">{{ __('Understood') }}</flux:button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            /**
             * Dice si se pudo validar el documento o no, y por qué no si
             * falló, en vez de un alert() de navegador. Si la DIAN mandó
             * una lista de reglas de rechazo, se muestran como viñetas
             * debajo del mensaje (mismo patrón que
             * showDocumentIssueError() en documents/create.blade.php). En
             * éxito el botón "Entendido" recarga la página para mostrar el
             * estado ya actualizado (en vez de redirigir de una, para que
             * el usuario alcance a leer el mensaje).
             * @param {boolean} success
             * @param {string} message
             * @param {string[]} [rules]
             * @returns {void}
             */
            function showDocRetryResult(success, message, rules) {
                const icon = document.getElementById('doc-retry-result-icon');
                const label = document.getElementById('doc-retry-result-modal-label');

                icon.className = success
                    ? 'shrink-0 flex size-9 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
                    : 'shrink-0 flex size-9 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
                icon.innerHTML = success
                    ? '<svg class="shrink-0 size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
                    : '<svg class="shrink-0 size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

                label.textContent = success ? @json(__('Validated')) : @json(__('Could not validate'));

                const box = document.getElementById('doc-retry-result-message');
                box.innerHTML = '';

                const p = document.createElement('p');
                p.textContent = message;
                box.appendChild(p);

                if (rules && rules.length) {
                    const ul = document.createElement('ul');
                    ul.className = 'mt-2 list-disc list-inside space-y-1 text-xs text-gray-600 dark:text-neutral-400';
                    rules.forEach((rule) => {
                        const li = document.createElement('li');
                        li.textContent = rule;
                        ul.appendChild(li);
                    });
                    box.appendChild(ul);
                }

                const closeBtn = document.getElementById('doc-retry-result-close-btn');
                closeBtn.onclick = success ? () => window.location.reload() : null;

                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#doc-retry-result-modal');
                }
            }

            function initDocRetryButtons() {
                if (document.body.dataset.docRetryBound === 'true') {
                    return;
                }
                document.body.dataset.docRetryBound = 'true';

                document.addEventListener('click', async function (event) {
                    const button = event.target.closest('.document-retry-btn');
                    if (! button || button.disabled) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const response = await fetch(button.dataset.url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            const error = new Error(data.message || @json(__('Could not validate the document.')));
                            error.rules = data.reglas || [];
                            throw error;
                        }

                        showDocRetryResult(true, @json(__('The document was validated successfully.')));
                    } catch (error) {
                        showDocRetryResult(false, error.message || @json(__('Could not validate the document.')), error.rules);
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', initDocRetryButtons);
            document.addEventListener('livewire:navigated', initDocRetryButtons);
        })();
    </script>
@endpush
