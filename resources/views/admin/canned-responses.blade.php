<x-layouts.app :title="__('Quick reply templates')">
    @include('partials.tittle', [
        'title' => __('Quick reply templates'),
        'subheading' => __('Reusable snippets the team can insert while replying to a support ticket.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="flex justify-end">
            <button type="button" id="canned-response-add-btn"
                class="inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent/90 focus:outline-hidden">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                {{ __('New template') }}
            </button>
        </div>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Title') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Message') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($cannedResponses as $canned)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $canned->title }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 break-words dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($canned->body, 120) }}</td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <form action="{{ route('admin.canned-responses.destroy', $canned->_id) }}" method="POST" onsubmit="return window.appConfirmDialog.open(event, this, '{{ __('This action cannot be undone.') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('templates')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: nueva plantilla -->
    <div id="canned-response-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="canned-response-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form action="{{ route('admin.canned-responses.store') }}" method="POST">
                    @csrf
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 id="canned-response-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('New template') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-4">
                        @if ($errors->any())
                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <flux:input name="title" :label="__('Title')" value="{{ old('title') }}" required />
                        <flux:textarea name="body" :label="__('Message')" rows="4" required>{{ old('body') }}</flux:textarea>
                    </div>
                    <div class="p-4 pt-0 flex justify-end gap-2">
                        <flux:button type="button" variant="filled" onclick="window.HSOverlay && HSOverlay.close('#canned-response-modal')">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Create template') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function initCannedResponsesPage() {
                const addBtn = document.getElementById('canned-response-add-btn');
                if (addBtn && ! addBtn.dataset.bound) {
                    addBtn.dataset.bound = 'true';
                    addBtn.addEventListener('click', () => {
                        if (window.HSOverlay) {
                            HSOverlay.open('#canned-response-modal');
                        }
                    });
                }

                @if ($errors->any())
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#canned-response-modal');
                    }
                @endif
            }

            document.addEventListener('DOMContentLoaded', initCannedResponsesPage);
            document.addEventListener('livewire:navigated', initCannedResponsesPage);
        </script>
    @endpush
</x-layouts.app>
