@php
    $moduleCatalog = config('modules');
    $activeModules = $company->modules ?? [];
    $quotaModules = array_values(array_intersect($activeModules, \App\Models\CompanyContract::QUOTA_MODULES));

    $basicSelectConfig = \App\Support\SelectConfig::basic(__('No access'));
    $quotaModeSelectConfig = \App\Support\SelectConfig::basic();
    $renewalSelectConfig = \App\Support\SelectConfig::basic();
@endphp

<x-layouts.app :title="__('Edit :name', ['name' => $company->name])">
    @include('partials.tittle', [
        'title' => $company->name,
        'subheading' => __('Manage this company\'s active modules and members.'),
    ])

    <!-- Tab Nav -->
    <div class="border-b border-gray-200 dark:border-neutral-700">
        <nav class="flex gap-x-1" aria-label="Tabs" role="tablist" aria-orientation="horizontal">
            <button
                type="button"
                class="hs-tab-active:font-semibold hs-tab-active:text-accent hs-tab-active:after:bg-accent relative py-4 px-1 inline-flex items-center gap-x-2 text-sm whitespace-nowrap text-gray-500 after:absolute after:-bottom-px after:inset-x-0 after:w-full after:h-0.5 after:bg-transparent hover:text-gray-700 focus:outline-hidden dark:text-neutral-400 dark:hover:text-neutral-300 active"
                id="tab-modules-item"
                aria-selected="true"
                data-hs-tab="#tab-modules"
                aria-controls="tab-modules"
                role="tab"
            >
                {{ __('Modules') }}
            </button>
            <button
                type="button"
                class="hs-tab-active:font-semibold hs-tab-active:text-accent hs-tab-active:after:bg-accent relative py-4 px-1 inline-flex items-center gap-x-2 text-sm whitespace-nowrap text-gray-500 after:absolute after:-bottom-px after:inset-x-0 after:w-full after:h-0.5 after:bg-transparent hover:text-gray-700 focus:outline-hidden dark:text-neutral-400 dark:hover:text-neutral-300"
                id="tab-users-item"
                aria-selected="false"
                data-hs-tab="#tab-users"
                aria-controls="tab-users"
                role="tab"
            >
                {{ __('Users') }}
            </button>
            <button
                type="button"
                class="hs-tab-active:font-semibold hs-tab-active:text-accent hs-tab-active:after:bg-accent relative py-4 px-1 inline-flex items-center gap-x-2 text-sm whitespace-nowrap text-gray-500 after:absolute after:-bottom-px after:inset-x-0 after:w-full after:h-0.5 after:bg-transparent hover:text-gray-700 focus:outline-hidden dark:text-neutral-400 dark:hover:text-neutral-300"
                id="tab-contracts-item"
                aria-selected="false"
                data-hs-tab="#tab-contracts"
                aria-controls="tab-contracts"
                role="tab"
            >
                {{ __('Contracts') }}
            </button>
        </nav>
    </div>
    <!-- End Tab Nav -->

    <!-- Tab Content -->
    <div class="mt-6">
        <div id="tab-modules" role="tabpanel" aria-labelledby="tab-modules-item">
            <form method="POST" action="{{ route('admin.companies.modules.update', $company->_id) }}" class="max-w-xl space-y-4">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-500 dark:text-neutral-400">{{ __('Select which modules this company has contracted.') }}</p>

                <ul class="flex flex-col">
                    @foreach ($moduleCatalog as $moduleKey => $module)
                        <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm font-medium bg-white border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200">
                            <div class="relative flex items-start w-full">
                                <div class="flex items-center h-5">
                                    <input
                                        type="checkbox"
                                        id="module-{{ $moduleKey }}"
                                        name="modules[]"
                                        value="{{ $moduleKey }}"
                                        class="shrink-0 size-4 rounded-sm border-gray-300 accent-accent focus:ring-accent dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-offset-neutral-800"
                                        {{ in_array($moduleKey, $activeModules) ? 'checked' : '' }}
                                    >
                                </div>
                                <label for="module-{{ $moduleKey }}" class="block ms-3 w-full text-sm text-gray-700 dark:text-neutral-300">
                                    {{ $module['name'] ?? $moduleKey }}
                                </label>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </form>
        </div>

        <div id="tab-users" class="hidden" role="tabpanel" aria-labelledby="tab-users-item">
            <div class="flex justify-end mb-4">
                <flux:button variant="primary" icon="plus" data-hs-overlay="#add-member">{{ __('Add member') }}</flux:button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-400">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-400">{{ __('Modules') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                        @forelse ($members as $member)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">{{ $member->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $member->email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                    @if ($member->membership->role === 'owner')
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">
                                            {{ __('Owner') }} ({{ __('all modules') }})
                                        </span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($member->membership->modules ?? [] as $assignment)
                                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">
                                                    {{ $moduleCatalog[$assignment['module']]['name'] ?? $assignment['module'] }}: {{ ucfirst($assignment['role']) }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-neutral-400">{{ __('No access') }}</span>
                                            @endforelse
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($member->membership->role !== 'owner')
                                        <div class="flex justify-end gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                icon="pencil-square"
                                                onclick="openEditMemberModal({!! Illuminate\Support\Js::from($member) !!})"
                                            ></flux:button>

                                            <form action="{{ route('admin.companies.members.destroy', [$company->_id, $member->_id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button size="sm" variant="danger" icon="trash" type="submit"></flux:button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-neutral-400">{{ __('No results found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-contracts" class="hidden" role="tabpanel" aria-labelledby="tab-contracts-item">
            <div class="flex justify-end mb-4">
                <flux:button variant="primary" icon="plus" onclick="window.openContractPanel()">{{ __('New contract') }}</flux:button>
            </div>

            @if ($contracts->isEmpty())
                <section class="flex min-h-[160px] items-center justify-center rounded-lg border border-gray-200 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-800">
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('No results found') }}</p>
                </section>
            @else
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($contracts as $contract)
                        <div class="relative space-y-3 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600">
                            <div class="absolute right-3 top-3 flex gap-1">
                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                                    title="{{ __('Edit') }}"
                                    onclick="window.openContractPanel({{ Illuminate\Support\Js::from([
                                        'id' => (string) $contract->_id,
                                        'price' => $contract->price,
                                        'starts_at' => $contract->starts_at?->format('Y-m-d'),
                                        'ends_at' => $contract->ends_at?->format('Y-m-d'),
                                        'unlimited' => $contract->unlimited,
                                        'modules' => $contract->modules ?? [],
                                        'quota_mode' => $contract->quota_mode,
                                        'renewal_type' => $contract->renewal_type,
                                        'shared_limit' => $contract->shared_limit,
                                        'invoicing_limit' => $contract->invoicing_limit,
                                        'pos_limit' => $contract->pos_limit,
                                        'cotizaciones_limit' => $contract->cotizaciones_limit,
                                    ]) }})"
                                >
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                </button>
                                <form action="{{ route('admin.companies.contracts.destroy', [$company->_id, $contract->_id]) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>

                            <div class="w-full space-y-3 pr-14">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($contract->isWithinDateRange())
                                        <span class="rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ __('Active') }}</span>
                                    @elseif ($contract->ends_at && now()->startOfDay()->gt($contract->ends_at->copy()->startOfDay()))
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ __('Expired') }}</span>
                                    @else
                                        <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Upcoming') }}</span>
                                    @endif
                                    <span class="text-xs text-neutral-400">{{ $contract->starts_at?->format('Y-m-d') }} &rarr; {{ $contract->ends_at?->format('Y-m-d') ?? __('No end date') }}</span>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    @forelse ($contract->modules ?? [] as $moduleKey)
                                        <div class="space-y-1">
                                            @include('panel.partials.module-badge', ['module' => $moduleKey])
                                            <div class="text-xs text-gray-600 dark:text-neutral-400">
                                                @if ($contract->unlimited)
                                                    {{ __('Unlimited') }}
                                                @else
                                                    @php
                                                        $limit = $contract->quota_mode === 'shared' ? $contract->shared_limit : ($contract->{"{$moduleKey}_limit"} ?? null);
                                                        $used = $contract->quota_mode === 'shared' ? $contract->shared_used : ($contract->{"{$moduleKey}_used"} ?? 0);
                                                    @endphp
                                                    {{ $used }} / {{ $limit ?? '∞' }}
                                                    @if ($limit !== null)
                                                        <span class="block text-neutral-400">{{ __(':count remaining', ['count' => $contract->remaining($moduleKey)]) }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-xs text-neutral-400">—</span>
                                    @endforelse
                                </div>

                                <div class="text-sm text-gray-600 dark:text-neutral-400">
                                    {{ $contract->price !== null ? '$' . number_format($contract->price, 2, '.', ',') : '—' }}
                                </div>

                                <div class="text-sm text-gray-600 dark:text-neutral-400">
                                    @if ($contract->unlimited)
                                        {{ __('Unlimited') }}
                                    @else
                                        {{ $contract->quota_mode === 'shared' ? __('Shared across modules') : __('Per module') }}
                                    @endif
                                    <span class="text-xs text-neutral-400">&middot; {{ $contract->renewal_type === 'monthly' ? __('Renews monthly') : __('Fixed package') }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
    <!-- End Tab Content -->

    <!-- Panel deslizante único: crear/editar contrato -->
    <div id="contract-panel" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700 flex flex-col" role="dialog" tabindex="-1" aria-labelledby="contract-panel-label">
        <div class="shrink-0 flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="contract-panel-label" class="font-bold text-gray-800 dark:text-white">{{ __('New contract') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#contract-panel">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <form id="contractForm" action="{{ route('admin.companies.contracts.store', $company->_id) }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="_method" id="contract-method" value="POST">

                <div>
                    <label for="contract-price-display" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Price') }}</label>
                    <div class="relative">
                        <input type="hidden" name="price" id="contract-price">
                        <input type="text" id="contract-price-display" inputmode="decimal" placeholder="0"
                            class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                            <span class="text-sm">$</span>
                        </div>
                    </div>
                </div>

                <div>
                    <x-date-range-picker name-from="starts_at" name-to="ends_at" :label="__('Contract dates')" :value-from="now()->format('Y-m-d')" :allow-open-end="true" :floating="true" />
                    <p class="mt-1 text-xs text-neutral-400">{{ __('If you leave the end date empty, the contract will have no expiration.') }}</p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Modules covered by this contract') }}</label>
                    @if (empty($quotaModules))
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This company has none of the document-producing modules active yet (Invoicing, POS, Quotations).') }}</p>
                    @else
                        <div class="flex flex-col gap-2">
                            @foreach ($quotaModules as $moduleKey)
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" id="contract-module-{{ $moduleKey }}" name="modules[]" value="{{ $moduleKey }}" class="contract-module-checkbox shrink-0 size-4 rounded-sm border-gray-300 accent-accent focus:ring-accent dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-offset-neutral-800">
                                    </div>
                                    <label for="contract-module-{{ $moduleKey }}" class="block ms-3 text-sm text-zinc-800 dark:text-white">{{ $moduleCatalog[$moduleKey]['name'] ?? $moduleKey }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('modules')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contract-renewal-type" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Renewal') }}</label>
                    <select name="renewal_type" id="contract-renewal-type" data-hs-select='{!! $renewalSelectConfig !!}' class="hidden">
                        <option value="{{ \App\Models\CompanyContract::RENEWAL_LIFETIME }}">{{ __('Fixed package (does not renew)') }}</option>
                        <option value="{{ \App\Models\CompanyContract::RENEWAL_MONTHLY }}">{{ __('Renews every month') }}</option>
                    </select>
                </div>

                <div class="relative flex items-start">
                    <div class="flex items-center h-5">
                        <input type="checkbox" id="contract-unlimited" name="unlimited" value="1" class="shrink-0 size-4 rounded-sm border-gray-300 accent-accent focus:ring-accent dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-offset-neutral-800">
                    </div>
                    <label for="contract-unlimited" class="block ms-3 text-sm text-zinc-800 dark:text-white">{{ __('Unlimited documents (only enforces the dates above)') }}</label>
                </div>

                <div id="contract-quota-fields" class="space-y-4">
                    <div>
                        <label for="contract-quota-mode" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Quota') }}</label>
                        <select name="quota_mode" id="contract-quota-mode" data-hs-select='{!! $quotaModeSelectConfig !!}' class="hidden">
                            <option value="{{ \App\Models\CompanyContract::QUOTA_MODE_PER_MODULE }}">{{ __('Separate quota per module') }}</option>
                            <option value="{{ \App\Models\CompanyContract::QUOTA_MODE_SHARED }}">{{ __('One shared quota for all modules') }}</option>
                        </select>
                    </div>

                    <div id="contract-shared-field" class="hidden">
                        <label for="contract-shared-limit" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Total documents') }}</label>
                        <input type="number" min="1" name="shared_limit" id="contract-shared-limit" class="h-10 py-2 px-3 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <p class="mt-1 text-xs text-neutral-400">{{ __('Leave blank for no limit.') }}</p>
                    </div>

                    @if (empty($quotaModules))
                        <p id="contract-per-module-fields" class="hidden text-sm text-neutral-500 dark:text-neutral-400">{{ __('This company has none of the document-producing modules active yet (Invoicing, POS, Quotations).') }}</p>
                    @else
                        <div id="contract-per-module-fields" class="hidden space-y-4">
                            @foreach ($quotaModules as $moduleKey)
                                <div id="contract-{{ $moduleKey }}-limit-wrapper" class="hidden" data-contract-module-limit-for="{{ $moduleKey }}">
                                    <label for="contract-{{ $moduleKey }}-limit" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ $moduleCatalog[$moduleKey]['name'] ?? $moduleKey }}</label>
                                    <input type="number" min="1" name="{{ $moduleKey }}_limit" id="contract-{{ $moduleKey }}-limit" class="h-10 py-2 px-3 block w-full bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-base sm:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent">
                                    <p class="mt-1 text-xs text-neutral-400">{{ __('Leave blank for no limit.') }}</p>
                                </div>
                            @endforeach
                            <p id="contract-no-module-selected-hint" class="hidden text-sm text-neutral-500 dark:text-neutral-400">{{ __('Check at least one module above to set its quota.') }}</p>
                        </div>
                    @endif
                </div>

                @error('starts_at')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('ends_at')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel deslizante: agregar miembro -->
    <div id="add-member" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="add-member-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="add-member-label" class="font-bold text-gray-800 dark:text-white">{{ __('Add member') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#add-member">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-visible p-4">
            <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{{ __('The user must already have a registered account.') }}</p>

            <form action="{{ route('admin.companies.members.store', $company->_id) }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="admin-member-email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                    <div class="relative">
                        <input type="email" id="admin-member-email" name="email" required class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400 dark:text-white/60">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                        </div>
                    </div>
                </div>

                @if (empty($activeModules))
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This company has no active modules yet, so the member will be added without access to anything until a module is assigned.') }}</p>
                @else
                    @foreach ($activeModules as $moduleKey)
                        <div>
                            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ $moduleCatalog[$moduleKey]['name'] ?? $moduleKey }}</label>
                            <select name="modules[{{ $moduleKey }}]" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                <option value="">{{ __('No access') }}</option>
                                @foreach ($moduleCatalog[$moduleKey]['roles'] ?? [] as $role)
                                    <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                @endif

                @error('email')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('modules')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel deslizante único: editar miembro -->
    <div id="edit-member" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-md w-full z-80 bg-white border-e border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="edit-member-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="edit-member-label" class="font-bold text-gray-800 dark:text-white">{{ __('Users') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#edit-member">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-visible p-4">
            <form id="editMemberForm" method="POST" action="" class="space-y-6">
                @csrf
                @method('PUT')

                @if (empty($activeModules))
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('This company has no active modules yet, so the member will be added without access to anything until a module is assigned.') }}</p>
                @else
                    @foreach ($activeModules as $moduleKey)
                        <div>
                            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ $moduleCatalog[$moduleKey]['name'] ?? $moduleKey }}</label>
                            <select id="edit-member-module-{{ $moduleKey }}" name="modules[{{ $moduleKey }}]" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                                <option value="">{{ __('No access') }}</option>
                                @foreach ($moduleCatalog[$moduleKey]['roles'] ?? [] as $role)
                                    <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                @endif

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <x-date-range-picker-script />

    <script>
        (function () {
            function setSelectValue(selectId, value) {
                const el = document.getElementById(selectId);
                const instance = window.HSSelect && HSSelect.getInstance(el);
                if (instance) {
                    instance.setValue(value ?? '');
                } else {
                    el.value = value ?? '';
                }
            }

            window.openEditMemberModal = function (member) {
                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#edit-member');
                }

                document.getElementById('editMemberForm').action = `{{ route('admin.companies.members.update', [$company->_id, '__USER_ID__']) }}`.replace('__USER_ID__', member.id);

                const assignments = {};
                (member.membership?.modules || []).forEach((assignment) => {
                    assignments[assignment.module] = assignment.role;
                });

                @foreach ($activeModules as $moduleKey)
                    setSelectValue('edit-member-module-{{ $moduleKey }}', assignments['{{ $moduleKey }}'] || '');
                @endforeach
            };

            @if ($errors->any())
                function reopenAddMemberPanel() {
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#add-member');
                    }
                }

                document.addEventListener('DOMContentLoaded', reopenAddMemberPanel);
                document.addEventListener('livewire:navigated', reopenAddMemberPanel);
            @endif

            function formatContractPriceDisplay(intPart, decPart, hasComma) {
                if (! intPart && ! hasComma) return '';
                const formattedInt = Number(intPart || '0').toLocaleString('es-CO');
                return hasComma ? `${formattedInt},${decPart}` : formattedInt;
            }

            function contractPriceRawValue(intPart, decPart) {
                if (! intPart && ! decPart) return '';
                return decPart ? `${intPart || '0'}.${decPart}` : (intPart || '0');
            }

            function setContractPriceValue(rawValue) {
                const str = String(rawValue ?? '');
                const hidden = document.getElementById('contract-price');
                const display = document.getElementById('contract-price-display');
                if (! str) {
                    hidden.value = '';
                    display.value = '';
                    return;
                }
                const [intPart, decPart] = str.split('.');
                hidden.value = str;
                display.value = formatContractPriceDisplay(intPart, decPart, !! decPart);
            }

            function setupContractPriceInput() {
                const display = document.getElementById('contract-price-display');
                if (! display) return;

                display.addEventListener('input', (event) => {
                    let str = String(event.target.value ?? '').replace(/\./g, '');
                    const commaIndex = str.indexOf(',');
                    let intPart, decPart, hasComma;
                    if (commaIndex === -1) {
                        intPart = str.replace(/\D/g, '');
                        decPart = '';
                        hasComma = false;
                    } else {
                        intPart = str.slice(0, commaIndex).replace(/\D/g, '');
                        decPart = str.slice(commaIndex + 1).replace(/\D/g, '').slice(0, 2);
                        hasComma = true;
                    }
                    document.getElementById('contract-price').value = contractPriceRawValue(intPart, decPart);
                    display.value = formatContractPriceDisplay(intPart, decPart, hasComma);
                });
            }

            document.addEventListener('DOMContentLoaded', setupContractPriceInput);
            document.addEventListener('livewire:navigated', setupContractPriceInput);

            function updateContractQuotaVisibility() {
                const unlimitedCheckbox = document.getElementById('contract-unlimited');
                const quotaFields = document.getElementById('contract-quota-fields');
                const quotaModeSelect = document.getElementById('contract-quota-mode');
                const sharedField = document.getElementById('contract-shared-field');
                const perModuleFields = document.getElementById('contract-per-module-fields');
                const noModuleHint = document.getElementById('contract-no-module-selected-hint');

                if (! unlimitedCheckbox || ! quotaFields || ! quotaModeSelect) return;

                quotaFields.classList.toggle('hidden', unlimitedCheckbox.checked);

                const isShared = quotaModeSelect.value === '{{ \App\Models\CompanyContract::QUOTA_MODE_SHARED }}';
                sharedField.classList.toggle('hidden', ! isShared);
                if (perModuleFields) perModuleFields.classList.toggle('hidden', isShared);

                // Solo mostrar (y permitir llenar) el cupo de un módulo si de verdad está
                // marcado arriba en "Módulos que cubre este contrato" -- si no, ese cupo nunca
                // se llegaría a usar (Company::activeContractFor() solo mira módulos marcados),
                // así que mostrarlo igual solo confundiría.
                let anyChecked = false;
                document.querySelectorAll('.contract-module-checkbox').forEach((checkbox) => {
                    const wrapper = document.getElementById(`contract-${checkbox.value}-limit-wrapper`);
                    if (! wrapper) return;

                    const show = ! isShared && checkbox.checked;
                    wrapper.classList.toggle('hidden', ! show);
                    if (show) anyChecked = true;

                    if (! checkbox.checked) {
                        const input = document.getElementById(`contract-${checkbox.value}-limit`);
                        if (input) input.value = '';
                    }
                });

                if (noModuleHint) noModuleHint.classList.toggle('hidden', isShared || anyChecked);
            }

            function setupContractQuotaToggle() {
                const unlimitedCheckbox = document.getElementById('contract-unlimited');
                const quotaModeSelect = document.getElementById('contract-quota-mode');

                if (! unlimitedCheckbox || ! quotaModeSelect) return;

                unlimitedCheckbox.addEventListener('change', updateContractQuotaVisibility);
                quotaModeSelect.addEventListener('change', updateContractQuotaVisibility);
                quotaModeSelect.addEventListener('change.hs.select', updateContractQuotaVisibility);
                document.querySelectorAll('.contract-module-checkbox').forEach((checkbox) => {
                    checkbox.addEventListener('change', updateContractQuotaVisibility);
                });

                updateContractQuotaVisibility();
            }

            document.addEventListener('DOMContentLoaded', setupContractQuotaToggle);
            document.addEventListener('livewire:navigated', setupContractQuotaToggle);

            window.openContractPanel = function (contract) {
                const form = document.getElementById('contractForm');
                const methodInput = document.getElementById('contract-method');
                const label = document.getElementById('contract-panel-label');

                setContractPriceValue(contract?.price ?? '');

                const dateRangeRoot = document.querySelector('#contract-panel [data-daterange]');
                dateRangeRoot?.daterangeSetValue?.(contract?.starts_at ?? '{{ now()->format('Y-m-d') }}', contract?.ends_at ?? null);

                document.getElementById('contract-unlimited').checked = !! contract?.unlimited;
                document.getElementById('contract-shared-limit').value = contract?.shared_limit ?? '';

                const selectedModules = contract?.modules ?? [];
                document.querySelectorAll('.contract-module-checkbox').forEach((checkbox) => {
                    checkbox.checked = selectedModules.includes(checkbox.value);
                });

                @foreach ($quotaModules as $moduleKey)
                    document.getElementById('contract-{{ $moduleKey }}-limit').value = contract?.{{ $moduleKey }}_limit ?? '';
                @endforeach

                setSelectValue('contract-renewal-type', contract?.renewal_type ?? '{{ \App\Models\CompanyContract::RENEWAL_LIFETIME }}');
                setSelectValue('contract-quota-mode', contract?.quota_mode ?? '{{ \App\Models\CompanyContract::QUOTA_MODE_PER_MODULE }}');

                if (contract?.id) {
                    form.action = @json(route('admin.companies.contracts.update', ['companyId' => $company->_id, 'contractId' => '__ID__'])).replace('__ID__', contract.id);
                    methodInput.value = 'PUT';
                    label.textContent = '{{ __('Edit contract') }}';
                } else {
                    form.action = @json(route('admin.companies.contracts.store', $company->_id));
                    methodInput.value = 'POST';
                    label.textContent = '{{ __('New contract') }}';
                }

                updateContractQuotaVisibility();

                if (window.HSOverlay) {
                    HSOverlay.autoInit();
                    HSOverlay.open('#contract-panel');
                }
            };
        })();
    </script>
</x-layouts.app>
