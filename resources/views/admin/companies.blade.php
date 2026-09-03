@php
    $moduleCatalog = config('modules');
@endphp

<x-layouts.app :title="__('All companies')">
    @include('partials.tittle', [
        'title' => __('All companies'),
        'subheading' => __('Every company registered in the system, regardless of owner.'),
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
                    </div>

                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="companiesTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Identification') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Owner') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Modules') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($companies as $company)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $company->name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $company->identificacion }}{{ $company->dv ? '-'.$company->dv : '' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $company->owner_name ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($company->modules ?? [] as $moduleKey)
                                                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $moduleCatalog[$moduleKey]['badge_classes'] ?? 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-200' }}">
                                                        {{ $moduleCatalog[$moduleKey]['name'] ?? $moduleKey }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-neutral-400">{{ __('No access') }}</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            <span @class([
                                                'rounded-md px-2 py-0.5 text-xs font-medium',
                                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' => $company->status !== 'inactive',
                                                'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-300' => $company->status === 'inactive',
                                            ])>
                                                {{ $company->status === 'inactive' ? __('Inactive') : __('Active') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <a href="{{ route('admin.companies.edit', $company->_id) }}" class="flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}">
                                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                    <path d="m15 5 4 4"></path>
                                                </svg>
                                            </a>
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

    @include('partials.datatable-pagination')

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                initWorkflowDataTable('#companiesTable', '#hs-table-with-pagination-search');
            });
        </script>
    @endpush
</x-layouts.app>
