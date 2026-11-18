<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Publicaciones para el portal (sin sesión): solo las publicadas.
 *  - index: las más recientes, opcionalmente de un tipo (article, video), con un extracto del texto
 *  - show: una publicación con su contenido completo
 */
class PublicBlogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|string|exists:content_types,slug',
            'limit' => 'nullable|integer|min:1|max:30',
        ]);

        $blogs = Blog::query()
            ->where('is_published', true)
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->whereHas(
                'contentType',
                fn ($q) => $q->where('slug', $type)
            ))
            ->latest()
            ->limit($validated['limit'] ?? 12)
            ->get();

        return response()->json($blogs->map(fn (Blog $blog) => [
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'type' => $blog->contentType?->slug,
            'excerpt' => $this->excerpt($blog->content),
            'image_url' => $blog->image_url,
            'video_url' => $blog->video_url,
            'created_at' => $blog->created_at,
        ]));
    }

    public function show(Blog $blog)
    {
        abort_unless($blog->is_published, 404);

        return response()->json([
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'type' => $blog->contentType?->slug,
            'content' => $blog->content,
            'image_url' => $blog->image_url,
            'video_url' => $blog->video_url,
            'created_at' => $blog->created_at,
        ]);
    }

    // Texto plano del HTML del editor, recortado para tarjetas y la nota destacada
    private function excerpt(?string $html, int $length = 220): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</h1>', '</h2>', '</h3>', '</li>'], ' ', $html ?? '')));

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $length);
    }
}
