<?php

namespace App\Http\Controllers;

use App\Models\Blog;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content_type_id' => 'required|exists:content_types,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'video_url' => 'nullable|url',
            'is_published' => 'boolean',
            'media_url' => 'nullable|url|max:500',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['is_published'] = $request->boolean('is_published');

        // Procesar y guardar el archivo si existe
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('blogs', 'public');
        }

        $blog = Blog::create($validated);

        return response()->json([
            'message' => 'Publicación creada exitosamente',
            'data' => $blog
        ], 201);
    }

    public function show(Blog $blog)
    {
        return response()->json($blog);
    }

    public function update(Request $request, Blog $blog)
    {
        $validated = $request->validate([
        'content_type_id' => 'sometimes|required|exists:content_types,id',
        'title' => 'sometimes|required|string|max:255',
        'content' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
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
            $validated['image'] = $request->file('image')->store('blogs', 'public');
        }

        $blog->update($validated);

        return response()->json([
            'message' => 'Publicación actualizada exitosamente',
            'data' => $blog
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