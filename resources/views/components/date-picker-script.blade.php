<script>
    (function () {
        function setSelectValue(el, value) {
            const instance = window.HSSelect && HSSelect.getInstance(el);
            if (instance) {
                instance.setValue(value ?? '');
            } else {
                el.value = value ?? '';
            }
        }

        function toIsoDate(date) {
            const y = date.getFullYear();
            const m = (date.getMonth() + 1).toString().padStart(2, '0');
            const d = date.getDate().toString().padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function initDatePickers() {
            document.querySelectorAll('[data-datepicker]').forEach((root) => {
                if (root.dataset.datepickerInitialized) {
                    return;
                }
                root.dataset.datepickerInitialized = 'true';

                const trigger = root.querySelector('[data-datepicker-trigger]');
                const hidden = root.querySelector('[data-datepicker-hidden]');
                const panel = root.querySelector('[data-datepicker-panel]');
                const daysContainer = panel.querySelector('[data-datepicker-days]');
                const monthSelect = panel.querySelector('[data-datepicker-month]');
                const yearSelect = panel.querySelector('[data-datepicker-year]');
                const prevBtn = panel.querySelector('[data-datepicker-prev]');
                const nextBtn = panel.querySelector('[data-datepicker-next]');
                const todayBtn = panel.querySelector('[data-datepicker-today]');

                let selectedDate = hidden.value ? new Date(hidden.value + 'T00:00:00') : null;
                const today = new Date();
                let viewYear = selectedDate ? selectedDate.getFullYear() : today.getFullYear();
                let viewMonth = selectedDate ? selectedDate.getMonth() : today.getMonth();

                function formatDisplay(date) {
                    return toIsoDate(date);
                }

                function renderCalendar() {
                    setSelectValue(monthSelect, String(viewMonth));
                    setSelectValue(yearSelect, String(viewYear));

                    daysContainer.innerHTML = '';

                    const firstOfMonth = new Date(viewYear, viewMonth, 1);
                    const startOffset = (firstOfMonth.getDay() + 6) % 7;
                    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
                    const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();
                    const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

                    for (let i = 0; i < totalCells; i++) {
                        const dayNumber = i - startOffset + 1;
                        let cellDate;
                        let inCurrentMonth = true;

                        if (dayNumber < 1) {
                            cellDate = new Date(viewYear, viewMonth - 1, daysInPrevMonth + dayNumber);
                            inCurrentMonth = false;
                        } else if (dayNumber > daysInMonth) {
                            cellDate = new Date(viewYear, viewMonth + 1, dayNumber - daysInMonth);
                            inCurrentMonth = false;
                        } else {
                            cellDate = new Date(viewYear, viewMonth, dayNumber);
                        }

                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = cellDate.getDate();

                        const isSelected = selectedDate && toIsoDate(cellDate) === toIsoDate(selectedDate);

                        if (! inCurrentMonth) {
                            button.disabled = true;
                            button.className = 'm-px size-10 flex justify-center items-center border-[1.5px] border-transparent text-sm text-zinc-400 dark:text-zinc-500 rounded-full disabled:opacity-50 disabled:pointer-events-none';
                        } else if (isSelected) {
                            button.className = 'm-px size-10 flex justify-center items-center bg-accent border-[1.5px] border-transparent text-sm font-medium text-white rounded-full focus:outline-hidden focus:bg-accent/90';
                        } else {
                            button.className = 'm-px size-10 flex justify-center items-center border-[1.5px] border-transparent text-sm text-zinc-700 dark:text-zinc-300 rounded-full hover:border-accent hover:text-accent focus:outline-hidden focus:border-accent focus:text-accent';
                        }

                        if (inCurrentMonth) {
                            button.addEventListener('click', () => {
                                selectedDate = cellDate;
                                hidden.value = toIsoDate(cellDate);
                                trigger.value = formatDisplay(cellDate);
                                closePanel();
                                renderCalendar();
                            });
                        }

                        const wrapper = document.createElement('div');
                        wrapper.appendChild(button);
                        daysContainer.appendChild(wrapper);
                    }
                }

                function openPanel() {
                    document.querySelectorAll('[data-datepicker-panel], [data-daterange-panel]').forEach((p) => {
                        if (p !== panel) p.classList.add('hidden');
                    });
                    panel.classList.remove('hidden');
                    renderCalendar();
                }

                function closePanel() {
                    panel.classList.add('hidden');
                }

                trigger.addEventListener('click', () => {
                    if (panel.classList.contains('hidden')) {
                        openPanel();
                    } else {
                        closePanel();
                    }
                });

                prevBtn.addEventListener('click', () => {
                    viewMonth--;
                    if (viewMonth < 0) {
                        viewMonth = 11;
                        viewYear--;
                    }
                    renderCalendar();
                });

                nextBtn.addEventListener('click', () => {
                    viewMonth++;
                    if (viewMonth > 11) {
                        viewMonth = 0;
                        viewYear++;
                    }
                    renderCalendar();
                });

                monthSelect.addEventListener('change', () => {
                    viewMonth = parseInt(monthSelect.value, 10);
                    renderCalendar();
                });

                yearSelect.addEventListener('change', () => {
                    viewYear = parseInt(yearSelect.value, 10);
                    renderCalendar();
                });

                todayBtn.addEventListener('click', () => {
                    const now = new Date();
                    selectedDate = now;
                    viewYear = now.getFullYear();
                    viewMonth = now.getMonth();
                    hidden.value = toIsoDate(now);
                    trigger.value = formatDisplay(now);
                    closePanel();
                    renderCalendar();
                });

                if (selectedDate) {
                    trigger.value = formatDisplay(selectedDate);
                }

                document.addEventListener('click', (event) => {
                    if (! root.contains(event.target)) {
                        closePanel();
                    }
                });

                // API pública para que código externo (p. ej. autocompletar
                // con datos de una consulta) actualice el valor sin abrir el
                // calendario -- asignar hidden.value directamente no alcanza
                // porque selectedDate/viewYear/viewMonth quedan en un cierre
                // interno que el calendario no vuelve a leer solo.
                root.datepickerSetValue = function (dateString) {
                    if (! dateString) {
                        selectedDate = null;
                        hidden.value = '';
                        trigger.value = '';
                        return;
                    }

                    selectedDate = new Date(dateString + 'T00:00:00');
                    viewYear = selectedDate.getFullYear();
                    viewMonth = selectedDate.getMonth();
                    hidden.value = toIsoDate(selectedDate);
                    trigger.value = formatDisplay(selectedDate);

                    if (! panel.classList.contains('hidden')) {
                        renderCalendar();
                    }
                };
            });
        }

        // Expuesto para que código externo (p. ej. al insertar dinámicamente
        // una fila con un date-picker nuevo, como en el bloque de pagos)
        // pueda inicializar los datepickers recién añadidos al DOM.
        window.initDatePickers = initDatePickers;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDatePickers);
        } else {
            initDatePickers();
        }

        document.addEventListener('livewire:navigated', initDatePickers);
    })();
</script>
