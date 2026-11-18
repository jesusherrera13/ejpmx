<?php

namespace App\Http\Controllers;

use App\Models\Advertising;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Publicidad para el portal (sin sesión): solo la activa y vigente hoy, de patrocinadores
 * activos, en orden aleatorio en cada consulta.
 */
class PublicAdvertisingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $ads = Advertising::query()
            ->where('is_active', true)
            ->whereHas('sponsor', fn ($query) => $query->where('is_active', true))
            ->activeOn(Carbon::today())
            ->inRandomOrder()
            ->limit($validated['limit'] ?? 3)
            ->get();

        return response()->json($ads->map(fn (Advertising $ad) => [
            'id' => $ad->id,
            'title' => $ad->title,
            'type' => $ad->contentType?->slug,
            'content' => $ad->content,
            'image_url' => $ad->image_url,
            'video_url' => $ad->video_url,
            'link_url' => $ad->link_url,
            'sponsor' => $ad->sponsor ? [
                'name' => $ad->sponsor->name,
                'logo_url' => $ad->sponsor->logo_url,
            ] : null,
        ]));
    }
}
