@php
    $basicSelectConfig = \App\Support\SelectConfig::basic();
@endphp

<x-layouts.app :title="__('Payment methods')">
    @include('partials.tittle', [
        'title' => __('Payment methods'),
        'subheading' => __('Your own payment methods for the POS, optionally mapped to their DIAN equivalent for electronic invoices.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-end items-center gap-3">
                        <flux:button type="button" variant="primary" icon="plus" onclick="window.openPaymentMethodPanel()">
                            {{ __('New payment method') }}
                        </flux:button>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('DIAN equivalent') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($paymentMethods as $paymentMethod)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $paymentMethod->name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            @if ($paymentMethod->dian_payment_means_code)
                                                {{ $paymentMeansCodes->firstWhere('codigo', $paymentMethod->dian_payment_means_code)?->medio ?? $paymentMethod->dian_payment_means_code }}
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">{{ __('Not mapped (cannot be used for electronic invoices)') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <div class="flex justify-end items-center gap-2">
                                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                                                    title="{{ __('Edit') }}"
                                                    onclick="window.openPaymentMethodPanel({{ Illuminate\Support\Js::from([
                                                        'id' => (string) $paymentMethod->_id,
                                                        'name' => $paymentMethod->name,
                                                        'dian_payment_means_code' => $paymentMethod->dian_payment_means_code,
                                                    ]) }})"
                                                >
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                                </button>
                                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400"
                                                    title="{{ __('Remove') }}"
                                                    onclick="window.confirmDeletePaymentMethod('{{ route('pos.payment-methods.destroy', $paymentMethod->_id) }}')"
                                                >
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Payment methods')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="payment-method-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-lg w-full z-80 bg-white border-s border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="payment-method-panel-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="payment-method-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('New payment method') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#payment-method-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">{{ __('If you leave the DIAN equivalent empty, this payment method cannot be used for a sale issued as an electronic invoice.') }}</p>
            <form id="paymentMethodForm" method="POST" action="{{ route('pos.payment-methods.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="payment-method-method" value="POST">
                <flux:input name="name" id="payment-method-name" :label="__('Name')" maxlength="255" required />
                <div>
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('DIAN equivalent') }}</label>
                    <select name="dian_payment_means_code" id="payment-method-dian-code" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value=""></option>
                        @foreach ($paymentMeansCodes as $paymentMeansCode)
                            <option value="{{ $paymentMeansCode->codigo }}">{{ $paymentMeansCode->codigo }} - {{ $paymentMeansCode->medio }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="filled" data-hs-overlay="#payment-method-panel">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirm-delete-payment-method-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="confirm-delete-payment-method-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-sm sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="p-4">
                    <h3 id="confirm-delete-payment-method-label" class="font-bold text-gray-800 dark:text-white mb-1">{{ __('Remove payment method') }}</h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">{{ __('Are you sure?') }}</p>
                    <form id="confirm-delete-payment-method-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <div class="flex justify-end gap-3">
                            <flux:button type="button" variant="filled" data-hs-overlay="#confirm-delete-payment-method-modal">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="danger">{{ __('Remove') }}</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.openPaymentMethodPanel = function (paymentMethod) {
            const form = document.getElementById('paymentMethodForm');
            const nameInput = document.getElementById('payment-method-name');
            const dianCodeSelect = document.getElementById('payment-method-dian-code');
            const methodInput = document.getElementById('payment-method-method');
            const label = document.getElementById('payment-method-panel-label');

            nameInput.value = paymentMethod?.name ?? '';

            const instance = window.HSSelect && HSSelect.getInstance(dianCodeSelect);
            if (instance) {
                instance.setValue(paymentMethod?.dian_payment_means_code ?? '');
            } else {
                dianCodeSelect.value = paymentMethod?.dian_payment_means_code ?? '';
            }

            if (paymentMethod?.id) {
                form.action = @json(route('pos.payment-methods.update', ['paymentMethod' => '__ID__'])).replace('__ID__', paymentMethod.id);
                methodInput.value = 'PUT';
                label.textContent = '{{ __('Edit payment method') }}';
            } else {
                form.action = @json(route('pos.payment-methods.store'));
                methodInput.value = 'POST';
                label.textContent = '{{ __('New payment method') }}';
            }

            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#payment-method-panel');
            }
        };

        window.confirmDeletePaymentMethod = function (destroyUrl) {
            document.getElementById('confirm-delete-payment-method-form').action = destroyUrl;
            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#confirm-delete-payment-method-modal');
            }
        };
    </script>
</x-layouts.app>
