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
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            {{ $ticket->company_name ?? '—' }}
            @if ($ticket->is_lead)
                <span class="ms-1 rounded-md bg-purple-100 px-1.5 py-0.5 text-[11px] font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Lead') }}</span>
            @endif
        </td>
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
@endforelse
