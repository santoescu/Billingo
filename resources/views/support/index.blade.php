<x-layouts.app :title="__('Support')">
    @include('partials.tittle', [
        'title' => __('Support'),
        'subheading' => __('Ask for help or file a request, complaint or claim -- we answer directly here.'),
    ])

    <div class="flex flex-col gap-6">
        <div class="flex justify-end">
            <button type="button" id="support-ticket-add-btn"
                class="inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent/90 focus:outline-hidden">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                {{ __('New request') }}
            </button>
        </div>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Module') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Subject') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Priority') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase dark:text-neutral-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($tickets as $ticket)
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $ticket->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            @if ($ticket->module && $ticket->module !== 'general')
                                                @include('panel.partials.module-badge', ['module' => $ticket->module])
                                            @else
                                                <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ __('General') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $ticket->subject }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ticket->priority_badge_classes }}">{{ $ticket->priority_label }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ticket->status_badge_classes }}">{{ $ticket->status_label }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-end text-sm">
                                            <a href="{{ route('support.show', $ticket->_id) }}" wire:navigate class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700" aria-label="{{ __('View') }}" title="{{ __('View') }}">
                                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('requests')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: nueva solicitud -->
    <div id="support-ticket-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-90 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="support-ticket-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
                <form action="{{ route('support.store') }}" method="POST">
                    @csrf
                    <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                        <h3 id="support-ticket-modal-label" class="font-bold text-gray-800 dark:text-white">{{ __('New request') }}</h3>
                    </div>
                    <div class="p-4 flex flex-col gap-4">
                        @if ($errors->any())
                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div>
                            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Module') }}</label>
                            <select name="module" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                                <option value="general" selected>{{ __('General') }}</option>
                                @foreach ($modules as $key => $module)
                                    <option value="{{ $key }}">{{ $module['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-neutral-400">{{ __('Which module has the problem, if any.') }}</p>
                        </div>

                        <div>
                            <label class="inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white mb-2">{{ __('Priority') }}</label>
                            <select name="priority" data-hs-select='{!! \App\Support\SelectConfig::basic() !!}' class="hidden">
                                <option value="baja">{{ __('Low') }}</option>
                                <option value="media" selected>{{ __('Medium') }}</option>
                                <option value="alta">{{ __('High') }}</option>
                                <option value="urgente">{{ __('Urgent') }}</option>
                            </select>
                        </div>

                        <flux:input name="subject" :label="__('Subject')" value="{{ old('subject') }}" required />
                        <flux:textarea name="body" :label="__('Message')" rows="4" required>{{ old('body') }}</flux:textarea>
                    </div>
                    <div class="p-4 pt-0 flex justify-end gap-2">
                        <flux:button type="button" variant="filled" onclick="window.HSOverlay && HSOverlay.close('#support-ticket-modal')">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Send') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            /**
             * Igual que initQuotationsPage(): se registra tanto en
             * "DOMContentLoaded" como en "livewire:navigated" para que el
             * botón funcione también al entrar por un link del sidebar.
             * @returns {void}
             */
            function initSupportIndexPage() {
                const addBtn = document.getElementById('support-ticket-add-btn');
                if (addBtn && ! addBtn.dataset.bound) {
                    addBtn.dataset.bound = 'true';
                    addBtn.addEventListener('click', () => {
                        if (window.HSOverlay) {
                            HSOverlay.open('#support-ticket-modal');
                        }
                    });
                }

                @if ($errors->any())
                    if (window.HSOverlay) {
                        HSOverlay.autoInit();
                        HSOverlay.open('#support-ticket-modal');
                    }
                @endif
            }

            document.addEventListener('DOMContentLoaded', initSupportIndexPage);
            document.addEventListener('livewire:navigated', initSupportIndexPage);
        </script>
    @endpush
</x-layouts.app>
