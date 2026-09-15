<?php

namespace App\Services\Blog;

/**
 * Un artículo del blog ya parseado: front matter + cuerpo convertido a HTML.
 * Inmutable -- se reconstruye desde el archivo Markdown en cada request
 * (ver BlogPostRepository), no se guarda en base de datos.
 */
readonly class BlogPost
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $metaDescription,
        public string $pillar,
        public string $status,
        public string $html,
    ) {
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
