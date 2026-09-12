{{--
    Script del panel de third-parties/partials/form-panel.blade.php.
    $storeRoute es obligatorio; $updateRouteBase solo lo define la pantalla
    de Clientes/Proveedores (el POS nunca edita, solo crea).

    El submit queda en dos modos según quién lo incluya:
    - Sin window.thirdPartyPanelOnSave definido (Clientes/Proveedores): el
      form se manda normal (recarga y redirige, como cualquier form Laravel).
    - Con window.thirdPartyPanelOnSave definido (POS): se intercepta por
      AJAX y se le pasa el cliente creado/actualizado, sin salir del modal
      ni perder el carrito que ya se tenía armado.
--}}
<script>
    (function () {
        const municipiosByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->codigo => $department->municipios ?? []]));

        function setSelectValue(selectId, value) {
            const el = document.getElementById(selectId);
            const instance = window.HSSelect && HSSelect.getInstance(el);
            if (instance) {
                instance.setValue(value ?? '');
            } else {
                el.value = value ?? '';
            }
        }

        let tpChipEmails = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[char]);
        }

        /**
         * El correo del tercero se guarda como una lista separada por coma en un solo campo
         * (mismo criterio que DocumentoEmitidoController::sendEmail()), pero se ve como "chips"
         * (un badge removible por cada correo) -- mismo patrón que
         * documents/partials/send-email-modal.blade.php. "tpChipEmails" es la lista en memoria;
         * el <input type="hidden" id="tp-email"> es lo único que de verdad manda el formulario.
         * @returns {void}
         */
        function tpRenderEmailChips() {
            const container = document.getElementById('tp-email-chips');
            const textInput = document.getElementById('tp-email-chip-input');
            const hiddenInput = document.getElementById('tp-email');
            if (! container || ! textInput || ! hiddenInput) return;

            container.querySelectorAll('[data-chip]').forEach((chip) => chip.remove());

            tpChipEmails.forEach((email, index) => {
                const chip = document.createElement('span');
                chip.dataset.chip = 'true';
                chip.className = 'inline-flex items-center gap-1 rounded-md bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-2 py-1 text-xs font-medium';
                chip.innerHTML = `${escapeHtml(email)}<button type="button" class="hover:opacity-70" data-index="${index}" aria-label="{{ __('Remove') }}">
                    <svg class="size-3 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>`;
                container.insertBefore(chip, textInput);
            });

            hiddenInput.value = tpChipEmails.join(',');
        }

        /**
         * Agrega el texto que el usuario escribió como chip nuevo, si parece un correo válido y
         * no está repetido -- un correo con formato inválido se deja tal cual en el input (no se
         * limpia) para que el usuario lo corrija, en vez de perderlo en silencio.
         * @returns {void}
         */
        function tpCommitEmailChipInput() {
            const textInput = document.getElementById('tp-email-chip-input');
            if (! textInput) return;

            const email = textInput.value.trim().replace(/,+$/, '');
            if (! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;

            if (! tpChipEmails.includes(email)) {
                tpChipEmails.push(email);
                tpRenderEmailChips();
            }

            textInput.value = '';
        }

        /**
         * Reemplaza toda la lista de chips por la que venga en "csv" (separada por coma) --
         * usado al abrir el panel para editar un tercero ya existente (ver openThirdPartyPanel())
         * y al recargar la página con errores de validación (ver el bloque de errores más abajo,
         * que ya deja "old('email')" en el input oculto).
         * @param {string} csv
         * @returns {void}
         */
        window.setThirdPartyEmailChips = function (csv) {
            tpChipEmails = (csv || '').split(',').map((email) => email.trim()).filter(Boolean);
            const textInput = document.getElementById('tp-email-chip-input');
            if (textInput) textInput.value = '';
            tpRenderEmailChips();
        };

        /**
         * Igual que initDianAcquirerLookups() (documents/components/dian-acquirer-lookup-script.blade.php):
         * se re-ejecuta completo en cada navegación Livewire, así que la guardia va sobre el
         * elemento en sí (fresco en cada navegación), no sobre "document.body" -- si no, después
         * de la primera visita a esta página en la sesión, los chips de una visita posterior se
         * quedarían sin ningún listener.
         * @returns {void}
         */
        function initThirdPartyEmailChips() {
            const container = document.getElementById('tp-email-chips');
            if (! container || container.dataset.bound === 'true') return;
            container.dataset.bound = 'true';

            // El valor inicial (old('email') tras un error de validación, o vacío en un panel
            // nuevo) ya viene puesto en el input oculto desde el servidor -- se arranca la lista
            // de chips a partir de ahí, en vez de vacía.
            tpChipEmails = (document.getElementById('tp-email')?.value || '').split(',').map((email) => email.trim()).filter(Boolean);

            const textInput = document.getElementById('tp-email-chip-input');

            textInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    tpCommitEmailChipInput();
                } else if (event.key === 'Backspace' && textInput.value === '' && tpChipEmails.length) {
                    tpChipEmails.pop();
                    tpRenderEmailChips();
                }
            });

            textInput.addEventListener('blur', tpCommitEmailChipInput);

            container.addEventListener('click', function (event) {
                const removeBtn = event.target.closest('button[data-index]');
                if (! removeBtn) return;

                tpChipEmails.splice(Number(removeBtn.dataset.index), 1);
                tpRenderEmailChips();
            });

            // El buscador de la DIAN (ver dian-acquirer-lookup-script.blade.php) dispara este
            // evento en vez de pisar un input de texto directo -- si ya está entre los chips no
            // agrega nada de nuevo (evita duplicar lo que el usuario ya había escrito a mano).
            container.addEventListener('add-email-chip', function (event) {
                const email = event.detail?.email?.trim();
                if (email && ! tpChipEmails.includes(email)) {
                    tpChipEmails.push(email);
                    tpRenderEmailChips();
                }
            });

            tpRenderEmailChips();
        }

        function rebuildCitySelect(citySelect, departmentCode, selectedCityCode = '') {
            const instance = window.HSSelect && HSSelect.getInstance(citySelect);
            if (instance && typeof instance.destroy === 'function') {
                instance.destroy();
                citySelect.parentElement.appendChild(citySelect);
            }

            const municipios = municipiosByDepartment[departmentCode] || [];
            citySelect.innerHTML = '<option value="">{{ __('Select...') }}</option>';
            municipios.forEach((municipio) => {
                const option = document.createElement('option');
                option.value = municipio.codigo;
                option.textContent = municipio.descripcion.trim();
                option.selected = municipio.codigo === selectedCityCode;
                citySelect.appendChild(option);
            });

            if (window.HSSelect) {
                new HSSelect(citySelect);
            }
        }

        window.openThirdPartyPanel = function (thirdParty) {
            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#third-party-panel');
            }

            document.getElementById('tp-panel-error')?.classList.add('hidden');
            document.querySelector('[data-dian-lookup-status]')?.classList.add('hidden');
            document.querySelector('[data-dian-lookup-spinner]')?.classList.add('hidden');

            const form = document.getElementById('thirdPartyForm');

            document.getElementById('tp-name').value = thirdParty?.name ?? '';
            setSelectValue('tp-identification_type', thirdParty?.identification_type ?? '13');
            document.getElementById('tp-identificacion').value = thirdParty?.identificacion ?? '';
            document.querySelector('[data-dian-lookup]')?.dianLookupTrigger?.();
            setSelectValue('tp-person_type', thirdParty?.person_type);

            const fiscalResponsibilitiesCodes = (thirdParty?.fiscal_responsibilities ?? '').split(';').filter(Boolean);
            const fiscalResponsibilitiesSelect = document.getElementById('tp-fiscal_responsibilities');
            const fiscalResponsibilitiesInstance = window.HSSelect && HSSelect.getInstance(fiscalResponsibilitiesSelect);
            if (fiscalResponsibilitiesInstance) {
                fiscalResponsibilitiesInstance.setValue(fiscalResponsibilitiesCodes);
            }

            document.getElementById('tp-address').value = thirdParty?.address ?? '';
            setSelectValue('tp-department_code', thirdParty?.department_code);
            rebuildCitySelect(document.getElementById('tp-city_code'), thirdParty?.department_code ?? '', thirdParty?.city_code ?? '');
            document.getElementById('tp-phone').value = thirdParty?.phone ?? '';
            window.setThirdPartyEmailChips(thirdParty?.email ?? '');

            @if (isset($updateRouteBase))
                if (thirdParty?.id) {
                    form.action = @json(route($updateRouteBase, ['thirdParty' => '__ID__'])).replace('__ID__', thirdParty.id);
                    document.getElementById('tp-method').value = 'PUT';
                } else {
                    form.action = @json(route($storeRoute));
                    document.getElementById('tp-method').value = 'POST';
                }
            @else
                form.action = @json(route($storeRoute));
                document.getElementById('tp-method').value = 'POST';
            @endif
        };

        function init() {
            const departmentSelect = document.getElementById('tp-department_code');

            if (!departmentSelect || departmentSelect.dataset.bound === 'true') {
                return;
            }
            departmentSelect.dataset.bound = 'true';

            initThirdPartyEmailChips();

            rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value, '{{ old('city_code') }}');
            departmentSelect.addEventListener('change', () => {
                rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value);
            });

            document.getElementById('thirdPartyForm').addEventListener('submit', async function (event) {
                // Antes que nada, tanto si el submit sigue normal como si se intercepta por
                // AJAX abajo: si el usuario escribió un correo y le dio directo a "Guardar" sin
                // pasar por Enter/coma, que no se pierda.
                tpCommitEmailChipInput();

                if (typeof window.thirdPartyPanelOnSave !== 'function') {
                    return;
                }

                event.preventDefault();

                const errorBox = document.getElementById('tp-panel-error');
                errorBox?.classList.add('hidden');

                try {
                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: new FormData(event.target),
                    });
                    const data = await response.json();

                    if (! response.ok) {
                        const message = data.message || Object.values(data.errors || {}).flat().join(' ') || '{{ __('Could not save the client.') }}';
                        throw new Error(message);
                    }

                    window.thirdPartyPanelOnSave(data.client);
                    if (window.HSOverlay) {
                        HSOverlay.close('#third-party-panel');
                    }
                } catch (error) {
                    if (errorBox) {
                        errorBox.textContent = error.message;
                        errorBox.classList.remove('hidden');
                    }
                }
            });

            @if ($errors->any())
                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#third-party-panel');
                }
            @endif
        }

        document.addEventListener('DOMContentLoaded', init);
        document.addEventListener('livewire:navigated', init);
    })();
</script>
