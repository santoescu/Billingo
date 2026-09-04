<script>
    /**
     * Inicializa (o reinicializa) una DataTable con paginación propia
     * (estilo Preline). "livewire:navigated" se dispara tanto en la carga
     * inicial de la página como en cada navegación SPA -- sin destruir la
     * instancia previa, una vista que escucha DOMContentLoaded Y
     * livewire:navigated (para que la tabla se reconstruya al volver por
     * SPA) termina llamando esto dos veces en la primera carga, y
     * DataTables truena con "Cannot reinitialise DataTable". Por la misma
     * razón, el bind de búsqueda usa un evento con namespace + .off() antes
     * de .on(): si esta función se llama otra vez sobre el mismo input, no
     * se acumulan binds duplicados apuntando cada uno a una instancia
     * distinta de la tabla.
     *
     * "emptyTable" es el mensaje para cuando la tabla arranca sin filas
     * (distinto de "zeroRecords", que es para una búsqueda sin resultados).
     * Por eso el placeholder de "no hay nada todavía" NO debe venir como
     * una fila real en el HTML (un <tr><td colspan="n"> cuenta como fila
     * de datos con una sola columna real, y DataTables tira "Requested
     * unknown parameter '1' for row 0" al intentar ordenar/buscar sobre
     * las columnas que le faltan) -- el <tbody> debe quedar vacío y dejar
     * que DataTables muestre este mensaje por su cuenta.
     *
     * @param {string} selector Selector de la tabla.
     * @param {string} searchSelector Selector del input de búsqueda.
     * @param {object} [options={}] pageLength, zeroRecords, emptyTable, columnDefs.
     * @returns {object} Instancia de DataTable.
     */
    window.initWorkflowDataTable = function(selector, searchSelector, options = {}) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }

        // DataTables mete este texto tal cual dentro del <td> que genera
        // (colspan automático a todas las columnas) -- se envuelve en un
        // div con el mismo estilo que usan los "no hay nada todavía"
        // armados a mano en el resto de la app (centrado + gris, visible
        // en claro y oscuro), para que quede igual sin repetir esas clases
        // en cada vista que llama a initWorkflowDataTable().
        const emptyStateHtml = (text) => `<div class="px-4 py-6 text-center text-sm text-neutral-400">${text}</div>`;
        const zeroRecordsText = options.zeroRecords || "{{ __('No matching records found') }}";

        const table = $(selector).DataTable({
            dom: 't<"workflow-datatable-footer flex flex-col gap-2 py-3 px-4 sm:flex-row sm:items-center sm:justify-between"<"workflow-datatable-info-wrap"><"workflow-datatable-pagination">>',
            pageLength: options.pageLength || 50,
            language: {
                zeroRecords: emptyStateHtml(zeroRecordsText),
                emptyTable: emptyStateHtml(options.emptyTable || zeroRecordsText),
            },
            columnDefs: options.columnDefs || [],
            drawCallback: function(settings) {
                renderWorkflowDataTablePagination(this.api(), settings);
            },
        });

        $(searchSelector).off('keyup.workflowDataTable').on('keyup.workflowDataTable', function () {
            table.search(this.value).draw();
        });

        return table;
    };

    function renderWorkflowDataTablePagination(table, settings) {
        const info = table.page.info();
        const footer = $(settings.nTableWrapper).find('.workflow-datatable-footer');
        const container = footer.find('.workflow-datatable-pagination');

        if (!container.length) return;

        const infoContainer = footer.find('.workflow-datatable-info-wrap');

        infoContainer.html(`
            <div class="workflow-datatable-info text-sm text-gray-500 dark:text-neutral-400">
                ${workflowPaginationInfo(info)}
            </div>
        `);

        if (info.pages <= 1) {
            container.empty();
            return;
        }

        const currentPage = info.page;
        const lastPage = info.pages - 1;
        const pages = workflowPaginationPages(currentPage, lastPage);

        const previousDisabled = currentPage === 0 ? 'disabled' : '';
        const nextDisabled = currentPage === lastPage ? 'disabled' : '';
        const pageButtons = pages.map((page) => {
            if (page === 'ellipsis') {
                return '<span class="min-h-[38px] min-w-[38px] flex justify-center items-center px-3 text-sm text-gray-500 dark:text-neutral-400">...</span>';
            }

            const active = page === currentPage;
            const classes = active
                ? 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-white'
                : 'text-gray-800 hover:bg-gray-100 focus:bg-gray-100 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700';

            return `
                <button type="button"
                        class="workflow-page-button min-h-[38px] min-w-[38px] flex justify-center items-center py-2 px-3 text-sm rounded-lg focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none ${classes}"
                        data-page="${page}"
                        ${active ? 'aria-current="page"' : ''}>
                    ${page + 1}
                </button>
            `;
        }).join('');

        container.html(`
            <nav class="flex items-center gap-x-1" aria-label="Pagination">
                <button type="button"
                        class="workflow-page-previous min-h-[38px] min-w-[38px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700"
                        aria-label="Previous"
                        ${previousDisabled}>
                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>{{ __('Previous') }}</span>
                </button>
                <div class="flex items-center gap-x-1">
                    ${pageButtons}
                </div>
                <button type="button"
                        class="workflow-page-next min-h-[38px] min-w-[38px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700"
                        aria-label="Next"
                        ${nextDisabled}>
                    <span>{{ __('Next') }}</span>
                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </nav>
        `);

        container.find('.workflow-page-previous').on('click', () => table.page('previous').draw('page'));
        container.find('.workflow-page-next').on('click', () => table.page('next').draw('page'));
        container.find('.workflow-page-button').on('click', function() {
            table.page(Number(this.dataset.page)).draw('page');
        });
    }

    function workflowPaginationPages(currentPage, lastPage) {
        if (lastPage <= 4) {
            return Array.from({ length: lastPage + 1 }, (_, index) => index);
        }

        const pages = [0];
        const start = Math.max(1, currentPage - 1);
        const end = Math.min(lastPage - 1, currentPage + 1);

        if (start > 1) pages.push('ellipsis');

        for (let page = start; page <= end; page++) {
            pages.push(page);
        }

        if (end < lastPage - 1) pages.push('ellipsis');

        pages.push(lastPage);

        return pages;
    }

    function workflowPaginationInfo(info) {
        if (info.recordsDisplay === 0) {
            return "{{ __('Showing 0 records') }}";
        }

        const start = info.start + 1;
        const end = info.end;
        const filteredText = info.recordsDisplay === info.recordsTotal
            ? ''
            : ` ({{ __('filtered from :total total') }}`.replace(':total', info.recordsTotal) + ')';

        return "{{ __('Showing :start to :end of :total records') }}"
            .replace(':start', start)
            .replace(':end', end)
            .replace(':total', info.recordsDisplay) + filteredText;
    }
</script>
