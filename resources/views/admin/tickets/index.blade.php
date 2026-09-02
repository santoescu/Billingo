<x-layouts.app :title="__('Support tickets')">
    @include('partials.tittle', [
        'title' => __('Support tickets'),
        'subheading' => __('Requests, complaints and claims filed by companies, across all of them.'),
    ])

    <div class="flex flex-col gap-4">
        <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Status') }}</label>
                <select name="status" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    <option value="abierto" @selected(($filters['status'] ?? '') === 'abierto')>{{ __('Open') }}</option>
                    <option value="asignado" @selected(($filters['status'] ?? '') === 'asignado')>{{ __('Assigned') }}</option>
                    <option value="cerrado" @selected(($filters['status'] ?? '') === 'cerrado')>{{ __('Closed') }}</option>
                </select>
            </div>

            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Module') }}</label>
                <select name="module" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    <option value="general" @selected(($filters['module'] ?? '') === 'general')>{{ __('General') }}</option>
                    @foreach ($modules as $key => $module)
                        <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $module['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-56">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Company') }}</label>
                <select name="company_id" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::searchable(__('All'), __('Search...')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($companies as $filterCompany)
                        <option value="{{ $filterCompany->_id }}" @selected(($filters['company_id'] ?? '') === (string) $filterCompany->_id)>{{ $filterCompany->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Assigned to') }}</label>
                <select name="assigned_to" class="ticket-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($staffUsers as $staffUser)
                        <option value="{{ $staffUser->_id }}" @selected(($filters['assigned_to'] ?? '') === (string) $staffUser->_id)>{{ $staffUser->name }}</option>
                    @endforeach
                </select>
            </div>

            @if (array_filter($filters))
                <a href="{{ route('admin.tickets.index') }}" class="text-sm text-zinc-500 hover:text-accent dark:text-neutral-400">{{ __('Clear filters') }}</a>
            @endif
        </form>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-between items-center gap-4">
                        <div class="relative max-w-xs">
                            <label class="sr-only">{{ __('Search') }}</label>
                            <flux:input type="text" name="hs-table-with-pagination-search" id="hs-table-with-pagination-search" icon="magnifying-glass" placeholder="{{ __('Search') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore />
                        </div>

                        <a href="{{ route('admin.tickets.create') }}">
                            <flux:button type="button" variant="primary" icon="plus">{{ __('New ticket') }}</flux:button>
                        </a>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="ticketsTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Module') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subject') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Priority') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Assigned to') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($tickets as $ticket)
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            <span class="inline-flex items-center gap-1.5">
                                                @if ($ticket->is_unread_for_staff)
                                                    <span class="size-1.5 shrink-0 rounded-full bg-accent" title="{{ __('Unread') }}"></span>
                                                @endif
                                                {{ $ticket->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $ticket->company_name ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            @if ($ticket->module && $ticket->module !== 'general')
                                                @include('panel.partials.module-badge', ['module' => $ticket->module])
                                            @else
                                                <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ __('General') }}</span>
                                            @endif
                                        </td>
                                        <td @class(['px-4 py-4 text-sm break-words dark:text-neutral-200', 'font-semibold text-gray-900' => $ticket->is_unread_for_staff, 'font-medium text-gray-800' => ! $ticket->is_unread_for_staff])>{{ $ticket->subject }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ticket->priority_badge_classes }}">{{ $ticket->priority_label }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ticket->status_badge_classes }}">{{ $ticket->status_label }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $ticket->assignee_name ?? __('Unassigned') }}</td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <a href="{{ route('admin.tickets.show', $ticket->_id) }}" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('requests')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            function initAdminTicketsPage() {
                const table = initWorkflowDataTable('#ticketsTable', '#hs-table-with-pagination-search');
                table.order([]).draw();

                document.querySelectorAll('.ticket-filter-auto-submit').forEach((select) => {
                    if (select.dataset.bound) return;
                    select.dataset.bound = 'true';
                    select.addEventListener('change', () => select.closest('form')?.submit());
                });
            }

            document.addEventListener('DOMContentLoaded', initAdminTicketsPage);
            document.addEventListener('livewire:navigated', initAdminTicketsPage);
        </script>
    @endpush
</x-layouts.app>
