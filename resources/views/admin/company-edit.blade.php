@php
    $moduleCatalog = config('modules');
    $activeModules = $company->modules ?? [];

    $basicSelectConfig = \App\Support\SelectConfig::basic(__('No access'));
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
    </div>
    <!-- End Tab Content -->

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
        })();
    </script>
</x-layouts.app>
