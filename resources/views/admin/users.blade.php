<x-layouts.app :title="__('All users')">
    @include('partials.tittle', [
        'title' => __('All users'),
        'subheading' => __('Grant or revoke superadmin access for any user.'),
    ])

    <div aria-hidden="true" style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;">
        <input type="text" name="fake-username" tabindex="-1" autocomplete="username">
        <input type="password" name="fake-password" tabindex="-1" autocomplete="new-password">
    </div>

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
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700" id="usersTable">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-500">{{ __('Name') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-500">{{ __('Email') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-500">{{ __('Companies') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-neutral-500">{{ __('Superadmin') }}</th>
                                    <th scope="col" class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                                @foreach ($users as $listedUser)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">{{ $listedUser->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">{{ $listedUser->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($listedUser->company_names as $companyName)
                                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-200">
                                                        {{ $companyName }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-neutral-400">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($listedUser->isGlobalAdmin())
                                                <span class="rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-400">{{ __('Yes') }}</span>
                                            @else
                                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-neutral-700 dark:text-neutral-300">{{ __('No') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            @if ((string) $listedUser->_id !== (string) auth()->id())
                                                <form method="POST" action="{{ route('admin.users.toggle-superadmin', $listedUser->_id) }}">
                                                    @csrf
                                                    <flux:button type="submit" size="sm" variant="{{ $listedUser->isGlobalAdmin() ? 'danger' : 'primary' }}">
                                                        {{ $listedUser->isGlobalAdmin() ? __('Revoke') : __('Grant') }}
                                                    </flux:button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-neutral-500">{{ __('This is you') }}</span>
                                            @endif
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
                initWorkflowDataTable('#usersTable', '#hs-table-with-pagination-search');
            });
        </script>
    @endpush
</x-layouts.app>
