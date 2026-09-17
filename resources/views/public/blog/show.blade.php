<x-layouts.public :title="$post->title . ' — Billingo'">
    @push('head')
        <meta name="description" content="{{ $post->metaDescription }}">
        {{--
            Article schema -- autor y fecha son señales de frescura/E-E-A-T que las IA (ChatGPT,
            Perplexity, Google AI Overviews) usan para decidir qué citar, ver skill ai-seo.
            Mismo motivo que en partials/head.blade.php para el @php/@endphp: "@context"/"@type"
            escritos directo en el HTML se compilan como directivas de Blade y rompen el JSON.
        --}}
        @php
            $articleSchema = json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $post->metaDescription,
                'datePublished' => $post->publishedAt->toIso8601String(),
                'dateModified' => $post->updatedAt->toIso8601String(),
                'author' => ['@type' => 'Organization', 'name' => $post->author],
                'publisher' => ['@type' => 'Organization', 'name' => 'Billingo'],
                'mainEntityOfPage' => route('blog.show', $post->slug),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @endphp
        <script type="application/ld+json">{!! $articleSchema !!}</script>
    @endpush

    <div class="mx-auto max-w-3xl">
        <a href="{{ route('blog.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('← Back to blog') }}</a>

        <p class="mt-4 text-xs text-zinc-400 dark:text-neutral-500">
            {{ __('By :author', ['author' => $post->author]) }} · {{ __('Published :date', ['date' => $post->publishedAt->format('Y-m-d')]) }}
            @if (! $post->updatedAt->isSameDay($post->publishedAt))
                · {{ __('Updated :date', ['date' => $post->updatedAt->format('Y-m-d')]) }}
            @endif
        </p>

        <article
            class="mt-6
                [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:text-gray-800 [&_h1]:dark:text-white [&_h1]:mb-4
                [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-gray-800 [&_h2]:dark:text-white [&_h2]:mt-8 [&_h2]:mb-3
                [&_p]:text-sm [&_p]:leading-relaxed [&_p]:text-zinc-600 [&_p]:dark:text-neutral-300 [&_p]:mb-4
                [&_ul]:list-disc [&_ul]:ps-5 [&_ul]:mb-4 [&_li]:text-sm [&_li]:text-zinc-600 [&_li]:dark:text-neutral-300 [&_li]:mb-1
                [&_a]:text-accent [&_a]:font-medium [&_a]:hover:underline
                [&_strong]:font-semibold [&_strong]:text-gray-800 [&_strong]:dark:text-white
                [&_hr]:my-8 [&_hr]:border-gray-200 [&_hr]:dark:border-neutral-700
                [&_em]:italic
                [&_table]:w-full [&_table]:mb-4 [&_table]:border-collapse [&_table]:text-sm
                [&_th]:border [&_th]:border-gray-200 [&_th]:dark:border-neutral-700 [&_th]:bg-zinc-50 [&_th]:dark:bg-white/5 [&_th]:p-2 [&_th]:text-start [&_th]:font-semibold [&_th]:text-gray-800 [&_th]:dark:text-white
                [&_td]:border [&_td]:border-gray-200 [&_td]:dark:border-neutral-700 [&_td]:p-2 [&_td]:text-zinc-600 [&_td]:dark:text-neutral-300
                [&_table]:block [&_table]:overflow-x-auto">
            {!! $post->html !!}
        </article>
    </div>
</x-layouts.public>
