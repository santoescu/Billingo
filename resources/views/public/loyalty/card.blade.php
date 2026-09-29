<x-layouts.public :title="($program->name ?? __('Loyalty')) . ' — ' . ($customer->name ?: $customer->identificacion)">
    <div class="flex min-h-[70vh] items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            @if ($program?->branding['logo_url'] ?? null)
                <img src="{{ $program->branding['logo_url'] }}" alt="{{ $program->name }}" class="mx-auto mb-3 max-h-14">
            @endif

            <h1 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $program->name ?? __('Loyalty program') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-neutral-400 mb-6">{{ $customer->name ?: $customer->identificacion }}</p>

            <img src="{{ $qrDataUri }}" alt="QR" class="mx-auto mb-6 size-40">

            <div class="grid grid-cols-2 gap-3 text-start mb-6">
                @if ($program?->hasMechanic('points'))
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Points') }}</p>
                        <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $customer->points_balance }}</p>
                    </div>
                @endif
                @if ($program?->hasMechanic('stamps'))
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Stamps') }}</p>
                        <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $customer->stamps_count }} / {{ $program->settingsFor('stamps')['stamps_required'] ?? 0 }}</p>
                    </div>
                @endif
                @if ($program?->hasMechanic('cashback'))
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Cashback') }}</p>
                        <p class="text-xl font-semibold text-gray-800 dark:text-white">${{ number_format($customer->cashback_balance, 2) }}</p>
                    </div>
                @endif
                @if ($program?->hasMechanic('tiers') && $customer->current_tier_name)
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <p class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Tier') }}</p>
                        <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $customer->current_tier_name }}</p>
                    </div>
                @endif
            </div>

            @if ($instruments->isNotEmpty())
                <div class="text-start">
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-neutral-400 mb-2">{{ __('Coupons and cards') }}</p>
                    <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                        @foreach ($instruments as $instrument)
                            <li class="py-2 flex justify-between text-sm">
                                <span class="text-gray-800 dark:text-white">{{ $instrument->plan_name ?: $instrument->type }}</span>
                                <span class="font-mono text-zinc-500 dark:text-neutral-400">{{ $instrument->code }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="mt-6 text-xs text-zinc-400 dark:text-neutral-500">{{ __('Show this screen or QR code at checkout.') }}</p>
        </div>
    </div>
</x-layouts.public>
