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
            document.getElementById('tp-email').value = thirdParty?.email ?? '';

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

            rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value, '{{ old('city_code') }}');
            departmentSelect.addEventListener('change', () => {
                rebuildCitySelect(document.getElementById('tp-city_code'), departmentSelect.value);
            });

            document.getElementById('thirdPartyForm').addEventListener('submit', async function (event) {
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
