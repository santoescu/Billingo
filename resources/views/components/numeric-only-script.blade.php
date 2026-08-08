<script>
    (function () {
        // type="text" en vez de type="number" a propósito: type="number" trae
        // las flechitas de subir/bajar que no queremos. Filtramos a mano los
        // caracteres no numéricos, igual que se hace con los campos de precio.
        document.addEventListener('input', (event) => {
            const el = event.target;
            if (! (el instanceof HTMLInputElement) || ! el.hasAttribute('data-numeric-only')) {
                return;
            }

            const digitsOnly = el.value.replace(/\D/g, '');
            if (digitsOnly !== el.value) {
                el.value = digitsOnly;
            }
        });
    })();
</script>
