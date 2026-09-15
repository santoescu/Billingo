<?php

namespace App\Http\Controllers;

use App\Services\Blog\BlogPostRepository;
use Illuminate\View\View;

/**
 * Blog público de billingo.com.co (sin login, sin empresa activa) -- lee
 * los artículos directamente de resources/content/blog/ vía
 * BlogPostRepository, no hay tabla en base de datos todavía.
 */
class BlogController extends Controller
{
    public function __construct(private readonly BlogPostRepository $posts)
    {
    }

    public function index(): View
    {
        return view('public.blog.index', [
            'posts' => $this->posts->published(),
        ]);
    }

    public function show(string $slug): View
    {
        $post = $this->posts->find($slug);

        abort_if($post === null || ! $post->isPublished(), 404);

        return view('public.blog.show', [
            'post' => $post,
        ]);
    }
}
