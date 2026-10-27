<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSpreadsheets;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PlayerController extends Controller
{
    use HandlesSpreadsheets;

    // Encabezados esperados en el archivo de importación => columna en BD
    private const IMPORT_HEADERS = [
        'APELLIDO 1' => 'last_name',
        'APELLIDO 2' => 'second_last_name',
        'NOMBRE' => 'first_name',
        'CURP' => 'curp',
        'FECHA DE NACIMIENTO' => 'birthdate',
    ];

    public function index()
    {
        return response()->json(Player::visibleTo(request()->user())->with('user:id,name')->orderBy('last_name')->orderBy('first_name')->get());
    }

    public function store(Request $request)
    {
        $request->merge(['curp' => $request->filled('curp') ? Str::upper($request->curp) : null]);
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['user_id'] = $request->user()->id;

        $player = Player::create($validated);

        return response()->json($player->load('user:id,name'), 201);
    }

    public function show(Player $player)
    {
        $this->ensureOwner($player);

        return response()->json($player);
    }

    public function update(Request $request, Player $player)
    {
        $this->ensureOwner($player);

        if ($request->has('curp')) {
            $request->merge(['curp' => $request->filled('curp') ? Str::upper($request->curp) : null]);
        }
        $validated = $request->validate($this->rules($player));
        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $player->update($validated);

        return response()->json($player->load('user:id,name'));
    }

    public function destroy(Player $player)
    {
        $this->ensureOwner($player);

        $player->delete();

        return response()->json([
            'message' => 'Jugador eliminado exitosamente'
        ], 200);
    }

    /**
     * Importa jugadores desde un archivo Excel/CSV con encabezados:
     * APELLIDO 1 | APELLIDO 2 | NOMBRE | CURP | FECHA DE NACIMIENTO.
     * Si la CURP ya existe se actualiza el jugador; sin CURP, se busca por nombre completo y fecha de nacimiento.
     */
    public function import(Request $request)
    {
        $rows = $this->readSpreadsheet($request, self::IMPORT_HEADERS);
        $userId = $request->user()->id;

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $userId, &$created, &$updated, &$errors) {
            foreach ($rows as $rowNumber => $row) {
                $birthdate = $this->parseBirthdate($row['birthdate']);

                $data = [
                    'last_name' => trim((string) $row['last_name']),
                    'second_last_name' => trim((string) $row['second_last_name']) ?: null,
                    'first_name' => trim((string) $row['first_name']),
                    'curp' => Str::upper(trim((string) $row['curp'])) ?: null,
                    'birthdate' => $birthdate ?: null,
                ];

                $validator = Validator::make($data, [
                    'last_name' => 'required|string|max:100',
                    'second_last_name' => 'nullable|string|max:100',
                    'first_name' => 'required|string|max:100',
                    'curp' => 'nullable|alpha_num|size:18',
                    'birthdate' => 'nullable|date|before_or_equal:today',
                ], [], [
                    'last_name' => 'Apellido 1',
                    'second_last_name' => 'Apellido 2',
                    'first_name' => 'Nombre',
                    'curp' => 'CURP',
                    'birthdate' => 'Fecha de Nacimiento',
                ]);

                // Había un valor pero no se pudo interpretar como fecha
                if ($birthdate === false) {
                    $validator->after(fn ($v) => $v->errors()->add('birthdate', 'La Fecha de Nacimiento debe tener formato aaaa-mm-dd.'));
                }

                if ($validator->fails()) {
                    $errors[] = "Fila {$rowNumber}: " . implode(' ', $validator->errors()->all());
                    continue;
                }

                $player = $this->findExistingPlayer($data, $userId);

                if ($player) {
                    $player->update($data);
                    $updated++;
                } else {
                    Player::create($data + ['user_id' => $userId, 'is_active' => true]);
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
            ['APELLIDO 1' => 20, 'APELLIDO 2' => 20, 'NOMBRE' => 25, 'CURP' => 22, 'FECHA DE NACIMIENTO' => 22],
            'ejpmx_plantilla_jugadores.xlsx',
            'Jugadores',
            function ($sheet) {
                // Todas las celdas de captura como texto: Excel no convierte la fecha (aaaa-mm-dd) ni la CURP
                $sheet->getStyle('A2:E1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        );
    }

    private function findExistingPlayer(array $data, int $userId): ?Player
    {
        if ($data['curp']) {
            return Player::ownedBy($userId)->where('curp', $data['curp'])->first();
        }

        return Player::ownedBy($userId)->whereNull('curp')
            ->whereRaw('LOWER(first_name) = ?', [Str::lower($data['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [Str::lower($data['last_name'])])
            ->whereRaw('LOWER(COALESCE(second_last_name, \'\')) = ?', [Str::lower($data['second_last_name'] ?? '')])
            ->where('birthdate', $data['birthdate'])
            ->first();
    }

    /**
     * Convierte la fecha a Y-m-d. Acepta texto aaaa-mm-dd (formato de la plantilla) o, si la celda
     * quedó como fecha de Excel, su número serial. Devuelve null si está vacía y false si es inválida.
     */
    private function parseBirthdate(mixed $value): string|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $date = \DateTime::createFromFormat('!Y-m-d', trim((string) $value));
        $issues = \DateTime::getLastErrors();
        // Fechas como 2010-02-31 generan warnings: se consideran inválidas
        if ($date && (! $issues || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }

        return false;
    }

    private function rules(?Player $player = null): array
    {
        $required = $player ? 'sometimes|required' : 'required';

        return [
            'first_name' => "{$required}|string|max:100",
            'last_name' => "{$required}|string|max:100",
            'second_last_name' => 'nullable|string|max:100',
            'birthdate' => 'nullable|date',
            'curp' => ['nullable', 'alpha_num', 'size:18', Rule::unique('players', 'curp')->where('user_id', $player?->user_id ?? auth()->id())->whereNull('deleted_at')->ignore($player?->id)],
            'is_active' => 'boolean',
        ];
    }
}
