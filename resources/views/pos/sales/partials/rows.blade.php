@php
    $nitIdentificationType = '31';
@endphp

@forelse ($documentos as $documento)
    @php
        $customerParty = $documento->payload['accounting_customer_party'] ?? [];
        $customerName = $documento->cliente?->name ?? ($customerParty['razon_social'] ?? null);
        $customerIdentification = $customerParty['identificacion'] ?? null;
        $customerDv = $customerParty['tipo_identificacion'] === $nitIdentificationType ? ($customerParty['dv'] ?? null) : null;
    @endphp
    <tr>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $documento->issue_date?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</td>
        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $documento->numeral }}</td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            <div class="text-gray-800 dark:text-neutral-200">{{ $customerName ?? '—' }}</div>
            <div>{{ $customerIdentification ?? '—' }}{{ $customerDv ? '-' . $customerDv : '' }}</div>
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $documento->total_formatted }}</td>
        <td class="px-4 py-4 text-sm">
            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->status_badge_classes }}">{{ $documento->status_label }}</span>
        </td>
        <td class="px-4 py-4 text-end text-sm">
            <div class="flex justify-end gap-1">
                <a href="{{ route('pos.sales.receipt-preview', $documento->_id) }}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View PDF') }}" title="{{ __('View PDF') }}">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                </a>
                <a href="{{ route('pos.sales.show', $documento->_id) }}" class="pos-sale-view-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
            </div>
        </td>
    </tr>
@empty
@endforelse
