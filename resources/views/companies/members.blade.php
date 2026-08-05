@php
    // El owner y cualquier 'administrador' de un módulo pueden gestionar miembros.
    $canManage = \App\Models\User::hasCompanyAdminAccess(
        session('selected_company.role'),
        collect(session('selected_company.modules', []))->map(fn ($role, $module) => ['module' => $module, 'role' => $role])->values()->all()
    );
    $activeModules = $company->modules ?? [];
    $moduleCatalog = config('modules');

    $basicSelectConfig = \App\Support\SelectConfig::basic(__('No access'));
@endphp

<x-layouts.app :title="__('Members')">
    @include('partials.tittle', [
        'title' => __('Members'),
        'subheading' => __('Companies you belong to and your role in each one.'),
    ])

    <div class="flex flex-col">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        @if ($canManage)
                            <flux:button variant="primary" icon="plus" data-hs-overlay="#add-member">{{ __('Add member') }}</flux:button>
                        @endif
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="membersTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Email') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Modules') }}</th>
                                    @if ($canManage)
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($members as $member)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $member->name }}</td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $member->email }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
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
                                        @if ($canManage)
                                            <td class="px-6 py-4 flex justify-center gap-2">
                                                @if ($member->membership->role !== 'owner')
                                                    <flux:button
                                                        size="sm"
                                                        variant="primary"
                                                        icon="pencil-square"
                                                        onclick="openEditMemberModal({!! Illuminate\Support\Js::from($member) !!})"
                                                    ></flux:button>

                                                    <form action="{{ route('companies.members.destroy', $member->_id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <flux:button size="sm" variant="danger" icon="trash" type="submit"></flux:button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
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

                <form action="{{ route('companies.members.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label for="member-email" class="block mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Email') }}</label>
                        <div class="relative">
                            <input type="email" id="member-email" name="email" required class="ps-10 pe-3 py-2 h-10 block w-full border rounded-lg text-base sm:text-sm shadow-xs appearance-none bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-400 border-zinc-200 border-b-zinc-300/80 dark:border-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent">
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
                <h3 id="edit-member-label" class="font-bold text-gray-800 dark:text-white">{{ __('Members') }}</h3>
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

                    document.getElementById('editMemberForm').action = `/companies/members/${member.id}`;

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
    @endif

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                initWorkflowDataTable('#membersTable', '#hs-table-with-pagination-search');
            });
            document.addEventListener('livewire:navigated', function () {
                initWorkflowDataTable('#membersTable', '#hs-table-with-pagination-search');
            });
        </script>
    @endpush
</x-layouts.app>
