{{--
    Tabla del registro de actividad, compartida entre companies/activity-log.blade.php
    (un 'owner' viendo su propia empresa) y admin/activity-log.blade.php (superadmin
    viendo todas). La columna "Empresa" solo aparece si viene $companyNames -- así se
    reusa la misma tabla sin duplicarla, la única diferencia real entre las dos
    pantallas.

    Requiere: $logs (colección de ActivityLog), $userNames (colección de User
    indexada por _id en string). Opcional: $companyNames (igual, indexada por _id)
    -- si viene, se agrega la columna "Empresa".
--}}
<div class="overflow-hidden">
    <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="activityLogTable">
        <thead class="bg-gray-50 dark:bg-neutral-700">
            <tr>
                @isset($companyNames)
                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                @endisset
                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('User') }}</th>
                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Action') }}</th>
                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Type') }}</th>
                <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Description') }}</th>
                <th scope="col" class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            @forelse ($logs as $log)
                <tr>
                    @isset($companyNames)
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $companyNames->get((string) $log->company_id)?->name ?? '—' }}</td>
                    @endisset
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $log->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $userNames->get((string) $log->user_id)?->name ?? __('System') }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $log->action_badge_classes }}">{{ $log->action_label }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $log->model_label }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $log->label }}</td>
                    <td class="px-4 py-3 text-end text-sm">
                        @if (! empty($log->changes))
                            <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View changes') }}" title="{{ __('View changes') }}" onclick="showActivityLogChanges(@json($log->changes), @json($log->action), @json($log->label))">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ isset($companyNames) ? 7 : 6 }}" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('activity')]) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="activity-log-changes-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="activity-log-changes-modal-label">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                <h3 id="activity-log-changes-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Changes') }}</h3>
                <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#activity-log-changes-modal">
                    <span class="sr-only">Close</span>
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4 max-h-96 overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                <div id="activity-log-changes-body" class="flex flex-col"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value === null || value === undefined ? '—' : String(value);
                return div.innerHTML;
            }

            /**
             * Muestra el detalle de una entrada del log en un modal: si fue
             * una edición, cada campo con su valor de antes (tachado, en
             * rojo) y de después (en verde); si fue creación/borrado, la
             * foto completa del registro en ese momento (un solo valor por
             * campo, sin antes/después).
             * @param {object} changes
             * @param {string} action
             * @param {string} label
             * @returns {void}
             */
            window.showActivityLogChanges = function (changes, action, label) {
                const body = document.getElementById('activity-log-changes-body');
                const title = document.getElementById('activity-log-changes-modal-label');
                title.textContent = label;
                body.innerHTML = '';

                Object.keys(changes || {}).forEach((field) => {
                    const value = changes[field];
                    const row = document.createElement('div');
                    row.className = 'flex flex-col gap-1 py-2 border-b border-gray-100 dark:border-neutral-700 last:border-0';

                    if (action === 'updated' && value && typeof value === 'object') {
                        row.innerHTML = `
                            <span class="text-xs font-medium text-gray-500 dark:text-neutral-400">${escapeHtml(field)}</span>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="text-red-600 line-through dark:text-red-400">${escapeHtml(value.from)}</span>
                                <span class="text-gray-400 dark:text-neutral-500">&rarr;</span>
                                <span class="text-green-600 dark:text-green-400">${escapeHtml(value.to)}</span>
                            </div>
                        `;
                    } else {
                        row.innerHTML = `
                            <span class="text-xs font-medium text-gray-500 dark:text-neutral-400">${escapeHtml(field)}</span>
                            <div class="text-sm text-gray-700 dark:text-neutral-300">${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : value)}</div>
                        `;
                    }

                    body.appendChild(row);
                });

                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#activity-log-changes-modal');
                }
            };
        })();
    </script>
@endpush
