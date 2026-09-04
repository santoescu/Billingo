@php
    $nitIdentificationType = '31';

    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Electronic sales invoice (export)'),
        '03' => __('Electronic transmission instrument (type 03)'),
        '04' => __('Electronic sales invoice (type 04)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];
@endphp

@forelse ($documentos as $documento)
    @php
        $customerParty = $documento->payload['accounting_customer_party'] ?? [];
        $customerName = $documento->cliente?->name ?? ($customerParty['razon_social'] ?? null);
        $customerIdentification = $customerParty['identificacion'] ?? null;
        $customerDv = $customerParty['tipo_identificacion'] === $nitIdentificationType ? ($customerParty['dv'] ?? null) : null;
    @endphp
    <tr>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            <div>EMI: {{ $documento->issue_date?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</div>
            <div>EXP: {{ $documento->fecha_expedicion?->setTimezone('America/Bogota')->format('Y-m-d H:i') ?? '—' }}</div>
        </td>
        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $documento->numeral }}</td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            {{ $documentTypeLabels[$documento->tipo_documento ?? ''] ?? $documento->tipo_documento }}
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
            <div class="text-gray-800 dark:text-neutral-200">{{ $customerName ?? '—' }}</div>
            <div>{{ $customerIdentification ?? '—' }}{{ $customerDv ? '-' . $customerDv : '' }}</div>
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $documento->total_formatted }}</td>
        <td class="px-4 py-4 text-sm">
            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $documento->status_badge_classes }}">{{ $documento->status_label }}</span>
        </td>
        <td class="px-4 py-4 text-end text-sm">
            <div class="flex justify-end items-center gap-3">
                @if ($documento->uuid)
                    <a href="{{ route('documents.invoice-preview', $documento->_id) }}" target="_blank" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View PDF') }}" title="{{ __('View PDF') }}">
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                    </a>
                @endif
                <a href="{{ route('documents.show', $documento->_id) }}" class="document-view-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                @if (in_array($documento->status, [\App\Models\DocumentoEmitido::STATUS_PENDING, \App\Models\DocumentoEmitido::STATUS_REJECTED], true))
                    <div class="hs-dropdown [--auto-close:true] relative inline-flex">
                        <button type="button" class="hs-dropdown-toggle flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('More actions') }}">
                            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                        </button>
                        <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 opacity-0 hidden transition-[opacity,margin] duration mt-2 z-50 bg-white border border-zinc-200 rounded-lg shadow-xl p-1 flex items-center gap-1 dark:bg-neutral-800 dark:border-neutral-700">
                            <button type="button" class="document-retry-btn flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" data-url="{{ route('documents.retry', $documento->_id) }}" aria-label="{{ __('Validate') }}" title="{{ __('Validate') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>
                            @if ($documento->status === \App\Models\DocumentoEmitido::STATUS_REJECTED)
                                <a href="{{ route('documents.create', ['edit_document_id' => $documento->_id]) }}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Correct and resend') }}" title="{{ __('Correct and resend') }}">
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                        <path d="m15 5 4 4"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </td>
    </tr>
@empty
@endforelse
