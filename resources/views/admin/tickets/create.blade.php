@php
    $companySelectConfig = \App\Support\SelectConfig::searchable(__('Select a company...'), __('Search...'));
@endphp

<x-layouts.app :title="__('New ticket')">
    @include('partials.tittle', [
        'title' => __('New ticket'),
        'subheading' => __('Open a request on behalf of a company -- e.g. a follow-up or notice that starts from this side.'),
    ])

    @include('admin.tickets.partials.tabs', ['activeTab' => 'tickets'])

    <form method="POST" action="{{ route('admin.tickets.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Company') }}</label>
            <select name="company_id" data-hs-select='{!! $companySelectConfig !!}' class="hidden">
                @foreach ($companies as $company)
                    <option value="{{ $company->_id }}" @selected(old('company_id') === (string) $company->_id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Module') }}</label>
            <select name="module" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                <option value="general" @selected(old('module', 'general') === 'general')>{{ __('General') }}</option>
                @foreach ($modules as $key => $module)
                    <option value="{{ $key }}" @selected(old('module') === $key)>{{ $module['name'] }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ __('This decides who at the company gets notified.') }}</p>
        </div>

        <flux:input name="subject" :label="__('Subject')" value="{{ old('subject') }}" required />

        <flux:textarea name="body" :label="__('Message')" rows="4" required>{{ old('body') }}</flux:textarea>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send') }}</flux:button>
        </div>
    </form>
</x-layouts.app>
