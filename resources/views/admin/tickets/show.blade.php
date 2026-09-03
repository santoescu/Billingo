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
    {{-- El truco de "altura fija (viewport menos el padding) + flex-1 en
         el chat" solo tiene sentido de lg para arriba, donde opciones y
         chat están en columnas lado a lado (mismo presupuesto de alto para
         ambas). En mobile las dos columnas se apilan en una sola: si se
         mantuviera la altura fija, el bloque de opciones+historial se
         comía la mayoría del alto disponible y dejaba el chat aplastado.
         Por eso en mobile la página fluye normal (sin altura fija) y el
         chat usa un alto propio en vh en vez de flex-1. --}}
    <div class="flex flex-col lg:h-[calc(100dvh-4rem)]">
        @include('partials.tittle', [
            'title' => $ticket->subject,
            'subheading' => ($company?->name ?? $ticket->contact_name) . ' — ' . $ticket->module_label,
        ])

        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <a href="{{ route('admin.tickets.index') }}" class="inline-flex w-fit shrink-0 items-center gap-2 text-sm text-zinc-500 hover:text-accent dark:text-neutral-400">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                {{ __('Back to tickets') }}
            </a>

            <div class="flex min-h-0 flex-1 flex-col gap-6 lg:flex-row">
                {{-- Opciones + historial: a la izquierda en desktop (el chat
                     queda a la derecha para que el textarea, que crece con
                     el mensaje, no le tape la campana de notificaciones,
                     fixed abajo a la izquierda) -- pero abajo del chat en
                     mobile (order-2), para que lo primero que se vea sea la
                     conversación, no los selects de estado/prioridad. --}}
                <div class="order-2 w-full shrink-0 space-y-6 lg:order-none lg:w-64">
                    @if ($ticket->is_lead)
                        {{-- Un prospecto del formulario "Contáctanos" no
                             tiene cuenta -- estos son los únicos datos que
                             hay para contactarlo de vuelta (todavía no hay
                             aviso por correo, ver memoria del backlog). --}}
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-900/40 dark:bg-purple-900/10">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-purple-700 dark:text-purple-400">{{ __('Contact details') }}</p>
                            <p class="text-sm font-medium text-purple-900 dark:text-purple-200">{{ $ticket->contact_name }}</p>
                            <p class="break-words text-xs text-purple-800 dark:text-purple-300">{{ $ticket->contact_email }}</p>
                            @if ($ticket->contact_phone)
                                <p class="text-sm text-purple-800 dark:text-purple-300">{{ $ticket->contact_phone }}</p>
                            @endif
                        </div>
                    @elseif ($company)
                        {{-- Mismo estilo de tarjeta, pero con los datos ya
                             registrados de la empresa -- para no tener que
                             ir a buscarlos aparte al querer llamar/escribir
                             directo en vez de contestar solo por el hilo. --}}
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">{{ __('Contact details') }}</p>
                            <p class="text-sm font-medium text-blue-900 dark:text-blue-200">{{ $company->name }}</p>
                            @if ($company->email)
                                <p class="break-words text-xs text-blue-800 dark:text-blue-300">{{ $company->email }}</p>
                            @endif
                            @if ($company->phone)
                                <p class="text-sm text-blue-800 dark:text-blue-300">{{ $company->phone }}</p>
                            @endif
                        </div>
                    @endif

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

                <div class="order-1 flex min-h-0 max-w-3xl flex-1 flex-col gap-4 lg:order-none">
                    <div id="ticket-messages" class="h-[60vh] shrink-0 overflow-y-auto rounded-lg border border-gray-200 p-4 dark:border-neutral-700 lg:h-auto lg:min-h-0 lg:flex-1 lg:shrink [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                        @include('support.partials.messages', ['messages' => $messages, 'ownRole' => 'staff', 'otherLabel' => $company?->name ?? $ticket->contact_name, 'activities' => $chatActivities, 'activityUsers' => $activityUsers])
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

                        const instance = window.HSSelect && HSSelect.getInstance(cannedSelect);
                        if (instance) {
                            instance.setValue('');
                        } else {
                            cannedSelect.value = '';
                        }
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', initAdminTicketShowPage);
            document.addEventListener('livewire:navigated', initAdminTicketShowPage);
        </script>
    @endpush
</x-layouts.app>
