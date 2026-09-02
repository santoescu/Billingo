{{--
    Hilo de mensajes estilo burbuja de chat (calco del bloque "Chat Bubble"
    de Preline). "$ownRole" decide qué lado del chat es "propio" (a la
    derecha, con el fondo de acento) -- 'company' cuando lo ve la empresa,
    'staff' cuando lo ve el equipo de Billingo -- así el mismo partial sirve
    para las dos vistas sin duplicar el marcado.

    Requiere: $messages (colección de SupportTicketMessage), $ownRole,
    $otherLabel (nombre a mostrar para el lado ajeno cuando no hay usuario,
    p. ej. el nombre de la empresa).
--}}
<ul class="space-y-5">
    @foreach ($messages as $message)
        @php
            $isOwn = $message->author_role === $ownRole;
            $authorName = $message->is_staff ? __('Billingo support') : ($message->user?->name ?? $otherLabel);
            $timestamp = $message->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i');
        @endphp

        @if ($message->is_internal)
            {{-- Nota interna: nunca la ve la empresa (el controlador del
                 lado cliente ni siquiera trae estos mensajes), así que acá
                 solo aparece del lado admin -- estilo distinto (ancho
                 completo, ámbar) para que no se confunda con una respuesta
                 real. --}}
            <li class="mx-auto w-full max-w-lg rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-900/10">
                <p class="mb-1 text-xs font-semibold text-amber-700 dark:text-amber-400">{{ __('Internal note') }} — {{ $authorName }}</p>
                <p class="text-sm whitespace-pre-line break-words text-amber-800 dark:text-amber-200">{{ $message->body }}</p>
                <p class="mt-1 text-xs text-amber-500 dark:text-amber-500/70">{{ $timestamp }}</p>
            </li>
        @elseif ($isOwn)
            <li class="ms-auto max-w-lg flex gap-x-2 sm:gap-x-4">
                <div class="grow text-end space-y-1">
                    <div class="inline-flex flex-col items-end justify-end">
                        <div class="inline-block bg-accent rounded-2xl px-4 py-3 shadow-2xs">
                            <p class="text-start text-sm whitespace-pre-line break-words text-white">{{ $message->body }}</p>
                        </div>
                    </div>
                    <div class="pe-1 text-xs text-zinc-400 dark:text-neutral-500">{{ $timestamp }}</div>
                </div>
            </li>
        @else
            <li class="max-w-lg flex gap-x-2 sm:gap-x-4 me-11">
                <div class="w-full space-y-1">
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                        <p class="mb-1 text-xs font-semibold text-zinc-500 dark:text-neutral-400">{{ $authorName }}</p>
                        <p class="text-sm whitespace-pre-line break-words text-zinc-700 dark:text-zinc-300">{{ $message->body }}</p>
                    </div>
                    <div class="ps-1 text-xs text-zinc-400 dark:text-neutral-500">{{ $timestamp }}</div>
                </div>
            </li>
        @endif
    @endforeach
</ul>
