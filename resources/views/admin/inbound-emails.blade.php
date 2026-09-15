<x-layouts.app :title="__('Inbound emails')">
    @include('partials.tittle', [
        'title' => __('Inbound emails'),
        'subheading' => __('Raw emails SES has stored in the reception bucket -- useful to read confirmation codes or debug why a document was not ingested.'),
    ])

    @if (! $bucket)
        <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
            {{ __('Email reception is not configured (missing AWS_SES_INBOUND_BUCKET).') }}
        </div>
    @else
        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subject') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Key') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Size') }}</th>
                                    <th scope="col" class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($objects as $object)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">{{ $object['LastModified']?->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200 max-w-xs truncate" title="{{ $object['Subject'] ?: '' }}">{{ $object['Subject'] ?: '—' }}</td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800 dark:text-neutral-200 break-all">{{ $object['Key'] }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-neutral-400 whitespace-nowrap">{{ number_format(($object['Size'] ?? 0) / 1024, 1) }} KB</td>
                                        <td class="px-4 py-3 text-end text-sm">
                                            <a href="{{ route('admin.inbound-emails.show', ['key' => $object['Key']]) }}" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('emails')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
