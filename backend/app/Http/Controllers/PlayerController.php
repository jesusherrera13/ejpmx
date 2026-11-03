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
        'APELLIDO1' => 'last_name',
        'APELLIDO2' => 'second_last_name',
        'NOMBRE' => 'first_name',
        'CURP' => 'curp',
        'FECHA_DE_NACIMIENTO' => 'birthdate',
    ];

    // Columnas que pueden venir o no; EQUIPO solo se muestra en la vista previa
    private const OPTIONAL_HEADERS = [
        'EQUIPO' => 'team',
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
     * Vista previa de la importación: cada fila con lo que se haría (crear, actualizar, sin cambios u omitir),
     * el ID del jugador encontrado y los motivos por los que se omitiría. No guarda nada.
     */
    public function previewImport(Request $request)
    {
        $rows = $this->readSpreadsheet($request, self::IMPORT_HEADERS, self::OPTIONAL_HEADERS);

        return response()->json(['rows' => $this->analyzeImport($rows, $request->user()->id)]);
    }

    /**
     * Importa jugadores desde un archivo Excel/CSV con encabezados:
     * APELLIDO1 | APELLIDO2 | NOMBRE | CURP | FECHA_DE_NACIMIENTO | EQUIPO (opcional), en una o varias hojas.
     * Si la CURP ya existe se actualiza el jugador; sin CURP, se busca por nombre completo y fecha de nacimiento.
     * Las filas que no pasan las validaciones (incluida la CURP contra nombre y fecha) se omiten.
     */
    public function import(Request $request)
    {
        $rows = $this->readSpreadsheet($request, self::IMPORT_HEADERS, self::OPTIONAL_HEADERS);
        $userId = $request->user()->id;
        $analysis = $this->analyzeImport($rows, $userId);

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($analysis, $userId, &$created, &$updated, &$errors) {
            foreach ($analysis as $item) {
                if ($item['errors']) {
                    $errors[] = "Fila {$item['row']}: " . implode(' ', $item['errors']);
                    continue;
                }

                if ($item['action'] === 'update') {
                    Player::ownedBy($userId)->findOrFail($item['player_id'])->update($item['data']);
                    $updated++;
                } elseif ($item['action'] === 'create') {
                    Player::create($item['data'] + ['user_id' => $userId, 'is_active' => true]);
                    $created++;
                }
            }
        });

        return $this->importSummary($created, $updated, $errors);
    }

    /**
     * Valida cada fila del archivo y decide qué hacer con ella: create, update, unchanged o skip (con errores).
     *
     * @return list<array{row: int|string, data: array, team: ?string, player_id: ?int, action: string, errors: string[]}>
     */
    private function analyzeImport(array $rows, int $userId): array
    {
        $result = [];
        $seenCurps = [];

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

            $errors = $validator->errors()->all();
            // Había un valor pero no se pudo interpretar como fecha
            if ($birthdate === false) {
                $errors[] = 'La Fecha de Nacimiento debe tener formato dd/mm/aaaa o aaaa-mm-dd.';
            }

            // El jugador existente se busca aunque la fila se vaya a omitir, para mostrar su ID en la vista previa
            $player = $errors ? null : $this->findExistingPlayer($data, $userId);

            if (! $errors && $data['curp']) {
                $errors = $this->curpMismatches($data);

                if (isset($seenCurps[$data['curp']])) {
                    $errors[] = "La CURP está repetida en la fila {$seenCurps[$data['curp']]}.";
                }
                $seenCurps[$data['curp']] ??= $rowNumber;
            }

            if ($player) {
                $errors = [...$errors, ...$this->drasticChanges($player, $data)];
            }

            $result[] = [
                'row' => $rowNumber,
                // Si la fecha no se pudo interpretar se muestra tal cual venía
                'data' => $birthdate === false ? ['birthdate' => (string) $row['birthdate']] + $data : $data,
                'team' => trim((string) $row['team']) ?: null,
                'player_id' => $player?->id,
                'action' => match (true) {
                    (bool) $errors => 'skip',
                    ! $player => 'create',
                    $this->sameData($player, $data) => 'unchanged',
                    default => 'update',
                },
                'errors' => $errors,
            ];
        }

        return $result;
    }

    /**
     * Compara la CURP con los datos que la conforman: iniciales del Apellido 1, Apellido 2 (X si no hay)
     * y Nombre (se salta José/María en nombres compuestos) y la fecha de nacimiento (aammdd, y el siglo
     * por la posición 17: dígito antes de 2000, letra desde 2000).
     *
     * @return string[]
     */
    private function curpMismatches(array $data): array
    {
        $curp = $data['curp'];

        if (! preg_match('/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/', $curp)) {
            return ['La CURP no tiene un formato válido.'];
        }

        $errors = [];
        $initials = [
            [0, 'Apellido 1', $this->curpInitial($data['last_name'], true)],
            [2, 'Apellido 2', $this->curpInitial($data['second_last_name'], true) ?? 'X'],
            [3, 'Nombre', $this->curpInitial($data['first_name'], false)],
        ];
        foreach ($initials as [$position, $label, $expected]) {
            if ($expected !== null && $curp[$position] !== $expected) {
                $errors[] = "La CURP no coincide con el {$label} (la letra " . ($position + 1) . " debería ser {$expected}).";
            }
        }

        if ($data['birthdate']) {
            $date = \DateTime::createFromFormat('!Y-m-d', $data['birthdate']);
            $sameCentury = ctype_digit($curp[16]) === ((int) $date->format('Y') < 2000);
            if (substr($curp, 4, 6) !== $date->format('ymd') || ! $sameCentury) {
                $errors[] = 'La CURP no coincide con la Fecha de Nacimiento.';
            }
        }

        return $errors;
    }

    // Letra con la que el nombre aparece en la CURP
    private function curpInitial(?string $name, bool $isSurname): ?string
    {
        $word = $this->curpMainWord($name, $isSurname);

        return $word ? $word[0] : null;
    }

    // Letras que el nombre aporta a la CURP: inicial, primera vocal interna (solo el Apellido 1)
    // y primera consonante interna
    private function curpLetters(?string $name, bool $isSurname, bool $withVowel = false): string
    {
        $word = (string) $this->curpMainWord($name, $isSurname);
        $inner = substr($word, 1);

        return substr($word, 0, 1)
            . ($withVowel ? (preg_match('/[AEIOU]/', $inner, $m) ? $m[0] : 'X') : '')
            . (preg_match('/[^AEIOU]/', $inner, $m) ? $m[0] : 'X');
    }

    // Palabra del nombre que se usa en la CURP: la Ñ cuenta como X y se omiten acentos,
    // partículas de apellidos (de, del, la...) y José/María al inicio de nombres compuestos
    private function curpMainWord(?string $name, bool $isSurname): ?string
    {
        $words = $this->curpWords($name);

        if ($isSurname) {
            $particles = ['DA', 'DAS', 'DE', 'DEL', 'DER', 'DI', 'DIE', 'DD', 'EL', 'LA', 'LAS', 'LE', 'LES', 'LOS', 'MAC', 'MC', 'VAN', 'VON', 'Y'];
            $words = array_values(array_filter($words, fn ($word) => ! in_array($word, $particles, true))) ?: $words;
        } elseif (count($words) > 1 && in_array($words[0], ['JOSE', 'J', 'MARIA', 'MA', 'M'], true)) {
            array_shift($words);
        }

        return $words[0] ?? null;
    }

    /** @return string[] */
    private function curpWords(?string $name): array
    {
        $name = Str::ascii(str_replace(['Ñ', 'ñ'], 'X', (string) $name));
        $name = preg_replace('/[^A-Z ]/', '', strtoupper($name));

        return preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Al actualizar un jugador existente los nombres solo pueden corregirse (acentos, mayúsculas,
     * errores de captura) o cambiar sin afectar las letras que aportan a la CURP (p. ej. "Grant" →
     * "Grant de Jesús"), no cambiarse por otros ni borrarse; tampoco puede borrarse la fecha.
     *
     * @return string[]
     */
    private function drasticChanges(Player $player, array $data): array
    {
        $errors = [];
        $labels = ['last_name' => 'Apellido 1', 'second_last_name' => 'Apellido 2', 'first_name' => 'Nombre'];

        foreach ($labels as $field => $label) {
            $current = implode(' ', $this->curpWords($player->{$field}));
            $new = implode(' ', $this->curpWords($data[$field]));
            if ($current === '' || $current === $new) {
                continue;
            }

            // El cambio no afecta la CURP: se deja pasar
            $isSurname = $field !== 'first_name';
            $withVowel = $field === 'last_name';
            if ($new !== '' && $this->curpLetters($player->{$field}, $isSurname, $withVowel) === $this->curpLetters($data[$field], $isSurname, $withVowel)) {
                continue;
            }

            similar_text($current, $new, $percent);
            if ($percent < 70) {
                $errors[] = "El {$label} cambia demasiado respecto al jugador #{$player->id} (\"{$player->{$field}}\" → \"" . ($data[$field] ?? '') . '").';
            }
        }

        if ($player->birthdate && ! $data['birthdate']) {
            $errors[] = "Falta la Fecha de Nacimiento; el jugador #{$player->id} ya la tiene registrada.";
        }

        return $errors;
    }

    private function sameData(Player $player, array $data): bool
    {
        return $player->last_name === $data['last_name']
            && $player->second_last_name === $data['second_last_name']
            && $player->first_name === $data['first_name']
            && $player->curp === $data['curp']
            && $player->birthdate?->format('Y-m-d') === $data['birthdate'];
    }

    /**
     * Genera la plantilla de Excel con los encabezados que espera import().
     */
    public function template()
    {
        return $this->downloadTemplate(
            ['APELLIDO1' => 20, 'APELLIDO2' => 20, 'NOMBRE' => 25, 'CURP' => 22, 'FECHA_DE_NACIMIENTO' => 24, 'EQUIPO' => 30],
            'ejpmx_plantilla_jugadores.xlsx',
            'Jugadores',
            function ($sheet) {
                // Todas las celdas de captura como texto: Excel no convierte la fecha (dd/mm/aaaa) ni la CURP
                $sheet->getStyle('A2:F1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
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
     * Convierte la fecha a Y-m-d. Acepta texto dd/mm/aaaa (formato de la plantilla) o aaaa-mm-dd o, si la celda
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

        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat($format, trim((string) $value));
            $issues = \DateTime::getLastErrors();
            // Fechas como 31/02/2010 generan warnings: se consideran inválidas
            if ($date && (! $issues || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
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
