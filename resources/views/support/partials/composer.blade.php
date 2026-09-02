{{--
    Caja de mensaje con el botón de enviar embebido adentro (esquina
    inferior derecha), en vez de textarea + botón aparte debajo. Requiere
    estar dentro de un <form>. "data-hs-textarea-auto-height" es el plugin
    de Preline que hace crecer la caja sola según el contenido, hasta
    "max-h-36" -- ver @preline/textarea-auto-height en resources/js/app.js.

    Opcionales (solo los usa el lado admin):
    - $cannedResponses: colección de CannedResponse -- si viene, muestra un
      select arriba del textarea para insertar una plantilla.
    - $allowInternalNote: si es true, agrega un segundo botón para guardar
      el mensaje como nota interna (is_internal=1) en vez de respuesta.
--}}
@if (! empty($cannedResponses) && $cannedResponses->isNotEmpty())
    <div class="mb-2 max-w-xs">
        <select id="canned-response-select" class="hidden" data-hs-select='{!! \App\Support\SelectConfig::basic(__('Insert a template...')) !!}'>
            <option value=""></option>
            @foreach ($cannedResponses as $canned)
                <option value="{{ $canned->body }}">{{ $canned->title }}</option>
            @endforeach
        </select>
    </div>
@endif

<div class="relative">
    <textarea
        name="body"
        id="composer-textarea"
        required
        rows="1"
        data-hs-textarea-auto-height='{"defaultHeight": 48}'
        placeholder="{{ __('Write a message...') }}"
        class="block max-h-36 w-full resize-none overflow-y-auto rounded-lg border border-gray-200 bg-white py-2.5 ps-4 {{ ! empty($allowInternalNote) ? 'pe-24' : 'pe-14' }} text-sm text-zinc-700 placeholder:text-zinc-400 focus:border-accent focus:ring-accent focus:outline-hidden disabled:pointer-events-none disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-zinc-300 dark:placeholder:text-neutral-500 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500"
    ></textarea>

    <div class="absolute bottom-2 end-2 flex items-center gap-1">
        @if (! empty($allowInternalNote))
            <button type="submit" name="is_internal" value="1" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-amber-600 hover:bg-amber-50 focus:outline-hidden disabled:pointer-events-none disabled:opacity-50 dark:text-amber-400 dark:hover:bg-amber-900/20" aria-label="{{ __('Save as internal note') }}" title="{{ __('Save as internal note') }}">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17h.01"/><path d="M12 3a6 6 0 0 0-6 6c0 7-3 9-3 9h18s-3-2-3-9a6 6 0 0 0-6-6"/></svg>
            </button>
        @endif
        <button type="submit" name="is_internal" value="0" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/90 focus:outline-hidden disabled:pointer-events-none disabled:opacity-50" aria-label="{{ __('Send') }}" title="{{ __('Send') }}">
            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855H.766l-.452.18a.5.5 0 0 0-.082.887l.41.26.001.002 4.995 3.178 3.178 4.995.002.002.26.41a.5.5 0 0 0 .886-.083l6-15Zm-1.833 1.89L6.637 10.07l-.215-.338a.5.5 0 0 0-.154-.154l-.338-.215 7.494-7.494 1.178-.471-.47 1.178Z"/></svg>
        </button>
    </div>
</div>
