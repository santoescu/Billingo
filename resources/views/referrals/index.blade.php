<x-layouts.app :title="__('My commissions')">
    @include('partials.tittle', [
        'title' => __('My commissions'),
        'subheading' => __('Companies you brought in, and what you earn from each contract.'),
    ])

    @unless ($canRefer)
        <div class="rounded-lg border border-gray-200 p-6 text-center dark:border-neutral-700">
            <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('Your account is not yet enabled to refer other businesses. Ask us to enable it if you\'re interested.') }}</p>
        </div>
    @else
    <div class="flex flex-col gap-6">
        @if ($referralUrl)
            {{-- Una sola caja colapsable, mismo patrón de "Cargos y descuentos" en
                 documents/create.blade.php (caja con encabezado clickeable, ícono que rota, cuerpo
                 que arranca "hidden") -- adentro, el link a la izquierda y el mensaje pre-escrito
                 a la derecha, uno al lado del otro. --}}
            <div class="border border-gray-200 rounded-lg dark:border-neutral-700">
                <button type="button" class="w-full px-4 py-3 flex justify-between items-center" onclick="toggleReferralLinkSection()">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Share with other businesses') }}</h3>
                    <svg id="referralLinkToggleIcon" class="shrink-0 size-4 text-gray-500 dark:text-neutral-400 transition-transform" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div id="referralLinkSectionBody" class="hidden p-4 border-t border-gray-200 dark:border-neutral-700">
                    <p class="text-sm text-gray-600 dark:text-neutral-400">{{ __('Share it with other businesses. When they sign up through it, we can attribute their contract to you.') }}</p>
                    <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ __('Your referral link') }}</p>
                            <div class="mt-2 flex items-stretch rounded-lg border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5">
                                <input type="text" readonly id="referral-link-url" value="{{ $referralUrl }}"
                                    class="flex-1 min-w-0 bg-transparent border-0 text-zinc-700 dark:text-zinc-300 text-sm h-10 px-3 focus:outline-hidden focus:ring-0">
                                <button type="button" class="js-clipboard relative shrink-0 inline-flex items-center justify-center px-3 border-s border-zinc-200 dark:border-white/10 text-gray-400 hover:bg-gray-100 hover:text-accent focus:outline-hidden dark:text-neutral-400 dark:hover:bg-neutral-700"
                                    data-clipboard-target="#referral-link-url"
                                    data-clipboard-action="copy"
                                    aria-label="{{ __('Copy') }}" title="{{ __('Copy') }}">
                                    <svg class="js-clipboard-default size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <svg class="js-clipboard-success hidden size-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ __('Pre-written message') }}</p>
                            <textarea id="referral-message" rows="4" class="mt-2 w-full rounded-lg border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 text-zinc-700 dark:text-zinc-300 text-sm p-3 focus:outline-hidden focus:ring-2 focus:ring-accent">{{ __('Hey! I use Billingo to handle invoicing, POS and quotes all in one place. It validates everything with the DIAN before sending, so there are no late rejections. If it could be useful for you, check it out: :url', ['url' => $referralUrl]) }}</textarea>
                            <div class="mt-2 flex gap-2">
                                <button type="button" id="referral-message-copy" class="js-clipboard relative inline-flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden"
                                    data-clipboard-target="#referral-message" data-clipboard-action="copy">
                                    <svg class="js-clipboard-default size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <svg class="js-clipboard-success hidden size-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    {{ __('Copy message') }}
                                </button>
                                <button type="button" id="referral-message-whatsapp" class="inline-flex items-center gap-2 py-2 px-3 text-sm font-medium rounded-lg border border-zinc-200 dark:border-white/10 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden">
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.031 0C5.44 0 .096 5.334.096 11.917c0 2.1.548 4.148 1.588 5.95L0 24l6.281-1.65a11.9 11.9 0 0 0 5.746 1.463h.005c6.592 0 11.936-5.335 11.936-11.917 0-3.184-1.24-6.176-3.492-8.424A11.86 11.86 0 0 0 12.031 0zm0 21.815h-.004a9.87 9.87 0 0 1-5.033-1.378l-.361-.214-3.728.978.995-3.632-.235-.373a9.86 9.86 0 0 1-1.511-5.279c0-5.462 4.45-9.906 9.923-9.906 2.65 0 5.14 1.03 7.014 2.905a9.83 9.83 0 0 1 2.9 6.996c0 5.462-4.451 9.903-9.96 9.903z"/></svg>
                                    {{ __('Send via WhatsApp') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-400 dark:text-neutral-500">
                {{ __('Terms: the reward only applies once the referred company signs a paid contract with Billingo, and the amount is set case by case, not guaranteed in advance. Referring your own company does not count. We can revoke referral access at any time.') }}
            </p>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Sales') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ $contracts->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-neutral-500">{{ __('Total commission') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($totalCommission, 2, '.', ',') }}</p>
            </div>
        </div>

        <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                    <div class="overflow-hidden">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Price') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Commission %') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Commission') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @forelse ($contracts as $contract)
                                    <tr>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">
                                            {{ collect($contract->company_ids ?? [])->map(fn ($id) => $companyNames->get((string) $id)?->name)->filter()->join(', ') ?: '—' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->starts_at?->format('Y-m-d') }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->price !== null ? number_format($contract->price, 2, '.', ',') : '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $contract->commission_percentage !== null ? rtrim(rtrim(number_format($contract->commission_percentage, 2), '0'), '.') . '%' : '—' }}</td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $contract->commission_amount !== null ? number_format($contract->commission_amount, 2, '.', ',') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-400">{{ __('There are no registered :name.', ['name' => __('sales')]) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($referredCompanies->isNotEmpty())
            <div>
                <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white">{{ __('Companies you referred') }}</p>
                <div class="-m-1.5 overflow-x-auto">
                    <div class="p-1.5 min-w-full inline-block align-middle">
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
                            <div class="overflow-hidden">
                                <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                                    <thead class="bg-gray-50 dark:bg-neutral-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Company') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Sign-up date') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                        @foreach ($referredCompanies as $referred)
                                            <tr>
                                                <td class="px-4 py-4 text-sm font-medium text-gray-800 break-words dark:text-neutral-200">{{ $referred->name }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-neutral-400">{{ $referred->created_at?->format('Y-m-d') }}</td>
                                                <td class="px-4 py-4 text-sm">
                                                    @if ($companyNames->has((string) $referred->_id))
                                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">{{ __('With contract') }}</span>
                                                    @else
                                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ __('Pending contract') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => window.hsClipboardHelper?.('.js-clipboard'));
            document.addEventListener('livewire:navigated', () => window.hsClipboardHelper?.('.js-clipboard'));

            // Manda el mensaje TAL COMO QUEDÓ en el textarea (si lo editaron, se respeta eso) --
            // wa.me abre WhatsApp Web o la app con el texto ya cargado, listo para elegir a quién
            // mandárselo, sin necesitar saber el número de nadie de antemano.
            document.getElementById('referral-message-whatsapp')?.addEventListener('click', () => {
                const message = document.getElementById('referral-message')?.value ?? '';
                window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
            });

            // Mismo patrón que toggleChargesSection() en documents/create.blade.php.
            window.toggleReferralLinkSection = function () {
                const body = document.getElementById('referralLinkSectionBody');
                const icon = document.getElementById('referralLinkToggleIcon');
                const isHidden = body.classList.toggle('hidden');
                icon.classList.toggle('rotate-180', ! isHidden);
            };
        </script>
    @endpush
    @endunless
</x-layouts.app>
