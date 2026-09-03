{{--
    Encuesta "¿te sirvió?" -- solo se incluye con el ticket ya cerrado (ver
    support/show.blade.php). Si ya se calificó, muestra las estrellas fijas
    en modo lectura en vez del formulario; el backend (submitSatisfaction())
    también rechaza un segundo envío por las dudas.
--}}
<div class="shrink-0 rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
    @if ($ticket->satisfaction_rating)
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-0.5 text-amber-400">
                @for ($i = 1; $i <= 5; $i++)
                    <svg class="size-5 shrink-0 {{ $i <= $ticket->satisfaction_rating ? 'fill-current' : 'fill-none text-zinc-300 dark:text-neutral-600' }}" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>
                @endfor
            </div>
            <p class="text-sm text-zinc-600 dark:text-neutral-400">{{ __('Thanks for your feedback!') }}</p>
        </div>
        @if ($ticket->satisfaction_comment)
            <p class="mt-2 text-sm text-zinc-700 dark:text-neutral-300">{{ $ticket->satisfaction_comment }}</p>
        @endif
    @else
        <form action="{{ route('support.satisfaction', $ticket->_id) }}" method="POST" id="satisfaction-form">
            @csrf
            <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Did this request help you?') }}</p>
            <input type="hidden" name="satisfaction_rating" id="satisfaction-rating-input" value="">
            <div class="flex items-center gap-1" id="satisfaction-stars">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" class="satisfaction-star rounded-full p-1 text-zinc-300 hover:text-amber-400 focus:outline-hidden dark:text-neutral-600" data-value="{{ $i }}" aria-label="{{ trans_choice(':count star|:count stars', $i, ['count' => $i]) }}">
                        <svg class="size-6 shrink-0 fill-none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>
                    </button>
                @endfor
            </div>
            <textarea name="satisfaction_comment" rows="2" maxlength="1000" placeholder="{{ __('Any comments? (optional)') }}" class="mt-3 block w-full resize-none rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm text-zinc-700 placeholder:text-zinc-400 focus:border-accent focus:ring-accent focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-800 dark:text-zinc-300 dark:placeholder:text-neutral-500"></textarea>
            <div class="mt-3 flex justify-end">
                <flux:button type="submit" variant="primary" id="satisfaction-submit-btn" disabled>{{ __('Send feedback') }}</flux:button>
            </div>
        </form>

        @push('scripts')
            <script>
                (function () {
                    function initSatisfactionStars() {
                        const container = document.getElementById('satisfaction-stars');
                        if (! container || container.dataset.bound === 'true') {
                            return;
                        }
                        container.dataset.bound = 'true';

                        const stars = Array.from(container.querySelectorAll('.satisfaction-star'));
                        const input = document.getElementById('satisfaction-rating-input');
                        const submitBtn = document.getElementById('satisfaction-submit-btn');

                        function paint(value) {
                            stars.forEach((star) => {
                                const active = Number(star.dataset.value) <= value;
                                star.classList.toggle('text-amber-400', active);
                                star.classList.toggle('text-zinc-300', ! active);
                                star.classList.toggle('dark:text-neutral-600', ! active);
                                star.querySelector('svg').classList.toggle('fill-current', active);
                            });
                        }

                        stars.forEach((star) => {
                            star.addEventListener('mouseenter', () => paint(Number(star.dataset.value)));
                            star.addEventListener('click', () => {
                                input.value = star.dataset.value;
                                submitBtn.disabled = false;
                                paint(Number(star.dataset.value));
                            });
                        });

                        container.addEventListener('mouseleave', () => paint(Number(input.value) || 0));
                    }

                    document.addEventListener('DOMContentLoaded', initSatisfactionStars);
                    document.addEventListener('livewire:navigated', initSatisfactionStars);
                })();
            </script>
        @endpush
    @endif
</div>
