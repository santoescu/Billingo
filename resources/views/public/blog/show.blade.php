<x-layouts.public :title="$post->title . ' — Billingo'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('blog.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('← Back to blog') }}</a>

        <article
            class="mt-6
                [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:text-gray-800 [&_h1]:dark:text-white [&_h1]:mb-4
                [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-gray-800 [&_h2]:dark:text-white [&_h2]:mt-8 [&_h2]:mb-3
                [&_p]:text-sm [&_p]:leading-relaxed [&_p]:text-zinc-600 [&_p]:dark:text-neutral-300 [&_p]:mb-4
                [&_ul]:list-disc [&_ul]:ps-5 [&_ul]:mb-4 [&_li]:text-sm [&_li]:text-zinc-600 [&_li]:dark:text-neutral-300 [&_li]:mb-1
                [&_a]:text-accent [&_a]:font-medium [&_a]:hover:underline
                [&_strong]:font-semibold [&_strong]:text-gray-800 [&_strong]:dark:text-white
                [&_hr]:my-8 [&_hr]:border-gray-200 [&_hr]:dark:border-neutral-700
                [&_em]:italic">
            {!! $post->html !!}
        </article>
    </div>
</x-layouts.public>
