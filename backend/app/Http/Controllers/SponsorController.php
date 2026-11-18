<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Patrocinadores (panel de administración). El logo se guarda optimizado (WebP).
 */
class SponsorController extends Controller
{
    public function index()
    {
        return response()->json(
            Sponsor::withCount('advertisings')->orderBy('name')->get()
        );
    }

    public function store(Request $request, ImageOptimizer $optimizer)
    {
        $validated = $this->validateSponsor($request);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $optimizer->store($request->file('logo'), 'sponsors');
        }

        $sponsor = Sponsor::create($validated);

        return response()->json([
            'message' => 'Patrocinador creado',
            'data' => $sponsor->loadCount('advertisings'),
        ], 201);
    }

    public function show(Sponsor $sponsor)
    {
        return response()->json($sponsor->loadCount('advertisings'));
    }

    public function update(Request $request, Sponsor $sponsor, ImageOptimizer $optimizer)
    {
        $validated = $this->validateSponsor($request, $sponsor);

        if ($request->hasFile('logo')) {
            if ($sponsor->logo) {
                Storage::disk('public')->delete($sponsor->logo);
            }
            $validated['logo'] = $optimizer->store($request->file('logo'), 'sponsors');
        } elseif ($request->boolean('remove_logo') && $sponsor->logo) {
            Storage::disk('public')->delete($sponsor->logo);
            $validated['logo'] = null;
        }

        $sponsor->update($validated);

        return response()->json([
            'message' => 'Patrocinador actualizado',
            'data' => $sponsor->fresh()->loadCount('advertisings'),
        ]);
    }

    public function destroy(Sponsor $sponsor)
    {
        if ($sponsor->advertisings()->exists()) {
            return response()->json([
                'message' => 'El patrocinador tiene publicidad registrada. Elimina su publicidad o márcalo como inactivo.',
            ], 422);
        }

        if ($sponsor->logo) {
            Storage::disk('public')->delete($sponsor->logo);
        }
        $sponsor->delete();

        return response()->json(['message' => 'Patrocinador eliminado']);
    }

    private function validateSponsor(Request $request, ?Sponsor $sponsor = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sponsors', 'name')->ignore($sponsor?->id)],
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:500',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ], [], [
            'name' => 'nombre',
            'contact_name' => 'contacto',
            'email' => 'correo',
            'phone' => 'teléfono',
            'website' => 'sitio web',
            'notes' => 'notas',
            'logo' => 'logo',
        ]);

        unset($validated['logo']);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
