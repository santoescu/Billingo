@php
    $companySelectConfig = \App\Support\SelectConfig::searchable(__('Select companies...'), __('Search...'));
    $searchableSelectConfig = \App\Support\SelectConfig::searchable(__('Select users...'), __('Search...'));

    $oldUserIds = old('user_ids', []);
    $oldCompanyIds = old('company_ids', []);
    $recipients = old('recipients', 'all');
@endphp

<x-layouts.app :title="__('Send notification')">
    @include('partials.tittle', [
        'title' => __('Send notification'),
        'subheading' => __('Send a message to all users or to a specific selection.'),
    ])

    <form method="POST" action="{{ route('admin.notifications.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Recipients') }}</label>

            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="radio" name="recipients" value="all" class="accent-accent" @checked($recipients === 'all') id="recipients_all">
                    {{ __('All users') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="radio" name="recipients" value="specific" class="accent-accent" @checked($recipients === 'specific') id="recipients_specific">
                    {{ __('Specific users') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="radio" name="recipients" value="companies" class="accent-accent" @checked($recipients === 'companies') id="recipients_companies">
                    {{ __('Members of selected companies') }}
                </label>
            </div>
        </div>

        <div id="specific_users_wrapper" class="{{ $recipients === 'specific' ? '' : 'hidden' }}">
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Users') }}</label>
            <select
                name="user_ids[]"
                multiple
                data-hs-select='{!! $searchableSelectConfig !!}'
                class="hidden"
            >
                @foreach ($users as $listedUser)
                    <option value="{{ $listedUser->_id }}" @selected(in_array((string) $listedUser->_id, $oldUserIds))>
                        {{ $listedUser->name }} ({{ $listedUser->email }})
                    </option>
                @endforeach
            </select>
        </div>

        <div id="specific_companies_wrapper" class="{{ $recipients === 'companies' ? '' : 'hidden' }}">
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Companies') }}</label>
            <select
                name="company_ids[]"
                multiple
                data-hs-select='{!! $companySelectConfig !!}'
                class="hidden"
            >
                @foreach ($companies as $company)
                    <option value="{{ $company->_id }}" @selected(in_array((string) $company->_id, $oldCompanyIds))>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <flux:input name="title" :label="__('Title')" value="{{ old('title') }}" required />

        <flux:textarea name="body" :label="__('Message')" rows="4" required>{{ old('body') }}</flux:textarea>

        <flux:input name="url" :label="__('Link (optional)')" placeholder="https://..." value="{{ old('url') }}" />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send') }}</flux:button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var radios = document.querySelectorAll('input[name="recipients"]');
            var usersWrapper = document.getElementById('specific_users_wrapper');
            var companiesWrapper = document.getElementById('specific_companies_wrapper');

            function toggle() {
                var checked = document.querySelector('input[name="recipients"]:checked');
                var value = checked ? checked.value : 'all';

                usersWrapper.classList.toggle('hidden', value !== 'specific');
                companiesWrapper.classList.toggle('hidden', value !== 'companies');
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', toggle);
            });
        });
    </script>
</x-layouts.app>
