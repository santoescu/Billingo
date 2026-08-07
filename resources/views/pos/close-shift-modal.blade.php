<!-- Modal: cerrar turno de caja (arqueo) -->
<div id="pos-close-shift-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="pos-close-shift-modal-label">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                <h3 id="pos-close-shift-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Close shift') }}</h3>
                <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#pos-close-shift-modal">
                    <span class="sr-only">Close</span>
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('pos.shifts.close', $shift->_id) }}" class="p-4 space-y-4">
                @csrf

                <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-white/5">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">{{ __('Opening balance') }}</span>
                        <span class="text-gray-800 dark:text-neutral-200">{{ '$' . number_format((float) $shift->opening_balance, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">{{ __('Expected cash so far') }}</span>
                        <span id="pos-close-expected" class="text-gray-800 dark:text-neutral-200">—</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1" for="pos-close-counted-display">{{ __('Counted cash') }}</label>
                    <div class="relative">
                        <input type="hidden" id="pos-close-counted-hidden" name="closing_balance" value="0">
                        <input type="text" inputmode="decimal" id="pos-close-counted-display" required
                            class="h-10 py-2 px-3 ps-6 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-sm shadow-xs focus:z-10 focus:outline-hidden focus:ring-2 focus:ring-accent" placeholder="0">
                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-2">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">$</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="filled" data-hs-overlay="#pos-close-shift-modal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Close shift') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        window.openPosCloseShiftModal = function () {
            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#pos-close-shift-modal');
            }

            const expectedEl = document.getElementById('pos-close-expected');
            expectedEl.textContent = '…';

            fetch('{{ route('pos.shifts.show', $shift->_id) }}', {
                headers: { 'Accept': 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    const expected = Number(data.expected_balance ?? 0);
                    expectedEl.textContent = '$' + expected.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                })
                .catch(() => {
                    expectedEl.textContent = '—';
                });
        };

        function formatMoney(intPart, decPart, hasComma) {
            if (! intPart && ! hasComma) {
                return '';
            }
            const formattedInt = Number(intPart || '0').toLocaleString('es-CO');
            return hasComma ? `${formattedInt},${decPart}` : formattedInt;
        }

        function rawValue(intPart, decPart) {
            if (! intPart && ! decPart) {
                return '0';
            }
            return decPart ? `${intPart || '0'}.${decPart}` : (intPart || '0');
        }

        const hidden = document.getElementById('pos-close-counted-hidden');
        const display = document.getElementById('pos-close-counted-display');

        display.addEventListener('input', () => {
            const str = display.value.replace(/\./g, '');
            const commaIndex = str.indexOf(',');
            let intPart, decPart, hasComma;
            if (commaIndex === -1) {
                intPart = str.replace(/\D/g, '');
                decPart = '';
                hasComma = false;
            } else {
                intPart = str.slice(0, commaIndex).replace(/\D/g, '');
                decPart = str.slice(commaIndex + 1).replace(/\D/g, '').slice(0, 2);
                hasComma = true;
            }
            hidden.value = rawValue(intPart, decPart);
            display.value = formatMoney(intPart, decPart, hasComma);
        });
    })();
</script>
