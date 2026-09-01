@php
    $dianConfigured = $company->dian_pin && $company->dian_software_id;
    
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Invoice (export)'),
        '03' => __('Invoice (contingency, paper)'),
        '04' => __('Invoice (DIAN contingency)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
        'FV' => __('Sales invoice'),
        'COT' => __('Quotation'),
    ];

    $basicSelectConfig = \App\Support\SelectConfig::basic();

    $hasInvoicing = array_key_exists('invoicing', session('selected_company.modules', []));
@endphp

<x-layouts.app :title="__('Resolutions')">
    @include('partials.tittle', [
        'title' => __('Resolutions'),
        'subheading' => __('The numbering ranges authorized for this company.'),
    ])

    @unless ($dianConfigured)
        <div class="mb-6 rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
            {{ __('Configure the pin and software ID for this company first.') }}
        </div>
    @endunless

    <div class="flex flex-col gap-6">
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="py-3 px-4 flex justify-end items-center gap-3">
                        <flux:button id="dian-add-prefix-btn" type="button" variant="filled" icon="plus" data-hs-overlay="#note-numbering-modal">
                            {{ __('Add prefixes') }}
                        </flux:button>
                        @if ($hasInvoicing)
                            <form method="POST" action="{{ route('dian.resolutions.sync') }}">
                                @csrf
                                <flux:button id="dian-sync-btn" type="submit" variant="primary" icon="arrow-path" :disabled="! $dianConfigured">
                                    {{ __('Sync from DIAN') }}
                                </flux:button>
                            </form>
                        @endif
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Document type') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Resolution number') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Resolution date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Prefix') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Range') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Next number') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Valid') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($resolutions as $resolution)
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            {{ $documentTypeLabels[$resolution->document_type ?? '01'] ?? $resolution->document_type }}
                                            @if ($resolution->is_fixed_test)
                                                <span class="ml-1 rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-400">{{ __('DIAN fixed test') }}</span>
                                            @elseif ($resolution->is_manual)
                                                <span class="ml-1 rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-neutral-700 dark:text-neutral-300">{{ __('Manual') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $resolution->resolution_number ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $resolution->resolution_date ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $resolution->prefix ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            {{ $resolution->range_from }} - {{ $resolution->range_to ?? __('no limit') }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $resolution->current_number }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            {{ $resolution->valid_from?->format('Y-m-d') }} — {{ $resolution->valid_to?->format('Y-m-d') }}
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            @if ($resolution->isExpired())
                                                <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-400">{{ __('Expired') }}</span>
                                            @elseif ($resolution->isExhausted())
                                                <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-400">{{ __('Exhausted') }}</span>
                                            @else
                                                <span class="rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-400">{{ __('Active') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            @if ($resolution->is_manual)
                                                <button
                                                    type="button"
                                                    class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400"
                                                    title="{{ __('Remove') }}"
                                                    onclick="confirmDeleteResolution('{{ route('dian.resolutions.destroy', $resolution->_id) }}')"
                                                >
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('Resolutions')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="note-numbering-modal" class="hs-overlay hs-overlay-open:translate-x-0 hidden translate-x-full fixed top-0 end-0 transition-all duration-300 transform h-full max-w-lg w-full z-80 bg-white border-s border-gray-200 dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-labelledby="note-numbering-modal-label">
        <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
            <h3 id="note-numbering-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('Add prefixes') }}</h3>
            <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#note-numbering-modal">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto p-4 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">{{ __('The DIAN does not authorize numbering ranges for credit/debit notes, non-electronic sales invoices, or quotations: define your own prefix and starting number.') }}</p>
            <form method="POST" action="{{ route('dian.resolutions.manual') }}" class="space-y-4">
                @csrf
                <div id="dian-manual-doctype-field">
                    <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Document type') }}</label>
                    <select name="document_type" data-hs-select='{!! $basicSelectConfig !!}' class="hidden">
                        <option value="FV">{{ __('Sales invoice') }}</option>
                        <option value="COT">{{ __('Quotation') }}</option>
                        <option value="91">91 - {{ __('Credit note') }}</option>
                        <option value="92">92 - {{ __('Debit note') }}</option>
                    </select>
                </div>
                <flux:input id="dian-manual-prefix" name="prefix" :label="__('Prefix')" maxlength="10" required />
                <flux:input id="dian-manual-range_from" name="range_from" type="number" min="1" :label="__('Starting number')" required />
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="filled" data-hs-overlay="#note-numbering-modal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" id="dian-manual-submit-btn" variant="primary">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirm-delete-resolution-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="confirm-delete-resolution-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-sm sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <div class="p-4">
                    <h3 id="confirm-delete-resolution-label" class="font-bold text-gray-800 dark:text-white mb-1">{{ __('Remove numbering') }}</h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">{{ __('Are you sure?') }}</p>
                    <form id="confirm-delete-resolution-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <div class="flex justify-end gap-3">
                            <flux:button type="button" variant="filled" data-hs-overlay="#confirm-delete-resolution-modal">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="danger">{{ __('Remove') }}</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDeleteResolution(destroyUrl) {
            document.getElementById('confirm-delete-resolution-form').action = destroyUrl;
            if (window.HSOverlay) {
                HSOverlay.autoInit();
                HSOverlay.open('#confirm-delete-resolution-modal');
            }
        }
    </script>
</x-layouts.app>
