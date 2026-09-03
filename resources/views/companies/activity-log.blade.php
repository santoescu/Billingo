@php
    $modelOptions = [
        'Product' => __('Product'),
        'ThirdParty' => __('Client/Provider'),
        'Warehouse' => __('Warehouse'),
        'PriceType' => __('Price type'),
        'PaymentMethod' => __('Payment method'),
        'Seller' => __('Seller'),
        'Resolution' => __('Resolution'),
        'DianCertificate' => __('Digital certificate'),
        'CatalogLink' => __('Catalog link'),
        'CompanyMember' => __('Member'),
    ];

    $actionOptions = [
        \App\Models\ActivityLog::ACTION_CREATED => __('Created'),
        \App\Models\ActivityLog::ACTION_UPDATED => __('Updated'),
        \App\Models\ActivityLog::ACTION_DELETED => __('Deleted'),
    ];
@endphp

<x-layouts.app :title="__('Activity log')">
    @include('partials.tittle', [
        'title' => __('Activity log'),
        'subheading' => __('Everything your team created, edited or deleted in this company.'),
    ])

    <div class="flex flex-col gap-4">
        <form method="GET" action="{{ route('companies.activity-log.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('User') }}</label>
                <select name="user_id" class="activity-log-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->_id }}" @selected(($filters['user_id'] ?? '') === (string) $member->_id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Type') }}</label>
                <select name="model" class="activity-log-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($modelOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['model'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-neutral-400">{{ __('Action') }}</label>
                <select name="action" class="activity-log-filter-auto-submit hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('All')) !!}'>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($actionOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-64">
                <x-date-range-picker name-from="from" name-to="to" :label="__('Date range')" :value-from="$filters['from'] ?? null" :value-to="$filters['to'] ?? null" :allow-open-end="true" :floating="true" />
            </div>

            <flux:button type="submit" variant="filled">{{ __('Filter') }}</flux:button>
        </form>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    @include('activity-log.partials.table', ['logs' => $logs, 'userNames' => $userNames])
                </div>
            </div>
        </div>
    </div>

    <x-date-range-picker-script />

    @push('scripts')
        <script>
            function initActivityLogFilters() {
                document.querySelectorAll('.activity-log-filter-auto-submit').forEach((select) => {
                    if (select.dataset.bound) return;
                    select.dataset.bound = 'true';
                    select.addEventListener('change', () => select.closest('form')?.submit());
                });
            }

            document.addEventListener('DOMContentLoaded', initActivityLogFilters);
            document.addEventListener('livewire:navigated', initActivityLogFilters);
        </script>
    @endpush
</x-layouts.app>
