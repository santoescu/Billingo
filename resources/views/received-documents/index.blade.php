<x-layouts.app :title="__('Received documents')">
    @include('partials.tittle', [
        'title' => __('Received documents'),
        'subheading' => __('Invoices and notes your providers sent you.'),
    ])

    @if (session('received-documents-errors'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach (session('received-documents-errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" id="received-documents-search" placeholder="{{ __('Search') }}" autocomplete="off" />
                        </div>

                        <flux:button variant="primary" icon="arrow-up-tray" data-hs-overlay="#upload-received-document-modal">
                            {{ __('Upload document') }}
                        </flux:button>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="receivedDocumentsTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Issue date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Provider') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Document') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Total') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($documentos as $documento)
                                    <tr>
                                        <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ optional($documento->issue_date)->format('Y-m-d') ?? '—' }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-800 dark:text-neutral-200">
                                            {{ $documento->proveedor->name ?? data_get($documento->payload, 'accounting_supplier_party.razon_social', '—') }}
                                            <span class="block text-xs text-gray-400 dark:text-neutral-500">{{ data_get($documento->payload, 'accounting_supplier_party.identificacion') }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $documento->numeral }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $documento->total_formatted }}</td>
                                        <td class="px-6 py-3">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->status_badge_classes }}">{{ $documento->status_label }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-end">
                                            <a href="{{ route('received-documents.show', $documento->_id) }}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Received documents')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="upload-received-document-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form id="upload-received-document-form" method="POST" action="{{ route('received-documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Upload document') }}</h3>
                        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#upload-received-document-modal">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-4 space-y-3">
                        <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('Upload the XML/UBL file that the provider sent you, or the .zip they usually send it in (with the PDF included). You can select more than one at a time.') }}</p>

                        <div id="received-document-file-upload" data-hs-file-upload='{
                                "url": "#",
                                "autoProcessQueue": false,
                                "autoHideTrigger": false
                            }'>
                            <template data-hs-file-upload-preview>
                                <div class="p-3 bg-white border border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700">
                                    <div class="mb-1 flex justify-between items-center">
                                        <div class="flex items-center gap-x-3">
                                            <span class="size-8 shrink-0 flex justify-center items-center bg-gray-100 text-gray-500 rounded-lg dark:bg-neutral-700 dark:text-neutral-400" data-hs-file-upload-file-icon>
                                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                                    <span data-hs-file-upload-file-name></span>.<span data-hs-file-upload-file-ext></span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-neutral-400" data-hs-file-upload-file-size></p>
                                            </div>
                                        </div>
                                        <button type="button" class="shrink-0 text-gray-400 hover:text-red-600 focus:outline-hidden dark:hover:text-red-400" data-hs-file-upload-remove title="{{ __('Remove') }}">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-x-3 whitespace-nowrap">
                                        <div class="flex w-full h-2 bg-gray-100 rounded-full overflow-hidden dark:bg-neutral-700" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-hs-file-upload-progress-bar>
                                            <div class="flex flex-col justify-center rounded-full overflow-hidden bg-accent text-xs text-white text-center whitespace-nowrap transition-all duration-500 hs-file-upload-complete:bg-green-500" style="width: 0" data-hs-file-upload-progress-bar-pane></div>
                                        </div>
                                        <div class="w-10 text-end">
                                            <span class="text-sm text-gray-800 dark:text-white">
                                                <span data-hs-file-upload-progress-bar-value>0</span>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="cursor-pointer h-20 flex items-center justify-center gap-2 border border-dashed border-gray-300 rounded-lg text-center dark:border-neutral-600" data-hs-file-upload-trigger>
                                <svg class="shrink-0 size-5 text-gray-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 13v8"/><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="m8 17 4-4 4 4"/></svg>
                                <p class="text-sm text-gray-600 dark:text-neutral-400">
                                    {{ __('Drop your file here or') }} <span class="font-semibold text-accent">{{ __('browse') }}</span>
                                </p>
                            </div>

                            <div class="mt-2 space-y-2 empty:mt-0" data-hs-file-upload-previews></div>
                        </div>

                        <input type="file" name="files[]" id="received-document-file-input" multiple accept=".xml,.zip" class="hidden">
                        <p id="received-document-upload-error" class="hidden text-xs text-red-600 dark:text-red-400"></p>
                    </div>
                    <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                        <flux:button type="button" variant="ghost" data-hs-overlay="#upload-received-document-modal">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Upload') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if (session('received-documents-errors'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.HSOverlay) {
                    HSOverlay.open('#upload-received-document-modal');
                }
            });
        </script>
    @endif

    <script>
        document.getElementById('received-documents-search')?.addEventListener('input', (event) => {
            const query = event.target.value.trim().toLowerCase();
            document.querySelectorAll('#receivedDocumentsTable tbody tr').forEach((row) => {
                row.classList.toggle('hidden', query !== '' && ! row.textContent.toLowerCase().includes(query));
            });
        });

        /**
         * Engancha el widget de arrastrar/soltar (Dropzone, vía Preline
         * HSFileUpload) con el <input type="file" multiple> real que se
         * manda en el submit -- mismo mecanismo que ya usa el certificado
         * digital (companies/create.blade.php), pero acumulando archivos en
         * vez de reemplazar el único que había, porque acá sí se permite
         * más de uno. autoProcessQueue está en false (no hay backend de
         * subida por partes acá), así que la barra de progreso se marca
         * "completa" apenas se agrega el archivo -- es solo la vista previa
         * con su nombre/tamaño, no una subida real en curso.
         */
        // Envuelto en DOMContentLoaded a propósito: el bundle de Vite carga app.js (que trae
        // Preline, de ahí sale window.HSFileUpload) como <script type="module">, que SIEMPRE se difiere hasta
        // que el documento termina de parsear -- si este bloque corriera de una al toque (como
        // el de la búsqueda de arriba, que no depende de Preline), "window.HSFileUpload" todavía
        // no existiría, el "if" de abajo se saltaría en silencio, y ni el input real se
        // sincronizaría con los archivos elegidos ni la barra de progreso se marcaría completa
        // (mismo motivo por el que companies/create.blade.php hace lo mismo con el certificado).
        document.addEventListener('DOMContentLoaded', function () {
            const uploadEl = document.getElementById('received-document-file-upload');
            const fileInput = document.getElementById('received-document-file-input');
            const errorMessage = document.getElementById('received-document-upload-error');
            const form = document.getElementById('upload-received-document-form');
            let selectedFiles = [];

            function syncFileInput() {
                const transfer = new DataTransfer();
                selectedFiles.forEach((file) => transfer.items.add(file));
                fileInput.files = transfer.files;
                errorMessage.classList.add('hidden');
            }

            function markPreviewComplete(file) {
                const previewElement = file.previewElement;
                if (! previewElement) {
                    return;
                }
                previewElement.classList.add('complete');
                previewElement.querySelector('[data-hs-file-upload-progress-bar]')?.setAttribute('aria-valuenow', '100');
                const pane = previewElement.querySelector('[data-hs-file-upload-progress-bar-pane]');
                if (pane) {
                    pane.style.width = '100%';
                }
                const value = previewElement.querySelector('[data-hs-file-upload-progress-bar-value]');
                if (value) {
                    value.textContent = '100';
                }
            }

            if (window.HSFileUpload) {
                HSFileUpload.autoInit();
            }

            const instance = window.HSFileUpload && HSFileUpload.getInstance(uploadEl, true);
            const dropzone = instance?.element?.dropzone;

            if (dropzone) {
                dropzone.on('addedfile', (file) => {
                    selectedFiles.push(file);
                    syncFileInput();
                    markPreviewComplete(file);
                });

                dropzone.on('removedfile', (file) => {
                    selectedFiles = selectedFiles.filter((selected) => selected !== file);
                    syncFileInput();
                });
            }

            form?.addEventListener('submit', (event) => {
                if (! selectedFiles.length) {
                    event.preventDefault();
                    errorMessage.textContent = '{{ __('Choose at least one file first.') }}';
                    errorMessage.classList.remove('hidden');
                }
            });
        });
    </script>
</x-layouts.app>
