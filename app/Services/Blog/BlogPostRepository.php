<?php

namespace App\Services\Blog;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Lee los artículos del blog desde archivos Markdown en
 * resources/content/blog/ -- cada archivo es un slug, con front matter YAML
 * (title, meta_description, pillar, status) seguido de "---" y el cuerpo en
 * Markdown. No hay base de datos: el contenido vive en el repo y se
 * despliega junto con el código, como cualquier otro asset versionado.
 */
class BlogPostRepository
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('content/blog');
    }

    /**
     * @return array<BlogPost>
     */
    public function published(): array
    {
        $posts = array_filter($this->all(), fn (BlogPost $post) => $post->isPublished());

        usort($posts, fn (BlogPost $a, BlogPost $b) => $a->title <=> $b->title);

        return $posts;
    }

    /**
     * @return array<BlogPost>
     */
    public function all(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $posts = [];

        foreach (glob($this->directory.'/*.md') as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            $posts[$slug] = $this->parse($slug, file_get_contents($path));
        }

        return $posts;
    }

    public function find(string $slug): ?BlogPost
    {
        $path = $this->directory.'/'.$slug.'.md';

        if (! is_file($path)) {
            return null;
        }

        return $this->parse($slug, file_get_contents($path));
    }

    private function parse(string $slug, string $raw): BlogPost
    {
        [$frontMatter, $body] = $this->splitFrontMatter($raw);

        $converter = new CommonMarkConverter();

        return new BlogPost(
            slug: $slug,
            title: $frontMatter['title'] ?? $slug,
            metaDescription: $frontMatter['meta_description'] ?? '',
            pillar: $frontMatter['pillar'] ?? '',
            status: $frontMatter['status'] ?? 'draft',
            html: (string) $converter->convert($body),
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        if (! str_starts_with($raw, "---\n")) {
            return [[], $raw];
        }

        $end = strpos($raw, "\n---\n", 4);

        if ($end === false) {
            return [[], $raw];
        }

        $yaml = substr($raw, 4, $end - 4);
        $body = substr($raw, $end + 5);

        return [Yaml::parse($yaml) ?? [], $body];
    }
}
