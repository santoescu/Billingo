<?php

namespace App\Http\Controllers;

use App\Services\Blog\BlogPostRepository;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Solo las páginas que de verdad son públicas e indexables -- todo lo demás vive detrás de
     * login (ver routes/web.php), así que no tiene sentido listarlo aquí: un crawler no puede
     * entrar de todos modos.
     */
    public function index(BlogPostRepository $blog): Response
    {
        $urls = collect([
            ['loc' => route('blog.index'), 'lastmod' => null],
            ['loc' => route('api-docs.index'), 'lastmod' => null],
        ])->concat(
            collect($blog->published())->map(fn ($post) => [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updatedAt->toDateString(),
            ])
        );

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
