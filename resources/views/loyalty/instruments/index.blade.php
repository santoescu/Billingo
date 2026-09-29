@php
    $basicSelectConfig = \App\Support\SelectConfig::basic();
@endphp

<x-layouts.app :title="__('Coupons and cards')">
    @include('partials.tittle', [
        'title' => __('Coupons and cards'),
        'subheading' => __('Coupons, gift cards, multipasses and memberships you have issued.'),
    ])

    <div class="mb-4 flex flex-wrap justify-between gap-3">
        <form id="loyalty-instrument-redeem-form" class="flex items-end gap-2">
            <flux:input name="code" :label="__('Redeem by code')" class="w-56 uppercase" />
            <flux:button type="submit" variant="filled">{{ __('Redeem') }}</flux:button>
        </form>
        <flux:button type="button" variant="filled" icon="plus" data-hs-overlay="#loyalty-instrument-add-modal">
            {{ __('Issue new') }}
        </flux:button>
    </div>

    <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
        <div class="overflow-hidden">
            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                <thead class="bg-gray-50 dark:bg-neutral-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Code') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Type') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Customer') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Remaining') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                    @forelse ($instruments as $instrument)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono text-gray-800 dark:text-neutral-200">{{ $instrument->code }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ \App\Models\LoyaltyInstrument::typeLabel($instrument->type) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $instrument->customer?->name ?: $instrument->customer?->identificacion ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $instrument->remaining_value }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ \App\Models\LoyaltyInstrument::statusLabel($instrument->status) }}</td>
                            <td class="px-4 py-3 text-end text-sm">
                                @if ($instrument->status === 'active')
                                    <button type="button" class="loyalty-instrument-cancel-btn text-xs text-red-600 hover:underline dark:text-red-400" data-url="{{ route('loyalty.instruments.cancel', $instrument->_id) }}">{{ __('Cancel') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('coupons and cards')]) }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="loyalty-instrument-add-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form id="loyalty-instrument-add-form">
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 class="font-bold text-gray-800 dark:text-white">{{ __('Issue new') }}</h3>
                        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400" aria-label="Close" data-hs-overlay="#loyalty-instrument-add-modal">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-4 flex flex-col gap-3">
                        <flux:field>
                            <flux:label>{{ __('Type') }}</flux:label>
                            <select id="loyalty-instrument-type" name="type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                <option value="coupon">{{ __('Coupon') }}</option>
                                <option value="gift_card">{{ __('Gift card') }}</option>
                                <option value="multipass">{{ __('Multipass') }}</option>
                                <option value="membership">{{ __('Membership') }}</option>
                            </select>
                        </flux:field>
                        <flux:input name="plan_name" :label="__('Name')" />

                        <div class="relative">
                            <flux:input id="loyalty-instrument-client-search" :label="__('Assign to customer (identification, optional)')" placeholder="{{ __('Name or identification') }}" autocomplete="off" />
                            <div id="loyalty-instrument-client-results" class="hidden absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"></div>
                            <input type="hidden" name="identificacion">
                        </div>

                        <div id="loyalty-instrument-coupon-fields" class="hidden flex gap-3">
                            <flux:field class="w-40 shrink-0">
                                <flux:label>{{ __('Discount type') }}</flux:label>
                                <select id="loyalty-instrument-discount_type" name="discount_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                    <option value="percentage">{{ __('Percentage') }}</option>
                                    <option value="fixed">{{ __('Fixed amount') }}</option>
                                </select>
                            </flux:field>
                            <flux:input type="number" step="0.01" name="discount_value" :label="__('Discount value')" class="flex-1" />
                        </div>

                        <div id="loyalty-instrument-value-field">
                            <flux:input type="number" step="0.01" name="initial_value" :label="__('Value / uses')" />
                        </div>

                        <flux:input type="number" name="expires_after_days" :label="__('Expires after (days, optional)')" />
                    </div>
                    <div class="flex justify-end gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                        <flux:button type="button" variant="ghost" data-hs-overlay="#loyalty-instrument-add-modal">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Issue') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[char]);
                }

                function toggleCouponFields() {
                    const isCoupon = document.getElementById('loyalty-instrument-type').value === 'coupon';
                    document.getElementById('loyalty-instrument-coupon-fields').classList.toggle('hidden', ! isCoupon);
                    document.getElementById('loyalty-instrument-value-field').classList.toggle('hidden', isCoupon);
                }

                function initLoyaltyInstruments() {
                    if (document.body.dataset.loyaltyInstrumentsBound === 'true') return;
                    document.body.dataset.loyaltyInstrumentsBound = 'true';

                    toggleCouponFields();
                    document.getElementById('loyalty-instrument-type').addEventListener('change', toggleCouponFields);
                    document.getElementById('loyalty-instrument-type').addEventListener('change.hs.select', toggleCouponFields);

                    let clientSearchTimeout = null;
                    const clientSearchInput = document.getElementById('loyalty-instrument-client-search');
                    const clientSearchResults = document.getElementById('loyalty-instrument-client-results');
                    const clientHiddenInput = document.querySelector('#loyalty-instrument-add-form [name="identificacion"]');

                    clientSearchInput.addEventListener('input', function () {
                        clearTimeout(clientSearchTimeout);
                        clientHiddenInput.value = '';
                        const query = this.value.trim();

                        if (query.length < 2) {
                            clientSearchResults.classList.add('hidden');
                            return;
                        }

                        clientSearchTimeout = setTimeout(async () => {
                            const response = await fetch(`{{ route('loyalty.customers.client-search') }}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                            const data = await response.json();
                            const clients = data.clients ?? [];

                            clientSearchResults.innerHTML = clients.length
                                ? clients.map((client) => `
                                    <button type="button" class="loyalty-instrument-client-result w-full text-start px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700" data-identificacion="${escapeHtml(client.identificacion)}" data-name="${escapeHtml(client.name)}">
                                        <span class="block text-gray-800 dark:text-white">${escapeHtml(client.name)}</span>
                                        <span class="block text-xs text-zinc-500 dark:text-neutral-400">${escapeHtml(client.identificacion)}</span>
                                    </button>
                                `).join('')
                                : `<p class="p-3 text-sm text-zinc-500 dark:text-neutral-400">${"{{ __('No matches found.') }}"}</p>`;
                            clientSearchResults.classList.remove('hidden');
                        }, 350);
                    });

                    clientSearchResults.addEventListener('click', function (event) {
                        const button = event.target.closest('.loyalty-instrument-client-result');
                        if (! button) return;

                        clientHiddenInput.value = button.dataset.identificacion;
                        clientSearchInput.value = button.dataset.name;
                        clientSearchResults.classList.add('hidden');
                    });

                    document.addEventListener('click', function (event) {
                        if (! event.target.closest('#loyalty-instrument-client-results') && event.target !== clientSearchInput) {
                            clientSearchResults.classList.add('hidden');
                        }
                    });

                    document.getElementById('loyalty-instrument-add-form').addEventListener('submit', async function (event) {
                        event.preventDefault();
                        const form = event.target;
                        const isCoupon = form.querySelector('[name="type"]').value === 'coupon';
                        const response = await fetch('{{ route('loyalty.instruments.store') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                type: form.querySelector('[name="type"]').value,
                                plan_name: form.querySelector('[name="plan_name"]').value,
                                identificacion: form.querySelector('[name="identificacion"]').value,
                                initial_value: form.querySelector('[name="initial_value"]').value,
                                discount_type: isCoupon ? form.querySelector('[name="discount_type"]').value : '',
                                discount_value: isCoupon ? form.querySelector('[name="discount_value"]').value : '',
                                expires_after_days: form.querySelector('[name="expires_after_days"]').value,
                            }),
                        });

                        if (response.ok) {
                            window.location.reload();
                        }
                    });

                    document.getElementById('loyalty-instrument-redeem-form').addEventListener('submit', async function (event) {
                        event.preventDefault();
                        const code = event.target.querySelector('[name="code"]').value;
                        const response = await fetch('{{ route('loyalty.instruments.redeem') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ code }),
                        });
                        const data = await response.json();

                        if (! response.ok) {
                            window.appConfirmDialog?.notify(data.message || '{{ __('Could not redeem.') }}', '{{ __('Redeem') }}');
                            return;
                        }

                        window.location.reload();
                    });

                    document.addEventListener('click', async function (event) {
                        const cancelBtn = event.target.closest('.loyalty-instrument-cancel-btn');
                        if (! cancelBtn) return;

                        await fetch(cancelBtn.dataset.url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        });
                        window.location.reload();
                    });
                }

                document.addEventListener('DOMContentLoaded', initLoyaltyInstruments);
                document.addEventListener('livewire:navigated', initLoyaltyInstruments);
            })();
        </script>
    @endpush
</x-layouts.app>
