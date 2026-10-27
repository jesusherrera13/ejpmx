<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSpreadsheets;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    use HandlesSpreadsheets;

    // Encabezados esperados en el archivo de importación => columna en BD
    private const IMPORT_HEADERS = [
        'Nombre' => 'name',
        'Nombre Corto' => 'short_name',
        'Abreviación' => 'abbreviation',
    ];

    public function index()
    {
        return response()->json(Team::visibleTo(request()->user())->with('user:id,name')->orderByRaw('LOWER(name)')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['user_id'] = $request->user()->id;

        $team = Team::create($validated);

        return response()->json($team->load('user:id,name'), 201);
    }

    public function show(Team $team)
    {
        $this->ensureOwner($team);

        return response()->json($team);
    }

    public function update(Request $request, Team $team)
    {
        $this->ensureOwner($team);

        $validated = $request->validate($this->rules($team));
        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $team->update($validated);

        return response()->json($team->load('user:id,name'));
    }

    public function destroy(Team $team)
    {
        $this->ensureOwner($team);

        $team->delete();

        return response()->json([
            'message' => 'Equipo eliminado exitosamente'
        ], 200);
    }

    /**
     * Importa equipos desde un archivo Excel/CSV con encabezados:
     * Nombre | Nombre Corto | Abreviación.
     * Si ya existe un equipo con el mismo nombre (sin distinguir mayúsculas) se actualiza.
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
                    'short_name' => 'required|string|max:100',
                    'abbreviation' => 'required|string|max:10',
                ], [], [
                    'name' => 'Nombre',
                    'short_name' => 'Nombre Corto',
                    'abbreviation' => 'Abreviación',
                ]);

                if ($validator->fails()) {
                    $errors[] = "Fila {$rowNumber}: " . implode(' ', $validator->errors()->all());
                    continue;
                }

                $team = Team::ownedBy($userId)->whereRaw('LOWER(name) = ?', [Str::lower($data['name'])])->first();

                if ($team) {
                    $team->update($data);
                    $updated++;
                } else {
                    Team::create($data + ['user_id' => $userId, 'is_active' => true]);
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
            ['Nombre' => 35, 'Nombre Corto' => 25, 'Abreviación' => 15],
            'ejpmx_plantilla_equipos.xlsx',
            'Equipos'
        );
    }

    private function rules(?Team $team = null): array
    {
        $required = $team ? 'sometimes|required' : 'required';

        return [
            'name' => [...explode('|', $required), 'string', 'max:150', Rule::unique('teams', 'name')->where('user_id', $team?->user_id ?? auth()->id())->whereNull('deleted_at')->ignore($team?->id)],
            'short_name' => "{$required}|string|max:100",
            'abbreviation' => "{$required}|string|max:10",
            'is_active' => 'boolean',
        ];
    }
}
