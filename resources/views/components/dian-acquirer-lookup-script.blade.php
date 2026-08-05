<script>
    (function () {
        function initDianAcquirerLookups() {
            document.querySelectorAll('[data-dian-lookup]').forEach((root) => {
                if (root.dataset.dianLookupInitialized) {
                    return;
                }
                root.dataset.dianLookupInitialized = 'true';

                const typeSelect = root.querySelector('[data-dian-lookup-type]');
                const numberInput = root.querySelector('[data-dian-lookup-number]');
                if (! typeSelect || ! numberInput) {
                    return;
                }

                const spinner = root.querySelector('[data-dian-lookup-spinner]');
                const statusEl = root.querySelector('[data-dian-lookup-status]');
                const dvWrapper = root.querySelector('[data-dian-lookup-dv-wrapper]');
                const dvInput = root.querySelector('[data-dian-lookup-dv]');

                function calculateDv(identification) {
                    const digits = (identification || '').replace(/\D/g, '').split('').reverse().map(Number);
                    const weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
                    const sum = digits.reduce((total, digit, i) => total + digit * (weights[i] || 0), 0);
                    const remainder = sum % 11;

                    return remainder > 1 ? 11 - remainder : remainder;
                }

                function refreshDv() {
                    if (! dvWrapper || ! dvInput) {
                        return;
                    }

                    const isNit = typeSelect.value === '31';
                    dvWrapper.classList.toggle('hidden', ! isNit);
                    dvInput.value = isNit && numberInput.value ? calculateDv(numberInput.value) : '';
                }

                // El nombre/correo a rellenar viven en otra parte del
                // formulario (no dentro de este cluster de tipo+número), así
                // que se buscan en el contenedor más cercano marcado como
                // alcance del formulario completo.
                const formScope = root.closest('[data-dian-lookup-form]') || document;
                const nameInput = formScope.querySelector('[data-dian-lookup-name]');
                const emailInput = formScope.querySelector('[data-dian-lookup-email]');

                let lookupTimeout = null;
                let lookupToken = 0;

                async function lookupInDian() {
                    const identificationType = typeSelect.value;
                    const identificationNumber = numberInput.value.trim();
                    const digitCount = identificationNumber.replace(/\D/g, '').length;

                    if (! identificationType || digitCount < 9) {
                        statusEl?.classList.add('hidden');
                        spinner?.classList.add('hidden');
                        return;
                    }

                    const token = ++lookupToken;
                    statusEl?.classList.add('hidden');
                    spinner?.classList.remove('hidden');

                    try {
                        const response = await fetch('{{ route('dian.acquirer') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                identification_type: identificationType,
                                identification_number: identificationNumber,
                            }),
                        });

                        const data = await response.json();

                        // Si mientras esperábamos el usuario ya cambió el valor, esta
                        // respuesta quedó obsoleta: no pisar lo que esté escribiendo ahora.
                        if (token !== lookupToken) {
                            return;
                        }

                        if (! response.ok || (! data.name && ! data.email)) {
                            if (statusEl) {
                                statusEl.textContent = data.message || '{{ __('The DIAN did not return data for this identification.') }}';
                                statusEl.classList.remove('hidden');
                            }
                            return;
                        }

                        if (data.name && nameInput) {
                            nameInput.value = data.name;
                        }
                        if (data.email && emailInput) {
                            emailInput.value = data.email;
                        }
                    } catch (error) {
                        if (token !== lookupToken) {
                            return;
                        }
                        if (statusEl) {
                            statusEl.textContent = error.message;
                            statusEl.classList.remove('hidden');
                        }
                    } finally {
                        if (token === lookupToken) {
                            spinner?.classList.add('hidden');
                        }
                    }
                }

                function scheduleLookup() {
                    clearTimeout(lookupTimeout);
                    lookupTimeout = setTimeout(lookupInDian, 500);
                }

                numberInput.addEventListener('input', () => { refreshDv(); scheduleLookup(); });
                typeSelect.addEventListener('change', () => { refreshDv(); scheduleLookup(); });
                refreshDv();

                // API pública: cuando algo externo (p. ej. elegir un cliente
                // ya existente de una búsqueda) llena la identificación por
                // JS en vez de que el usuario la escriba, el navegador no
                // dispara 'input' solo -- hay que forzar la consulta a mano.
                root.dianLookupTrigger = function () {
                    refreshDv();
                    clearTimeout(lookupTimeout);
                    lookupInDian();
                };
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDianAcquirerLookups);
        } else {
            initDianAcquirerLookups();
        }

        document.addEventListener('livewire:navigated', initDianAcquirerLookups);
    })();
</script>
