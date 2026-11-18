<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::latest()->paginate(10);
        return response()->json($blogs);
    }

    public function store(Request $request, ImageOptimizer $optimizer)
    {
        $validated = $request->validate([
            'content_type_id' => 'required|exists:content_types,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // hasta 10 MB; se optimiza al guardar
            'video_url' => 'nullable|url',
            'is_published' => 'boolean',
            'media_url' => 'nullable|url|max:500',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['is_published'] = $request->boolean('is_published');

        // Guardar la portada reducida (WebP, máx. 1600 px)
        if ($request->hasFile('image')) {
            $validated['image'] = $optimizer->store($request->file('image'), 'blogs');
        }

        $blog = Blog::create($validated);

        return response()->json([
            'message' => 'Publicación creada exitosamente',
            // fresh(): incluye el tipo de publicación (contentType) para que el panel la muestre bien
            'data' => $blog->fresh()
        ], 201);
    }

    public function show(Blog $blog)
    {
        return response()->json($blog);
    }

    public function update(Request $request, Blog $blog, ImageOptimizer $optimizer)
    {
        $validated = $request->validate([
        'content_type_id' => 'sometimes|required|exists:content_types,id',
        'title' => 'sometimes|required|string|max:255',
        'content' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // hasta 10 MB; se optimiza al guardar
        'video_url' => 'nullable|url',
        'is_published' => 'boolean',
        'media_url' => 'nullable|url|max:500',
    ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }
        if ($request->has('is_published')) {
            $validated['is_published'] = $request->boolean('is_published');
        }

        // Si se subió una nueva imagen, borrar la anterior y guardar la nueva
        if ($request->hasFile('image')) {
            if ($blog->image) {
                Storage::disk('public')->delete($blog->image);
            }
            $validated['image'] = $optimizer->store($request->file('image'), 'blogs');
        }

        $blog->update($validated);

        return response()->json([
            'message' => 'Publicación actualizada exitosamente',
            'data' => $blog->fresh()
        ], 200);
    }

    public function destroy(Blog $blog)
    {
        if ($blog->image) {
            Storage::disk('public')->delete($blog->image);
        }
        $blog->delete();

        return response()->json([
            'message' => 'Publicación eliminada exitosamente'
        ], 200); 
    }
}