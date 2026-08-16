@php
    $isClient = $role === 'cliente';
    $storeRoute = $isClient ? 'clients.store' : 'providers.store';
    $updateRouteBase = $isClient ? 'clients.update' : 'providers.update';
    $destroyRouteBase = $isClient ? 'clients.destroy' : 'providers.destroy';

    $identificationTypes = [
        '11' => __('Civil registry'),
        '12' => __('Identity card'),
        '13' => __('Citizenship card'),
        '21' => __('Foreigner card'),
        '22' => __('Foreigner ID card'),
        '31' => __('NIT'),
        '41' => __('Passport'),
        '42' => __('Foreign identification document'),
        '47' => __('PEP (Special Permanence Permit)'),
        '48' => __('PPT (Temporary Protection Permit)'),
        '50' => __('NIT from another country'),
        '91' => __('NUIP'),
    ];
@endphp

<x-layouts.app :title="$isClient ? __('Clients') : __('Providers')">
    @include('partials.tittle', [
        'title' => $isClient ? __('Clients') : __('Providers'),
        'subheading' => $isClient
            ? __('Everyone you issue invoices to.')
            : __('Everyone you receive documents from.'),
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

                        <flux:button variant="primary" icon="plus" onclick="openThirdPartyPanel()">
                            {{ $isClient ? __('New client') : __('New provider') }}
                        </flux:button>
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="thirdPartiesTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Email') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Phone') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($thirdParties as $thirdParty)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $thirdParty->name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->identificacion }}{{ $thirdParty->dv ? '-'.$thirdParty->dv : '' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->email ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $thirdParty->phone ?? '—' }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex justify-end gap-1">
                                                <button type="button" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" onclick="openThirdPartyPanel({!! Illuminate\Support\Js::from($thirdParty) !!})">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                        <path d="m15 5 4 4"></path>
                                                    </svg>
                                                </button>

                                                <form action="{{ route($destroyRouteBase, $thirdParty->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-red-400" aria-label="{{ __('Delete') }}">
                                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('third-parties.partials.form-panel', ['panelLabel' => $isClient ? __('Client') : __('Provider')])

    @include('partials.datatable-pagination')

    @push('scripts')
        @include('third-parties.partials.form-panel-script')

        <script>
            document.addEventListener('DOMContentLoaded', () => initWorkflowDataTable('#thirdPartiesTable', '#hs-table-with-pagination-search'));
            document.addEventListener('livewire:navigated', () => initWorkflowDataTable('#thirdPartiesTable', '#hs-table-with-pagination-search'));
        </script>

        <x-dian-acquirer-lookup-script />
    @endpush
</x-layouts.app>
