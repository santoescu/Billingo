@php
    $statusOptions = [
        \App\Models\SupportTicket::STATUS_OPEN => __('Open'),
        \App\Models\SupportTicket::STATUS_ASSIGNED => __('Assigned'),
        \App\Models\SupportTicket::STATUS_CLOSED => __('Closed'),
    ];

    $priorityOptions = [
        \App\Models\SupportTicket::PRIORITY_LOW => __('Low'),
        \App\Models\SupportTicket::PRIORITY_MEDIUM => __('Medium'),
        \App\Models\SupportTicket::PRIORITY_HIGH => __('High'),
        \App\Models\SupportTicket::PRIORITY_URGENT => __('Urgent'),
    ];
@endphp

<x-layouts.app :title="$ticket->subject">
    {{-- Mismo criterio que support/show.blade.php: todo dentro de un
         contenedor con altura fija (viewport menos el padding de
         <flux:main>) para que el chat sea lo único que scrollee. --}}
    <div class="flex h-[calc(100dvh-3rem)] flex-col lg:h-[calc(100dvh-4rem)]">
        @include('partials.tittle', [
            'title' => $ticket->subject,
            'subheading' => $company->name . ' — ' . $ticket->module_label,
        ])

        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <a href="{{ route('admin.tickets.index') }}" class="inline-flex w-fit shrink-0 items-center gap-2 text-sm text-zinc-500 hover:text-accent dark:text-neutral-400">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                {{ __('Back to tickets') }}
            </a>

            <div class="flex min-h-0 flex-1 flex-col gap-6 lg:flex-row">
                {{-- Opciones + historial: a la izquierda -- el chat queda a
                     la derecha (ver más abajo) para que el textarea, que
                     crece con el mensaje, no le tape la campana de
                     notificaciones (fixed, abajo a la izquierda). --}}
                <div class="w-full shrink-0 space-y-6 lg:w-64">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Status') }}</label>
                        <form action="{{ route('admin.tickets.status', $ticket->_id) }}" method="POST">
                            @csrf
                            <select name="status" id="ticket-status-select" class="ticket-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}'>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Priority') }}</label>
                        <form action="{{ route('admin.tickets.priority', $ticket->_id) }}" method="POST">
                            @csrf
                            <select name="priority" id="ticket-priority-select" class="ticket-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}'>
                                @foreach ($priorityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Assigned to') }}</label>
                        <form action="{{ route('admin.tickets.assign', $ticket->_id) }}" method="POST">
                            @csrf
                            <select name="assigned_to" id="ticket-assigned-select" class="ticket-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}'>
                                <option value="" @selected(! $ticket->assigned_to)>{{ __('Unassigned') }}</option>
                                @foreach ($staffUsers as $staffUser)
                                    <option value="{{ $staffUser->_id }}" @selected((string) $ticket->assigned_to === (string) $staffUser->_id)>{{ $staffUser->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div class="min-h-0 flex-1">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('History') }}</label>
                        <div class="flex max-h-64 flex-col gap-3 overflow-y-auto pe-1 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                            @forelse ($activities as $activity)
                                @php
                                    $actorName = $activityUsers->get((string) $activity->user_id)?->name ?? __('Someone');
                                    $isStatusChange = $activity->action === \App\Models\SupportTicketActivity::ACTION_STATUS;
                                    $isPriorityChange = $activity->action === \App\Models\SupportTicketActivity::ACTION_PRIORITY;

                                    if ($isStatusChange) {
                                        $activityDescription = __(':actor changed the status:', ['actor' => $actorName]);
                                    } elseif ($isPriorityChange) {
                                        $activityDescription = __(':actor changed the priority:', ['actor' => $actorName]);
                                    } else {
                                        $toName = $activity->to ? ($activityUsers->get((string) $activity->to)?->name ?? __('Someone')) : null;
                                        $fromName = $activity->from ? ($activityUsers->get((string) $activity->from)?->name ?? __('Someone')) : null;
                                        $activityDescription = $toName
                                            ? __(':actor assigned the ticket to :to', ['actor' => $actorName, 'to' => $toName])
                                            : __(':actor removed the assignment (was :from)', ['actor' => $actorName, 'from' => $fromName ?? __('nobody')]);
                                    }
                                @endphp
                                <div class="text-xs">
                                    <p class="text-zinc-600 dark:text-neutral-300">{{ $activityDescription }}</p>
                                    @if ($isStatusChange)
                                        <div class="mt-1 flex items-center gap-1.5">
                                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ \App\Models\SupportTicket::statusBadgeClasses($activity->from) }}">{{ \App\Models\SupportTicket::statusLabel($activity->from) }}</span>
                                            <svg class="size-3 shrink-0 text-zinc-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ \App\Models\SupportTicket::statusBadgeClasses($activity->to) }}">{{ \App\Models\SupportTicket::statusLabel($activity->to) }}</span>
                                        </div>
                                    @elseif ($isPriorityChange)
                                        <div class="mt-1 flex items-center gap-1.5">
                                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ \App\Models\SupportTicket::priorityBadgeClasses($activity->from) }}">{{ \App\Models\SupportTicket::priorityLabel($activity->from) }}</span>
                                            <svg class="size-3 shrink-0 text-zinc-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ \App\Models\SupportTicket::priorityBadgeClasses($activity->to) }}">{{ \App\Models\SupportTicket::priorityLabel($activity->to) }}</span>
                                        </div>
                                    @endif
                                    <p class="mt-1 text-zinc-400 dark:text-neutral-500">{{ $activity->created_at?->diffForHumans() }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 dark:text-neutral-500">{{ __('No activity yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 max-w-3xl flex-1 flex-col gap-4">
                    <div id="ticket-messages" class="min-h-0 flex-1 overflow-y-auto rounded-lg border border-gray-200 p-4 dark:border-neutral-700 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                        @include('support.partials.messages', ['messages' => $messages, 'ownRole' => 'staff', 'otherLabel' => $company->name])
                    </div>

                    <form action="{{ route('admin.tickets.reply', $ticket->_id) }}" method="POST" class="shrink-0">
                        @csrf
                        @include('support.partials.composer', ['allowInternalNote' => true, 'cannedResponses' => $cannedResponses])
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function initAdminTicketShowPage() {
                const messages = document.getElementById('ticket-messages');
                if (messages) {
                    messages.scrollTop = messages.scrollHeight;
                }

                document.querySelectorAll('.ticket-auto-submit').forEach((select) => {
                    if (select.dataset.bound) return;
                    select.dataset.bound = 'true';
                    select.addEventListener('change', () => select.closest('form')?.submit());
                });

                const cannedSelect = document.getElementById('canned-response-select');
                const textarea = document.getElementById('composer-textarea');
                if (cannedSelect && textarea && ! cannedSelect.dataset.bound) {
                    cannedSelect.dataset.bound = 'true';
                    cannedSelect.addEventListener('change', () => {
                        if (! cannedSelect.value) return;

                        textarea.value = textarea.value ? textarea.value + '\n' + cannedSelect.value : cannedSelect.value;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.focus();
                        cannedSelect.value = '';
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', initAdminTicketShowPage);
            document.addEventListener('livewire:navigated', initAdminTicketShowPage);
        </script>
    @endpush
</x-layouts.app>
