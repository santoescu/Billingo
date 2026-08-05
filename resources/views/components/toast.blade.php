<div
    x-data="{ show: false, message: '', type: 'info' }"
    x-init="
        @if(session('toast'))
            // Mostrar si hay sesión flash
            show = true;
            message = '{{ session('toast.message') }}';
            type = '{{ session('toast.type') }}';
            setTimeout(() => show = false, 3000);
        @endif

        // Escuchar evento Livewire/Alpine
        window.addEventListener('toast', (event) => {
            show = true;
            message = event.detail.message;
            type = event.detail.type;
            setTimeout(() => show = false, 3000);
        });
    "
    class="fixed top-5 right-5 z-50"
>
    <template x-if="show">
        <div class="max-w-xs bg-white border rounded-xl shadow-lg dark:bg-neutral-900 dark:border-neutral-700" role="alert">
            <div class="flex p-4 space-x-2 items-center">

                <!-- Iconos con Lucide -->
                <div class="flex-shrink-0">
                    <!-- Success -->
                    <svg x-show="type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>

                    <!-- Error -->
                    <svg x-show="type === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>

                    <!-- Info -->
                    <svg x-show="type === 'info'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="16" x2="12" y2="12" />
                        <line x1="12" y1="8" x2="12.01" y2="8" />
                    </svg>
                </div>

                <!-- Mensaje -->
                <div>
                    <p class="text-sm text-gray-700 dark:text-neutral-200" x-text="message"></p>
                </div>

                <!-- Botón cerrar -->

            </div>
        </div>
    </template>
</div>
