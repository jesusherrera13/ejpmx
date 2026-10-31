<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSpreadsheets;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    use HandlesSpreadsheets;

    // Encabezados esperados en el archivo de importación => columna en BD
    private const IMPORT_HEADERS = [
        'Nombre de Torneo' => 'name',
    ];

    public function index()
    {
        return response()->json(Tournament::visibleTo(request()->user())->with('user:id,name')->orderByRaw('LOWER(name)')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['games_per_series'] ??= 3;
        $validated['user_id'] = $request->user()->id;

        $tournament = Tournament::create($validated);

        return response()->json($tournament->load('user:id,name'), 201);
    }

    public function show(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($tournament);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $validated = $request->validate($this->rules($tournament));
        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $tournament->update($validated);

        return response()->json($tournament->load('user:id,name'));
    }

    public function destroy(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $tournament->delete();

        return response()->json([
            'message' => 'Torneo eliminado exitosamente'
        ], 200);
    }

    /**
     * Importa torneos desde un archivo Excel/CSV con el encabezado: Nombre de Torneo.
     * Si ya existe un torneo con el mismo nombre (sin distinguir mayúsculas) se actualiza.
     */
    public function import(Request $request)
    {
        $rows = $this->readSpreadsheet($request, self::IMPORT_HEADERS);
        $userId = $request->user()->id;

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $userId, &$created, &$updated, &$errors) {
            foreach ($rows as $rowNumber => $data) {
                $data = array_map(fn ($value) => trim((string) $value), $data);

                $validator = Validator::make($data, [
                    'name' => 'required|string|max:150',
                ], [], [
                    'name' => 'Nombre de Torneo',
                ]);

                if ($validator->fails()) {
                    $errors[] = "Fila {$rowNumber}: " . implode(' ', $validator->errors()->all());
                    continue;
                }

                $tournament = Tournament::ownedBy($userId)->whereRaw('LOWER(name) = ?', [Str::lower($data['name'])])->first();

                if ($tournament) {
                    $tournament->update($data);
                    $updated++;
                } else {
                    Tournament::create($data + ['user_id' => $userId, 'is_active' => true]);
                    $created++;
                }
            }
        });

        return $this->importSummary($created, $updated, $errors);
    }

    /**
     * Genera la plantilla de Excel con los encabezados que espera import().
     */
    public function template()
    {
        return $this->downloadTemplate(
            ['Nombre de Torneo' => 45],
            'ejpmx_plantilla_torneos.xlsx',
            'Torneos'
        );
    }

    private function rules(?Tournament $tournament = null): array
    {
        $required = $tournament ? 'sometimes|required' : 'required';

        return [
            'name' => [...explode('|', $required), 'string', 'max:150', Rule::unique('tournaments', 'name')->where('user_id', $tournament?->user_id ?? auth()->id())->whereNull('deleted_at')->ignore($tournament?->id)],
            'games_per_series' => ['integer', Rule::in(Tournament::GAMES_PER_SERIES_OPTIONS)],
            'is_active' => 'boolean',
        ];
    }
}
