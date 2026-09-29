@php
    $basicSelectConfig = \App\Support\SelectConfig::basic();
@endphp

<x-layouts.app :title="__('Loyalty program')">
    @include('partials.tittle', [
        'title' => __('Loyalty program'),
        'subheading' => __('Configure the mechanics your customers can earn and redeem.'),
    ])

    <div class="border border-gray-200 rounded-lg dark:border-neutral-700 mb-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
            <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Enrollment link') }}</h3>
            <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Share this link (or its QR) so customers can sign up themselves, no app needed.') }}</p>
        </div>
        <div class="p-4">
            <div class="flex items-stretch rounded-lg border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 max-w-xl">
                <input type="text" readonly id="loyalty-enroll-url" value="{{ $enrollUrl }}" class="flex-1 min-w-0 bg-transparent border-0 text-zinc-700 dark:text-zinc-300 text-sm h-9 px-3 focus:outline-hidden focus:ring-0">
                <button type="button" class="js-clipboard shrink-0 inline-flex items-center justify-center px-3 border-s border-zinc-200 dark:border-white/10 text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" data-clipboard-target="#loyalty-enroll-url" data-clipboard-action="copy" aria-label="{{ __('Copy') }}" title="{{ __('Copy') }}">
                    <svg class="js-clipboard-default size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    <svg class="js-clipboard-success hidden size-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('loyalty.program.update') }}" x-data="{ active: {{ json_encode($program->active_mechanics ?? []) }} }">
        @csrf
        @method('PUT')

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700 mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('General') }}</h3>
            </div>
            <div class="p-4 flex flex-col gap-4 max-w-xl">
                <flux:input name="name" :label="__('Program name')" value="{{ old('name', $program->name) }}" required />

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <select name="status" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="active" @selected($program->status === 'active')>{{ __('Active') }}</option>
                        <option value="paused" @selected($program->status === 'paused')>{{ __('Paused') }}</option>
                    </select>
                </flux:field>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg dark:border-neutral-700 mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Mechanics') }}</h3>
                <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Turn on as many as you want, they all work at the same time.') }}</p>
            </div>
            <div class="p-4 flex flex-col divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach ($mechanics as $mechanic)
                    <div class="py-4 first:pt-0 last:pb-0">
                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-800 dark:text-white">
                            <input type="checkbox" name="active_mechanics[]" value="{{ $mechanic }}" x-model="active" class="rounded-sm border-gray-300 accent-accent">
                            {{ __(ucfirst(str_replace('_', ' ', $mechanic))) }}
                        </label>

                        <div x-show="active.includes('{{ $mechanic }}')" x-cloak class="mt-3 pl-6 flex flex-wrap gap-4">
                            @switch($mechanic)
                                @case('stamps')
                                    <flux:input type="number" name="settings[stamps][stamps_required]" :label="__('Stamps required')" value="{{ $program->settingsFor('stamps')['stamps_required'] ?? 10 }}" class="w-40" />
                                    <flux:input name="settings[stamps][reward_description]" :label="__('Reward')" value="{{ $program->settingsFor('stamps')['reward_description'] ?? '' }}" class="w-64" />
                                    <flux:input type="number" step="0.01" name="settings[stamps][reward_value]" :label="__('Reward value (discount at checkout)')" value="{{ $program->settingsFor('stamps')['reward_value'] ?? 0 }}" class="w-56" />
                                    @break
                                @case('points')
                                    <flux:input type="number" name="settings[points][earn_rate_per_currency]" :label="__('Points earned')" value="{{ $program->settingsFor('points')['earn_rate_per_currency'] ?? 1 }}" class="w-40" />
                                    <flux:input type="number" name="settings[points][currency_unit]" :label="__('Per amount spent')" value="{{ $program->settingsFor('points')['currency_unit'] ?? 1000 }}" class="w-40" />
                                    <flux:input type="number" step="0.01" name="settings[points][redeem_value]" :label="__('Value per point when redeemed')" value="{{ $program->settingsFor('points')['redeem_value'] ?? 0 }}" class="w-56" />
                                    @break
                                @case('cashback')
                                    <flux:input type="number" step="0.01" name="settings[cashback][percentage]" :label="__('Percentage')" value="{{ $program->settingsFor('cashback')['percentage'] ?? 5 }}" class="w-40" />
                                    @break
                                @case('tiers')
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400 w-full">{{ __('Tier levels are managed from the customer list for now.') }}</p>
                                    @break
                                @case('memberships')
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400 w-full">{{ __('Issue memberships from the "Coupons and cards" screen.') }}</p>
                                    @break
                                @case('multipass')
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400 w-full">{{ __('Issue multipasses from the "Coupons and cards" screen.') }}</p>
                                    @break
                                @case('gift_cards')
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400 w-full">{{ __('Issue gift cards from the "Coupons and cards" screen.') }}</p>
                                    @break
                                @case('coupons')
                                    <p class="text-xs text-zinc-500 dark:text-neutral-400 w-full">{{ __('Issue coupons from the "Coupons and cards" screen.') }}</p>
                                    @break
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
    </form>
</x-layouts.app>
