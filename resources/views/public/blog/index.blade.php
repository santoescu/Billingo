<x-layouts.public :title="__('Blog') . ' — Billingo'">
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ __('Blog') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-neutral-400">{{ __('Facturación electrónica, DIAN, punto de venta y todo lo que necesitas para llevar tu negocio digital.') }}</p>
        </div>

        @if (count($posts) === 0)
            <p class="text-sm text-zinc-500 dark:text-neutral-400">{{ __('No articles published yet.') }}</p>
        @else
            <div class="space-y-4">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}"
                        class="block rounded-lg border border-gray-200 p-5 transition hover:border-accent/40 hover:bg-accent/5 dark:border-neutral-700 dark:hover:border-accent/40">
                        @if ($post->pillar)
                            <span class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $post->pillar }}</span>
                        @endif
                        <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">{{ $post->title }}</h2>
                        @if ($post->metaDescription)
                            <p class="mt-2 text-sm text-zinc-500 dark:text-neutral-400">{{ $post->metaDescription }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
