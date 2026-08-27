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

        function sameDay(a, b) {
            return !!a && !!b && toIsoDate(a) === toIsoDate(b);
        }

        function initDateRangePickers() {
            document.querySelectorAll('[data-daterange]').forEach((root) => {
                if (root.dataset.daterangeInitialized) {
                    return;
                }
                root.dataset.daterangeInitialized = 'true';

                const trigger = root.querySelector('[data-daterange-trigger]');
                const hiddenFrom = root.querySelector('[data-daterange-hidden-from]');
                const hiddenTo = root.querySelector('[data-daterange-hidden-to]');
                const panel = root.querySelector('[data-daterange-panel]');

                const monthSelects = { left: panel.querySelector('[data-daterange-month="left"]'), right: panel.querySelector('[data-daterange-month="right"]') };
                const yearSelects = { left: panel.querySelector('[data-daterange-year="left"]'), right: panel.querySelector('[data-daterange-year="right"]') };
                const daysContainers = { left: panel.querySelector('[data-daterange-days="left"]'), right: panel.querySelector('[data-daterange-days="right"]') };
                const prevBtn = panel.querySelector('[data-daterange-prev="left"]');
                const nextBtn = panel.querySelector('[data-daterange-next="right"]');
                const cancelBtn = panel.querySelector('[data-daterange-cancel]');
                const applyBtn = panel.querySelector('[data-daterange-apply]');
                const allowOpenEnd = root.hasAttribute('data-daterange-allow-open-end');
                const floating = root.hasAttribute('data-daterange-floating');

                let committedStart = hiddenFrom.value ? new Date(hiddenFrom.value + 'T00:00:00') : null;
                let committedEnd = hiddenTo.value ? new Date(hiddenTo.value + 'T00:00:00') : null;
                let draftStart = committedStart;
                let draftEnd = committedEnd;

                const today = new Date();
                let viewYear = (committedStart || today).getFullYear();
                let viewMonth = (committedStart || today).getMonth();

                function formatDisplay() {
                    if (! committedStart) {
                        return '';
                    }
                    if (! committedEnd) {
                        return toIsoDate(committedStart);
                    }
                    return toIsoDate(committedStart) + ' / ' + toIsoDate(committedEnd);
                }

                function renderSide(side) {
                    const offset = side === 'left' ? 0 : 1;
                    let month = viewMonth + offset;
                    let year = viewYear;
                    if (month > 11) {
                        month -= 12;
                        year += 1;
                    }

                    setSelectValue(monthSelects[side], String(month));
                    setSelectValue(yearSelects[side], String(year));

                    const daysContainer = daysContainers[side];
                    daysContainer.innerHTML = '';

                    const firstOfMonth = new Date(year, month, 1);
                    const startOffset = (firstOfMonth.getDay() + 6) % 7;
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const daysInPrevMonth = new Date(year, month, 0).getDate();
                    const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

                    for (let i = 0; i < totalCells; i++) {
                        const dayNumber = i - startOffset + 1;
                        let cellDate;
                        let inCurrentMonth = true;

                        if (dayNumber < 1) {
                            cellDate = new Date(year, month - 1, daysInPrevMonth + dayNumber);
                            inCurrentMonth = false;
                        } else if (dayNumber > daysInMonth) {
                            cellDate = new Date(year, month + 1, dayNumber - daysInMonth);
                            inCurrentMonth = false;
                        } else {
                            cellDate = new Date(year, month, dayNumber);
                        }

                        const wrapper = document.createElement('div');
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = cellDate.getDate();

                        const isStart = sameDay(cellDate, draftStart);
                        const isEnd = sameDay(cellDate, draftEnd);
                        const isInRange = inCurrentMonth && draftStart && draftEnd && cellDate > draftStart && cellDate < draftEnd;
                        const isRangeEndpoint = inCurrentMonth && draftStart && draftEnd && ! sameDay(draftStart, draftEnd) && (isStart || isEnd);

                        if (! inCurrentMonth) {
                            button.disabled = true;
                            button.className = 'm-px size-10 flex justify-center items-center border-[1.5px] border-transparent text-sm text-zinc-400 dark:text-zinc-500 rounded-full disabled:opacity-50 disabled:pointer-events-none';
                        } else if (isRangeEndpoint) {
                            wrapper.className = 'bg-zinc-100 dark:bg-white/10 ' + (isStart ? 'rounded-s-full' : 'rounded-e-full');
                            button.className = 'm-px size-10 flex justify-center items-center bg-accent border-[1.5px] border-transparent text-sm font-medium text-white rounded-full focus:outline-hidden focus:bg-accent/90';
                        } else if (isStart || isEnd) {
                            button.className = 'm-px size-10 flex justify-center items-center bg-accent border-[1.5px] border-transparent text-sm font-medium text-white rounded-full focus:outline-hidden focus:bg-accent/90';
                        } else if (isInRange) {
                            wrapper.className = 'bg-zinc-100 dark:bg-white/10 first:rounded-s-full last:rounded-e-full';
                            button.className = 'm-px size-10 flex justify-center items-center border-[1.5px] border-transparent text-sm text-zinc-700 dark:text-zinc-300 rounded-full hover:border-accent hover:text-accent focus:outline-hidden focus:border-accent focus:text-accent';
                        } else {
                            button.className = 'm-px size-10 flex justify-center items-center border-[1.5px] border-transparent text-sm text-zinc-700 dark:text-zinc-300 rounded-full hover:border-accent hover:text-accent focus:outline-hidden focus:border-accent focus:text-accent';
                        }

                        if (inCurrentMonth) {
                            button.addEventListener('click', (event) => {
                                event.stopPropagation();

                                if (! draftStart || (draftStart && draftEnd)) {
                                    draftStart = cellDate;
                                    draftEnd = null;
                                } else if (cellDate < draftStart) {
                                    draftStart = cellDate;
                                    draftEnd = null;
                                } else {
                                    draftEnd = cellDate;
                                }
                                renderBoth();
                            });
                        }

                        wrapper.appendChild(button);
                        daysContainer.appendChild(wrapper);
                    }
                }

                function renderBoth() {
                    renderSide('left');
                    renderSide('right');
                }

                function positionFloatingPanel() {
                    if (! floating) {
                        return;
                    }

                    const triggerRect = trigger.getBoundingClientRect();
                    const panelWidth = panel.offsetWidth;

                    let left = triggerRect.right - panelWidth;
                    left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));

                    let top = triggerRect.bottom + 8;
                    const panelHeight = panel.offsetHeight;
                    if (top + panelHeight > window.innerHeight - 8) {
                        top = Math.max(8, triggerRect.top - panelHeight - 8);
                    }

                    panel.style.position = 'fixed';
                    panel.style.margin = '0';
                    panel.style.left = `${left}px`;
                    panel.style.top = `${top}px`;
                    panel.style.zIndex = '100';
                }

                function openPanel() {
                    document.querySelectorAll('[data-daterange-panel], [data-datepicker-panel]').forEach((p) => {
                        if (p !== panel) p.classList.add('hidden');
                    });

                    // Un ancestro con "transform" (como el panel deslizante de admin) crea un
                    // containing block propio para los descendientes position:fixed -- el
                    // calendario ya no se posicionaría relativo al viewport sino a ese ancestro,
                    // quedando invisible/mal ubicado. Sacarlo al <body> lo libera de eso.
                    if (floating && panel.parentElement !== document.body) {
                        document.body.appendChild(panel);
                    }

                    draftStart = committedStart;
                    draftEnd = committedEnd;
                    viewYear = (draftStart || today).getFullYear();
                    viewMonth = (draftStart || today).getMonth();
                    panel.classList.remove('hidden');
                    renderBoth();
                    positionFloatingPanel();
                }

                function closePanel() {
                    panel.classList.add('hidden');
                }

                root.daterangeSetValue = function (from, to) {
                    committedStart = from ? new Date(from + 'T00:00:00') : null;
                    committedEnd = to ? new Date(to + 'T00:00:00') : null;
                    draftStart = committedStart;
                    draftEnd = committedEnd;
                    hiddenFrom.value = from ?? '';
                    hiddenTo.value = to ?? '';
                    trigger.value = formatDisplay();
                    viewYear = (committedStart || today).getFullYear();
                    viewMonth = (committedStart || today).getMonth();
                };

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
                    renderBoth();
                });

                nextBtn.addEventListener('click', () => {
                    viewMonth++;
                    if (viewMonth > 11) {
                        viewMonth = 0;
                        viewYear++;
                    }
                    renderBoth();
                });

                monthSelects.left.addEventListener('change', () => {
                    viewMonth = parseInt(monthSelects.left.value, 10);
                    renderBoth();
                });

                yearSelects.left.addEventListener('change', () => {
                    viewYear = parseInt(yearSelects.left.value, 10);
                    renderBoth();
                });

                monthSelects.right.addEventListener('change', () => {
                    let month = parseInt(monthSelects.right.value, 10) - 1;
                    let year = viewYear;
                    if (month < 0) {
                        month = 11;
                        year--;
                    }
                    viewMonth = month;
                    viewYear = year;
                    renderBoth();
                });

                yearSelects.right.addEventListener('change', () => {
                    const rightYear = parseInt(yearSelects.right.value, 10);
                    viewYear = viewMonth === 11 ? rightYear - 1 : rightYear;
                    renderBoth();
                });

                function applyDraft() {
                    if (! draftStart) {
                        return;
                    }
                    committedStart = draftStart;
                    committedEnd = draftEnd || (allowOpenEnd ? null : draftStart);
                    hiddenFrom.value = toIsoDate(committedStart);
                    hiddenTo.value = committedEnd ? toIsoDate(committedEnd) : '';
                    trigger.value = formatDisplay();
                }

                cancelBtn.addEventListener('click', () => {
                    draftStart = committedStart;
                    draftEnd = committedEnd;
                    closePanel();
                });

                applyBtn.addEventListener('click', () => {
                    applyDraft();
                    closePanel();
                });

                if (committedStart) {
                    trigger.value = formatDisplay();
                }

                document.addEventListener('click', (event) => {
                    if (! root.contains(event.target) && ! panel.contains(event.target) && ! panel.classList.contains('hidden')) {
                        if (draftStart && (draftEnd || allowOpenEnd)) {
                            applyDraft();
                        }
                        closePanel();
                    }
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDateRangePickers);
        } else {
            initDateRangePickers();
        }

        document.addEventListener('livewire:navigated', initDateRangePickers);
    })();
</script>
