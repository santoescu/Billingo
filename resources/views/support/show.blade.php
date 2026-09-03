<x-layouts.app :title="$ticket->subject">
    {{--
        El scroll de esta pantalla debe quedar SOLO dentro del chat, no en
        toda la página -- para eso, todo (título, back link, chat, caja de
        respuesta) va dentro de un mismo contenedor con altura fija igual al
        viewport menos el padding de <flux:main> (p-6 / lg:p-8, ver
        resources/views/components/layouts/app.blade.php), en vez de dejar
        el título afuera y adivinar un porcentaje de alto para el chat --
        así el chat (flex-1 min-h-0 overflow-y-auto) se queda exactamente
        con el espacio que sobra, sin que nada empuje la página a crecer
        más que el viewport.
    --}}
    <div class="flex h-[calc(100dvh-3rem)] flex-col lg:h-[calc(100dvh-4rem)]">
        @include('partials.tittle', [
            'title' => $ticket->subject,
            'subheading' => $ticket->module_label,
        ])

        {{-- mx-auto: centrado -- acá no hay panel al lado (a diferencia del
             admin), así que puede ocupar más ancho sin tapar la campana de
             notificaciones (fixed, abajo a la izquierda). --}}
        <div class="mx-auto flex min-h-0 w-full max-w-5xl flex-1 flex-col gap-4">
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <a href="{{ route('support.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-zinc-500 hover:text-accent dark:text-neutral-400">
                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    {{ __('Back to requests') }}
                </a>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($ticket->assignedTo)
                        <span class="text-xs text-zinc-500 dark:text-neutral-400">{{ __('Handled by :name', ['name' => $ticket->assignedTo->name]) }}</span>
                    @endif
                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ticket->status_badge_classes }}">{{ $ticket->status_label }}</span>
                    @if ($ticket->status === \App\Models\SupportTicket::STATUS_CLOSED)
                        <form action="{{ route('support.reopen', $ticket->_id) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-accent hover:underline">{{ __('Reopen') }}</button>
                        </form>
                    @else
                        <form action="{{ route('support.close', $ticket->_id) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-zinc-500 hover:text-accent dark:text-neutral-400">{{ __('Mark as resolved') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div id="ticket-messages" class="min-h-0 flex-1 overflow-y-auto rounded-lg border border-gray-200 p-4 dark:border-neutral-700 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
                @include('support.partials.messages', ['messages' => $messages, 'ownRole' => 'company', 'otherLabel' => __('You'), 'activities' => $activities, 'activityUsers' => $activityUsers])
            </div>

            @if ($ticket->status === \App\Models\SupportTicket::STATUS_CLOSED)
                @include('support.partials.satisfaction', ['ticket' => $ticket])
            @endif

            <form action="{{ route('support.reply', $ticket->_id) }}" method="POST" class="shrink-0">
                @csrf
                @include('support.partials.composer')
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function initSupportShowPage() {
                const messages = document.getElementById('ticket-messages');
                if (messages) {
                    messages.scrollTop = messages.scrollHeight;
                }
            }

            document.addEventListener('DOMContentLoaded', initSupportShowPage);
            document.addEventListener('livewire:navigated', initSupportShowPage);
        </script>
    @endpush
</x-layouts.app>
