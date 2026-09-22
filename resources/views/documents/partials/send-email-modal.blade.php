{{--
    Modal para mandar un documento por correo (ver DocumentoEmitidoController::sendEmail()) --
    compartido entre documents/index.blade.php (uno por fila) y documents/show.blade.php (un solo
    botón), por eso el JS engancha por delegación de eventos sobre ".document-send-email-btn" en
    vez de buscar un id puntual -- mismo criterio que retry-modal.blade.php. El formulario no
    tiene una action fija: se la pone el botón que lo abrió (data-url), junto con el correo del
    cliente ya guardado (data-email), si tiene uno.

    El campo de destinatarios se ve como "chips" (un badge removible por cada correo), pero por
    adentro sigue siendo la misma lista separada por coma que ya espera
    DocumentoEmitidoController::sendEmail() -- el input visible de texto libre solo sirve para
    escribir uno nuevo, el que de verdad se manda en el submit es el <input type="hidden">, armado
    por JS cada vez que se agrega/quita un chip.
--}}
<div id="doc-send-email-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
        <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <form method="POST" id="doc-send-email-form">
                @csrf
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Send by email') }} <span id="doc-send-email-modal-numeral" class="text-zinc-500 dark:text-neutral-400"></span></h3>
                    <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#doc-send-email-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('The PDF and the signed XML will be attached automatically in a single .zip file.') }}</p>

                    <div>
                        <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2" for="doc-send-email-chip-input">{{ __('Recipient email') }}</label>
                        <div id="doc-send-email-chips" class="flex flex-wrap items-center gap-1.5 w-full min-h-10 bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 rounded-lg text-base sm:text-sm shadow-xs py-1.5 px-2 focus-within:ring-2 focus-within:ring-accent">
                            <input type="text" id="doc-send-email-chip-input" autocomplete="off" class="flex-1 min-w-32 border-0 bg-transparent p-1 text-zinc-700 dark:text-zinc-300 focus:outline-hidden focus:ring-0" placeholder="{{ __('Type an email and press Enter') }}">
                        </div>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Press Enter or comma to add each address.') }}</p>
                        <input type="hidden" name="email" id="doc-send-email-input" required>
                    </div>
                </div>
                <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                    <flux:button type="button" variant="ghost" data-hs-overlay="#doc-send-email-modal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Send') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            let chipEmails = [];

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                })[char]);
            }

            /**
             * Sincroniza el input oculto (lo único que de verdad manda el formulario) con la
             * lista de chips en memoria -- se llama después de cualquier cambio (agregar/quitar
             * un chip), nunca al revés.
             * @returns {void}
             */
            function syncHiddenInput() {
                document.getElementById('doc-send-email-input').value = chipEmails.join(',');
            }

            /**
             * Vuelve a pintar los chips ya agregados delante del input de texto -- se reconstruye
             * entero en vez de solo agregar/quitar un nodo, más simple dado que la lista siempre
             * es chica (unos pocos destinatarios).
             * @returns {void}
             */
            function renderChips() {
                const container = document.getElementById('doc-send-email-chips');
                const textInput = document.getElementById('doc-send-email-chip-input');

                container.querySelectorAll('[data-chip]').forEach((chip) => chip.remove());

                chipEmails.forEach((email, index) => {
                    const chip = document.createElement('span');
                    chip.dataset.chip = 'true';
                    chip.className = 'inline-flex items-center gap-1 rounded-md bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-2 py-1 text-xs font-medium';
                    chip.innerHTML = `${escapeHtml(email)}<button type="button" class="hover:opacity-70" data-index="${index}" aria-label="{{ __('Remove') }}">
                        <svg class="size-3 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>`;
                    container.insertBefore(chip, textInput);
                });

                syncHiddenInput();
            }

            /**
             * Agrega el texto que el usuario escribió como chip nuevo, si parece un correo válido
             * y no está repetido -- limpia el input de texto para que pueda seguir escribiendo el
             * siguiente. Un correo con formato inválido se deja tal cual en el input (no se
             * limpia) para que el usuario lo corrija, en vez de perderlo en silencio.
             * @returns {void}
             */
            function commitChipInput() {
                const textInput = document.getElementById('doc-send-email-chip-input');
                const email = textInput.value.trim().replace(/,+$/, '');

                if (! email) return;

                if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    return;
                }

                if (! chipEmails.includes(email)) {
                    chipEmails.push(email);
                    renderChips();
                }

                textInput.value = '';
            }

            /**
             * Todo por delegación sobre "document" (nunca referencias directas a los nodos del
             * modal, ni una guardia de "ya se enganchó") -- este partial se incluye en páginas
             * que navegan por Livewire (wire:navigate), que reemplaza el HTML del modal en cada
             * navegación pero nunca "document" en sí. Guardar una referencia al input o poner
             * una guardia habría dejado los listeners apuntando a nodos viejos (o directamente
             * sin re-enganchar) después de la primera visita a la página en la sesión.
             */
            function initDocSendEmailButtons() {
                // Guardia válida acá (a diferencia del error anterior en otro módulo de esta
                // misma app): TODOS los listeners de abajo están delegados sobre "document", que
                // nunca se reemplaza entre navegaciones -- bindearlos de nuevo en cada
                // navegación los iría acumulando (cada Enter dispararía el commit N veces).
                if (document.body.dataset.docSendEmailBound === 'true') return;
                document.body.dataset.docSendEmailBound = 'true';

                document.addEventListener('keydown', function (event) {
                    if (event.target.id !== 'doc-send-email-chip-input') return;

                    if (event.key === 'Enter' || event.key === ',') {
                        event.preventDefault();
                        commitChipInput();
                    } else if (event.key === 'Backspace' && event.target.value === '' && chipEmails.length) {
                        chipEmails.pop();
                        renderChips();
                    }
                });

                // "focusout" en vez de "blur": blur no burbujea, así que delegarlo sobre
                // "document" nunca lo vería disparar.
                document.addEventListener('focusout', function (event) {
                    if (event.target.id === 'doc-send-email-chip-input') {
                        commitChipInput();
                    }
                });

                document.addEventListener('click', function (event) {
                    const removeBtn = event.target.closest('#doc-send-email-chips button[data-index]');
                    if (removeBtn) {
                        chipEmails.splice(Number(removeBtn.dataset.index), 1);
                        renderChips();
                        return;
                    }

                    const openBtn = event.target.closest('.document-send-email-btn');
                    if (openBtn) {
                        document.getElementById('doc-send-email-form').action = openBtn.dataset.url;
                        document.getElementById('doc-send-email-modal-numeral').textContent = openBtn.dataset.numeral ?? '';
                        chipEmails = (openBtn.dataset.email || '')
                            .split(',')
                            .map((email) => email.trim())
                            .filter(Boolean);
                        document.getElementById('doc-send-email-chip-input').value = '';
                        renderChips();

                        if (window.HSOverlay) {
                            HSOverlay.autoInit();
                            HSOverlay.open('#doc-send-email-modal');
                        }
                    }
                });

                /**
                 * Manda el formulario por fetch() en vez de dejar que el navegador navegue --
                 * así ni la tabla de documentos ni la página completa se recargan al mandar un
                 * correo. El aviso de éxito/error sale por el evento "toast" (ver
                 * components/toast.blade.php), no por la sesión flash de siempre (esa solo se
                 * pinta en el próximo render de página completo, que acá nunca llega a pasar).
                 * @param {SubmitEvent} event
                 * @returns {Promise<void>}
                 */
                async function submitDocSendEmailForm(event) {
                    event.preventDefault();
                    commitChipInput();

                    // El input real es "type=hidden" -- el navegador nunca lo valida por su
                    // cuenta (los inputs ocultos quedan afuera de la validación de formularios
                    // del HTML), así que sin esto un envío sin ningún chip agregado llegaría
                    // vacío al servidor en vez de avisarle algo al usuario de una.
                    if (! chipEmails.length) {
                        document.getElementById('doc-send-email-chip-input').focus();
                        return;
                    }

                    const form = document.getElementById('doc-send-email-form');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: new FormData(form),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            throw new Error(data.message || @json(__('Could not send the document.')));
                        }

                        // Sin refrescar la tabla a propósito -- el seguimiento (columna
                        // "Tracking") se consulta fresco de la base recién cuando se abre ese
                        // modal (ver DocumentoEmitidoController::emailLogs()), no hace falta
                        // recargar nada más acá.
                        if (window.HSOverlay) HSOverlay.close('#doc-send-email-modal');
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: data.message } }));
                    } catch (error) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: error.message } }));
                    } finally {
                        if (submitBtn) submitBtn.disabled = false;
                    }
                }

                document.addEventListener('submit', function (event) {
                    if (event.target.id === 'doc-send-email-form') {
                        submitDocSendEmailForm(event);
                    }
                });
            }

            initDocSendEmailButtons();
        })();
    </script>
@endpush
