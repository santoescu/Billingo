<section class="w-full">
    @include('partials.settings-heading')
    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
        <div class="inline-flex items-center rounded-lg p-1 w-full max-w-2xl
            bg-gray-200
            dark:bg-neutral-900/80">
            <!-- Light -->
            <div class="relative flex-1">
                <input id="appearance-light" type="radio" name="appearance" value="light"
                       wire:model.live="appearance" class="peer sr-only" />
                <label for="appearance-light"
                       class="flex items-center justify-center gap-2 w-full px-5 py-2.5
                      text-sm font-medium rounded-md cursor-pointer select-none transition
                      text-gray-600 hover:text-gray-900
                      dark:text-neutral-300 dark:hover:text-white
                      peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow
                      dark:peer-checked:bg-neutral-700 dark:peer-checked:text-white dark:peer-checked:shadow-none">
                    <flux:icon.sun />
                    {{ __('Light') }}
                </label>
            </div>
            <!-- Dark -->
            <div class="relative flex-1">
                <input id="appearance-dark" type="radio" name="appearance" value="dark"
                       wire:model.live="appearance" class="peer sr-only" />

                <label for="appearance-dark"
                       class="flex items-center justify-center gap-2 w-full px-5 py-2.5
                      text-sm font-medium rounded-md cursor-pointer select-none transition
                      text-gray-600 hover:text-gray-900
                      dark:text-neutral-300 dark:hover:text-white
                      peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow
                      dark:peer-checked:bg-neutral-700 dark:peer-checked:text-white dark:peer-checked:shadow-none">
                    <flux:icon.moon />
                    {{ __('Dark') }}
                </label>
            </div>
        </div>
        <script>
            window.addEventListener('appearance-updated', () => {
                location.reload();
            });
        </script>
    </x-settings.layout>
</section>
