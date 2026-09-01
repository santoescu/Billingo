<x-layouts.app :title="__('Sellers')">
    @include('partials.tittle', [
        'title' => __('Sellers'),
        'subheading' => __('The sellers you can pick from when charging a POS sale.'),
    ])

    @include('pos.partials.tabs', ['activeTab' => 'sellers'])

    <div class="flex flex-col gap-6">
        <div class="flex justify-end">
            <flux:button id="new-seller-btn" type="button" variant="primary" icon="plus" onclick="window.openSellerPanel()">
                {{ __('New seller') }}
            </flux:button>
        </div>

        @if ($sellers->isEmpty())
            <section class="flex min-h-[160px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Sellers')]) }}</p>
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sellers as $seller)
                    <div class="relative space-y-2 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600">
                        <div class="absolute right-3 top-3 flex gap-1">
                            <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                                title="{{ __('Edit') }}"
                                onclick="window.openSellerPanel({{ Illuminate\Support\Js::from([
                                    'id' => (string) $seller->_id,
                                    'name' => $seller->name,
                                ]) }})"
                            >
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                            </button>
                            <form action="{{ route('pos.sellers.destroy', $seller->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                </button>
                            </form>
                        </div>

                        <div class="w-full space-y-2 pr-14">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $seller->name }}</h2>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>

    <div id="seller-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-lg w-full z-80 bg-white border-s border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="seller-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="seller-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('New seller') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#seller-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <form id="sellerForm" method="POST" action="{{ route('pos.sellers.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="seller-method" value="POST">
                <flux:input name="name" id="seller-name" :label="__('Name')" maxlength="255" required />
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="filled" data-hs-overlay="#seller-panel">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" id="seller-submit-btn" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.openSellerPanel = function (seller) {
            const form = document.getElementById('sellerForm');
            const nameInput = document.getElementById('seller-name');
            const methodInput = document.getElementById('seller-method');
            const label = document.getElementById('seller-panel-label');

            nameInput.value = seller?.name ?? '';

            if (seller?.id) {
                form.action = @json(route('pos.sellers.update', ['seller' => '__ID__'])).replace('__ID__', seller.id);
                methodInput.value = 'PUT';
                label.textContent = '{{ __('Edit seller') }}';
            } else {
                form.action = @json(route('pos.sellers.store'));
                methodInput.value = 'POST';
                label.textContent = '{{ __('New seller') }}';
            }

            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#seller-panel');
            }
        };
    </script>
</x-layouts.app>
