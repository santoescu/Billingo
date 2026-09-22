{{--
    Helpers para pintar la respuesta de GetDocumentInfo de la DIAN (emisor, receptor, tenedor
    actual, eventos, validaciones) como tarjetas -- se originó en documents/create.blade.php
    (validateUuid(), al validar un UUID de referencia para una nota) y ahora también lo usan la
    casilla de "Tracking" del listado y el detalle de documentos emitidos/recibidos (ver
    partials/radian-events.blade.php) para mostrar los eventos RADIAN con el mismo diseño.
    Se expone en window.* porque cada página que lo incluye llama renderDianInfo() desde su
    propio script, en un scope distinto a este.
--}}
@push('scripts')
    <script>
        window.dianEscapeHtml = function (value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        /**
         * Normaliza un valor del XML de la DIAN a array siempre. xmlToArray() en el backend solo
         * agrupa en array cuando hay más de una ocurrencia del mismo elemento -- si solo hay uno
         * (p. ej. un único evento), llega como objeto suelto.
         * @param {*} value
         * @returns {Array}
         */
        window.dianAsArray = function (value) {
            if (value === undefined || value === null || value === '') {
                return [];
            }
            return Array.isArray(value) ? value : [value];
        };

        /**
         * Arma el numeral completo de un documento DIAN. El "Folio" a veces ya trae el prefijo
         * pegado (p. ej. Serie "SETP" + Folio "SETP990000000") y a veces no (Serie "FEL" + Folio
         * "227106") -- evita duplicar el prefijo cuando el Folio ya lo trae.
         * @param {object} numeroDocumento
         * @returns {string}
         */
        window.dianFullNumeral = function (numeroDocumento) {
            const serie = (numeroDocumento.Serie || '').trim();
            const folio = (numeroDocumento.Folio || '').trim();
            if (! folio) {
                return '';
            }
            if (! serie || folio.startsWith(serie)) {
                return folio;
            }
            return serie + folio;
        };

        (function () {
            function dianBadge(text, tone) {
                const tones = {
                    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                    red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                    zinc: 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300',
                };
                return '<span class="inline-flex items-center gap-x-1 py-1 px-2 rounded-full text-xs font-medium ' + (tones[tone] || tones.zinc) + '">' + window.dianEscapeHtml(text) + '</span>';
            }

            function dianEntity(entity) {
                if (! entity) {
                    return '<span class="text-zinc-400">—</span>';
                }
                return '<p class="font-medium text-zinc-800 dark:text-white">' + window.dianEscapeHtml(entity.Nombre) + '</p>'
                    + '<p class="text-xs text-zinc-500 dark:text-zinc-400">'
                        + (entity.TipoDoc ? window.dianEscapeHtml(entity.TipoDoc) + ' ' : '')
                        + window.dianEscapeHtml(entity.NumeroDoc)
                    + '</p>';
            }

            /**
             * Pinta la lista de validaciones de un documento DIAN, ocultando "Documento validado
             * por la DIAN": no aporta nada nuevo, si el evento/documento aparece aquí es porque
             * ya está validado, esa línea solo repite algo implícito.
             * @param {object} validacionesDoc
             * @returns {string}
             */
            function dianValidaciones(validacionesDoc) {
                const items = window.dianAsArray(validacionesDoc && validacionesDoc.ValidacionDoc)
                    .filter((item) => item.Nombre !== 'Documento validado por la DIAN');
                if (items.length === 0) {
                    return '';
                }

                return '<div class="mt-2 space-y-1.5">'
                    + items.map((item) => {
                        const isValid = String(item.IsValida).toLowerCase() === 'true';
                        const icon = isValid
                            ? '<svg class="shrink-0 size-3.5 mt-0.5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
                            : '<svg class="shrink-0 size-3.5 mt-0.5 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                        return '<div class="flex items-start gap-x-2 text-xs">'
                            + icon
                            + '<span class="text-zinc-600 dark:text-zinc-400">'
                                + '<span class="font-medium text-zinc-700 dark:text-zinc-300">' + window.dianEscapeHtml(item.Nombre) + '</span>'
                                + (item.MensajeError ? ': ' + window.dianEscapeHtml(item.MensajeError) : '')
                            + '</span>'
                        + '</div>';
                    }).join('')
                    + '</div>';
            }

            function dianEstados(estado) {
                const items = window.dianAsArray(estado && estado.KeyValueOfintstring);
                if (items.length === 0) {
                    return '';
                }

                return '<div class="flex flex-wrap gap-2 mb-4">'
                    + items.map((item) => dianBadge(item.Key + ' · ' + item.Value, 'zinc')).join('')
                    + '</div>';
            }

            function dianIsTituloValor(estado) {
                return window.dianAsArray(estado && estado.KeyValueOfintstring)
                    .some((item) => String(item.Value).toLowerCase().includes('título valor') || String(item.Value).toLowerCase().includes('titulo valor'));
            }

            function dianTituloValorWarning(estado) {
                if (! dianIsTituloValor(estado)) {
                    return '';
                }

                return '<div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40">'
                    + '<p class="text-sm text-amber-800 dark:text-amber-400">'
                        + '{{ __('This invoice is registered as a negotiable instrument (Título Valor). A note referencing it directly can no longer be issued — it must be issued without reference instead, using a period.') }}'
                    + '</p>'
                    + '<button type="button" id="doc-use-without-reference-btn" class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-400 underline hover:no-underline">'
                        + '{{ __('Switch to "without reference"') }}'
                    + '</button>'
                + '</div>';
            }

            function dianEvento(evento) {
                const numeroDocumento = evento.NumeroDocumento || {};

                return '<div class="relative ps-6 pb-5 last:pb-0 border-s-2 border-zinc-200 dark:border-white/10 last:border-transparent">'
                    + '<span class="absolute -start-[7px] top-0.5 size-3 rounded-full bg-accent"></span>'
                    + '<p class="text-sm font-semibold text-zinc-800 dark:text-white">' + window.dianEscapeHtml(evento.Descripcion) + '</p>'
                    + '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">'
                        + window.dianEscapeHtml(numeroDocumento.Folio)
                        + (numeroDocumento.FechaEmision ? ' · ' + window.dianEscapeHtml(numeroDocumento.FechaEmision) : '')
                    + '</p>'
                    + (evento.Emisor ? '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('By') }} ' + window.dianEscapeHtml(evento.Emisor.Nombre) + '</p>' : '')
                    + dianValidaciones(evento.ValidacionesDoc)
                + '</div>';
            }

            function renderDianDocument(doc, isReferenceValidation) {
                const emisor = doc.Emisor || {};
                const receptor = doc.Receptor || {};
                const numeroDocumento = doc.NumeroDocumento || {};
                const eventos = window.dianAsArray(doc.Eventos && doc.Eventos.Evento);

                let html = '';

                if (isReferenceValidation) {
                    html += '<div class="flex items-start justify-between gap-4 pb-4 mb-4 border-b border-zinc-200 dark:border-white/10">'
                        + '<div>'
                            + '<p class="font-semibold text-zinc-800 dark:text-white">' + window.dianEscapeHtml(doc.DocumentTypeName) + ' (' + window.dianEscapeHtml(doc.DocumentTypeId) + ')</p>'
                            + '<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">'
                                + window.dianEscapeHtml(window.dianFullNumeral(numeroDocumento))
                                + (numeroDocumento.FechaEmision ? ' · ' + window.dianEscapeHtml(numeroDocumento.FechaEmision) : '')
                            + '</p>'
                        + '</div>'
                        + dianBadge('{{ __('Found by the DIAN') }}', 'green')
                    + '</div>';
                }

                html += dianEstados(doc.Estado);
                if (isReferenceValidation) {
                    html += dianTituloValorWarning(doc.Estado);
                }

                html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">'
                    + '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Issuer') }}</p>'
                        + dianEntity(emisor)
                    + '</div>'
                    + '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Recipient') }}</p>'
                        + dianEntity(receptor)
                    + '</div>'
                + '</div>';

                if (doc.LegitimoTenedor && doc.LegitimoTenedor.Nombre) {
                    html += '<div class="p-3 rounded-lg bg-zinc-50 dark:bg-white/5 mb-4">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Current holder') }}</p>'
                        + '<p class="font-medium text-zinc-800 dark:text-white">' + window.dianEscapeHtml(doc.LegitimoTenedor.Nombre) + '</p>'
                        + (doc.LegitimoTenedor.FechaInscripcionComoTituloValor
                            ? '<p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Registered as a negotiable instrument on') }} ' + window.dianEscapeHtml(doc.LegitimoTenedor.FechaInscripcionComoTituloValor) + '</p>'
                            : '')
                    + '</div>';
                }

                if (isReferenceValidation) {
                    html += '<div class="mb-4">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">UUID</p>'
                        + '<p class="text-xs font-mono break-all text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-white/5 rounded-lg p-2">' + window.dianEscapeHtml(doc.UUID) + '</p>'
                    + '</div>';
                }

                if (eventos.length > 0) {
                    html += '<div class="mb-4">'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Events') }}</p>'
                        + eventos.map(dianEvento).join('')
                    + '</div>';
                }

                const validacionesHtml = dianValidaciones(doc.ValidacionesDoc);
                if (validacionesHtml) {
                    html += '<div>'
                        + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Validations') }}</p>'
                        + validacionesHtml
                    + '</div>';
                }

                return html;
            }

            /**
             * isReferenceValidation (default true) solo tiene sentido cuando se está por
             * emitir una nota referenciando este documento (documents/create.blade.php) -- el
             * aviso ofrece cambiar a "sin referencia", algo que no existe fuera de ese formulario
             * (ver seguimiento en documents/index.blade.php y partials/radian-events.blade.php,
             * que pasan false).
             * @param {object} info
             * @param {boolean} [isReferenceValidation]
             * @returns {string}
             */
            window.renderDianInfo = function (info, isReferenceValidation) {
                const documents = window.dianAsArray(info && info.documents);

                if (documents.length === 0) {
                    return '<p class="text-zinc-500 dark:text-zinc-400">{{ __('The DIAN did not return information for this document.') }}</p>';
                }

                const showWarning = isReferenceValidation !== false;

                return documents.map((doc) => renderDianDocument(doc, showWarning)).join('<hr class="my-4 border-zinc-200 dark:border-white/10">');
            };
        })();
    </script>
@endpush
