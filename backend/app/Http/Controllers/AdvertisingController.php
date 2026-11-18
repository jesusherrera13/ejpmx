<?php

namespace App\Http\Controllers;

use App\Models\Advertising;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Publicidad de los patrocinadores (panel de administración).
 * index filtra por fecha (vigente ese día), tipo de publicación y patrocinador.
 */
class AdvertisingController extends Controller
{
    // La imagen de la publicidad se guarda en formato vertical 4:5 (recortada al centro)
    private const IMAGE_WIDTH = 800;

    private const IMAGE_HEIGHT = 1000;

    public function index(Request $request)
    {
        $filters = $request->validate([
            'date' => 'nullable|date',
            'content_type_id' => 'nullable|integer|exists:content_types,id',
            'sponsor_id' => 'nullable|integer|exists:sponsors,id',
        ]);

        $ads = Advertising::query()
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->activeOn($date))
            ->when($filters['content_type_id'] ?? null, fn ($query, $type) => $query->where('content_type_id', $type))
            ->when($filters['sponsor_id'] ?? null, fn ($query, $sponsor) => $query->where('sponsor_id', $sponsor))
            ->latest()
            ->get();

        return response()->json($ads);
    }

    public function store(Request $request, ImageOptimizer $optimizer)
    {
        $validated = $this->validateAd($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $optimizer->storeCover($request->file('image'), 'advertising', self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        }

        $ad = Advertising::create($validated);

        return response()->json([
            'message' => 'Publicidad creada',
            'data' => $ad->fresh(),
        ], 201);
    }

    public function show(Advertising $advertising)
    {
        return response()->json($advertising);
    }

    public function update(Request $request, Advertising $advertising, ImageOptimizer $optimizer)
    {
        $validated = $this->validateAd($request);

        if ($request->hasFile('image')) {
            if ($advertising->image) {
                Storage::disk('public')->delete($advertising->image);
            }
            $validated['image'] = $optimizer->storeCover($request->file('image'), 'advertising', self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        }

        $advertising->update($validated);

        return response()->json([
            'message' => 'Publicidad actualizada',
            'data' => $advertising->fresh(),
        ]);
    }

    public function destroy(Advertising $advertising)
    {
        if ($advertising->image) {
            Storage::disk('public')->delete($advertising->image);
        }
        $advertising->delete();

        return response()->json(['message' => 'Publicidad eliminada']);
    }

    private function validateAd(Request $request): array
    {
        $permanent = $request->boolean('is_permanent');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'content_type_id' => 'required|exists:content_types,id',
            'sponsor_id' => 'required|exists:sponsors,id',
            'video_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'is_permanent' => 'boolean',
            'is_active' => 'boolean',
            // Sin "permanente", la fecha de inicio es obligatoria y el fin no puede ser antes del inicio
            'starts_at' => [$permanent ? 'nullable' : 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ], [], [
            'title' => 'título',
            'content' => 'contenido',
            'content_type_id' => 'tipo de publicación',
            'sponsor_id' => 'patrocinador',
            'video_url' => 'URL del video',
            'link_url' => 'enlace',
            'starts_at' => 'fecha de inicio',
            'ends_at' => 'fecha de fin',
            'image' => 'imagen',
        ]);

        unset($validated['image']);
        $validated['is_permanent'] = $permanent;
        $validated['is_active'] = $request->boolean('is_active', true);

        // La publicidad permanente no usa rango de fechas
        if ($permanent) {
            $validated['starts_at'] = null;
            $validated['ends_at'] = null;
        }

        return $validated;
    }
}
