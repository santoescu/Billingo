<x-layouts.app :title="__('Inbound email')">
    @include('partials.tittle', [
        'title' => __('Inbound email'),
        'subheading' => $key,
    ])

    <a href="{{ route('admin.inbound-emails.index') }}" class="mb-4 inline-block text-sm font-medium text-accent hover:underline">{{ __('← Back') }}</a>

    <div class="rounded-lg border border-gray-200 dark:border-neutral-700 overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-200 dark:bg-neutral-700">
            <div class="bg-white dark:bg-neutral-800 p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-neutral-400">{{ __('From') }}</div>
                <div class="text-sm text-gray-800 dark:text-neutral-200 break-all">{{ $from ?: '—' }}</div>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-neutral-400">{{ __('To') }}</div>
                <div class="text-sm text-gray-800 dark:text-neutral-200 break-all">{{ $to ?: '—' }}</div>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-neutral-400">{{ __('Subject') }}</div>
                <div class="text-sm text-gray-800 dark:text-neutral-200 break-all">{{ $subject ?: '—' }}</div>
            </div>
            <div class="bg-white dark:bg-neutral-800 p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-neutral-400">{{ __('Date') }}</div>
                <div class="text-sm text-gray-800 dark:text-neutral-200">{{ $date ?: '—' }}</div>
            </div>
        </div>

        @if ($attachments->isNotEmpty())
            <div class="p-4 border-t border-gray-200 dark:border-neutral-700">
                <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2">{{ __('Attachments') }}</div>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($attachments as $filename)
                        <li class="rounded-md bg-zinc-50 dark:bg-white/5 border border-zinc-200 dark:border-white/10 px-2 py-1 text-xs font-mono text-zinc-600 dark:text-neutral-300">{{ $filename }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="p-4 border-t border-gray-200 dark:border-neutral-700">
            <div class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2">{{ __('Body') }}</div>
            <pre class="whitespace-pre-wrap break-words text-sm text-gray-700 dark:text-neutral-300 bg-zinc-50 dark:bg-white/5 rounded-md p-3 max-h-[32rem] overflow-y-auto">{{ $text ?: '—' }}</pre>
        </div>
    </div>
</x-layouts.app>
