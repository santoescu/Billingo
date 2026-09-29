@php
    $basicSelectConfig = \App\Support\SelectConfig::basic();
@endphp

<x-layouts.app :title="$customer->name ?: $customer->identificacion">
    @include('partials.tittle', [
        'title' => $customer->name ?: $customer->identificacion,
        'subheading' => $customer->identificacion . ($customer->phone ? ' · ' . $customer->phone : ''),
    ])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        @if ($program?->hasMechanic('points'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h3 class="text-xs text-zinc-500 dark:text-neutral-400 uppercase mb-1">{{ __('Points') }}</h3>
                <p class="text-2xl font-semibold text-gray-800 dark:text-white" id="loyalty-points-balance">{{ $customer->points_balance }}</p>
            </div>
        @endif
        @if ($program?->hasMechanic('stamps'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h3 class="text-xs text-zinc-500 dark:text-neutral-400 uppercase mb-1">{{ __('Stamps') }}</h3>
                <p class="text-2xl font-semibold text-gray-800 dark:text-white" id="loyalty-stamps-count">{{ $customer->stamps_count }}</p>
                <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('of') }} {{ $program->settingsFor('stamps')['stamps_required'] ?? 0 }}</p>
            </div>
        @endif
        @if ($program?->hasMechanic('cashback'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h3 class="text-xs text-zinc-500 dark:text-neutral-400 uppercase mb-1">{{ __('Cashback') }}</h3>
                <p class="text-2xl font-semibold text-gray-800 dark:text-white">$<span id="loyalty-cashback-balance">{{ number_format($customer->cashback_balance, 2) }}</span></p>
            </div>
        @endif
        @if ($program?->hasMechanic('tiers'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h3 class="text-xs text-zinc-500 dark:text-neutral-400 uppercase mb-1">{{ __('Tier') }}</h3>
                <p class="text-2xl font-semibold text-gray-800 dark:text-white">{{ $customer->current_tier_name ?? '—' }}</p>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Redeem') }}</h3>
            </div>
            <div class="p-4 flex flex-col gap-3">
                @if ($program?->hasMechanic('points'))
                    <form class="loyalty-redeem-form flex items-end gap-2" data-mechanic="points">
                        <flux:input type="number" name="amount" :label="__('Points to redeem')" class="w-40" required />
                        <flux:button type="submit" variant="filled">{{ __('Redeem points') }}</flux:button>
                    </form>
                @endif
                @if ($program?->hasMechanic('stamps'))
                    <form class="loyalty-redeem-form flex items-end gap-2" data-mechanic="stamps">
                        <flux:button type="submit" variant="filled">{{ __('Redeem completed card') }}</flux:button>
                    </form>
                @endif
                @if ($program?->hasMechanic('cashback'))
                    <form class="loyalty-redeem-form flex items-end gap-2" data-mechanic="cashback">
                        <flux:input type="number" step="0.01" name="amount" :label="__('Cashback to redeem')" class="w-40" required />
                        <flux:button type="submit" variant="filled">{{ __('Redeem cashback') }}</flux:button>
                    </form>
                @endif
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Manual adjustment') }}</h3>
            </div>
            <div class="p-4">
                <form id="loyalty-adjust-form" class="flex flex-col gap-3">
                    <flux:field>
                        <flux:label>{{ __('Mechanic') }}</flux:label>
                        <select name="mechanic" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                            @if ($program?->hasMechanic('points')) <option value="points">{{ __('Points') }}</option> @endif
                            @if ($program?->hasMechanic('stamps')) <option value="stamps">{{ __('Stamps') }}</option> @endif
                            @if ($program?->hasMechanic('cashback')) <option value="cashback">{{ __('Cashback') }}</option> @endif
                        </select>
                    </flux:field>
                    <flux:input type="number" step="0.01" name="amount" :label="__('Amount (use a negative number to subtract)')" required />
                    <flux:input name="note" :label="__('Reason')" required />
                    <flux:button type="submit" variant="filled">{{ __('Apply adjustment') }}</flux:button>
                </form>
            </div>
        </div>
    </div>

    @if ($instruments->isNotEmpty())
        <div class="border border-gray-200 rounded-lg dark:border-neutral-700 mt-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Coupons and cards') }}</h3>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach ($instruments as $instrument)
                    <li class="px-4 py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-800 dark:text-white">{{ $instrument->plan_name ?: \App\Models\LoyaltyInstrument::typeLabel($instrument->type) }} · <span class="font-mono">{{ $instrument->code }}</span></p>
                            <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Status') }}: {{ \App\Models\LoyaltyInstrument::statusLabel($instrument->status) }}</p>
                        </div>
                        <span class="text-sm text-gray-800 dark:text-white">{{ $instrument->remaining_value }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="border border-gray-200 rounded-lg dark:border-neutral-700 mt-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
            <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Recent activity') }}</h3>
        </div>
        @if ($transactions->isEmpty())
            <p class="p-4 text-sm text-zinc-500 dark:text-neutral-400">{{ __('No recent activity.') }}</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach ($transactions as $transaction)
                    <li class="px-4 py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-800 dark:text-white">{{ \App\Models\LoyaltyProgram::mechanicLabel($transaction->mechanic) }} · {{ \App\Models\LoyaltyTransaction::directionLabel($transaction->direction) }}</p>
                            <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ $transaction->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</p>
                        </div>
                        <span class="text-sm font-medium {{ $transaction->direction === 'redeem' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $transaction->direction === 'redeem' ? '-' : '+' }}{{ $transaction->amount }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @push('scripts')
        <script>
            (function () {
                function initLoyaltyCustomerActions() {
                    if (document.body.dataset.loyaltyCustomerBound === 'true') return;
                    document.body.dataset.loyaltyCustomerBound = 'true';

                    document.addEventListener('submit', async function (event) {
                        const redeemForm = event.target.closest('.loyalty-redeem-form');
                        if (redeemForm) {
                            event.preventDefault();
                            const response = await fetch('{{ route('loyalty.customers.redeem', $customer->_id) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    mechanic: redeemForm.dataset.mechanic,
                                    amount: redeemForm.querySelector('[name="amount"]')?.value ?? null,
                                }),
                            });
                            const data = await response.json();

                            if (! response.ok) {
                                window.appConfirmDialog?.notify(data.message || '{{ __('Could not redeem.') }}', '{{ __('Redeem') }}');
                                return;
                            }

                            if (document.getElementById('loyalty-points-balance')) document.getElementById('loyalty-points-balance').textContent = data.points_balance;
                            if (document.getElementById('loyalty-stamps-count')) document.getElementById('loyalty-stamps-count').textContent = data.stamps_count;
                            if (document.getElementById('loyalty-cashback-balance')) document.getElementById('loyalty-cashback-balance').textContent = Number(data.cashback_balance).toFixed(2);
                            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: '{{ __('Redeemed successfully.') }}' } }));
                            return;
                        }

                        if (event.target.id === 'loyalty-adjust-form') {
                            event.preventDefault();
                            const form = event.target;
                            const response = await fetch('{{ route('loyalty.customers.adjust', $customer->_id) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    mechanic: form.querySelector('[name="mechanic"]').value,
                                    amount: form.querySelector('[name="amount"]').value,
                                    note: form.querySelector('[name="note"]').value,
                                }),
                            });
                            const data = await response.json();

                            if (! response.ok) {
                                window.appConfirmDialog?.notify(data.message || '{{ __('Could not apply the adjustment.') }}', '{{ __('Manual adjustment') }}');
                                return;
                            }

                            window.location.reload();
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', initLoyaltyCustomerActions);
                document.addEventListener('livewire:navigated', initLoyaltyCustomerActions);
            })();
        </script>
    @endpush
</x-layouts.app>
