<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameBattingLine;
use App\Models\GamePlateAppearance;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Captura del box score de un partido en cinco modalidades:
 *  - score: marcador final por equipo (R H E)
 *  - linescore: R H E y carreras por entrada
 *  - batting: R H E y bateo por jugador (AB H R RBI SO)
 *  - batting_full: bateo detallado (PA AB H 1B 2B 3B HR R RBI BB IBB HBP SF SH SO KS KC E);
 *    R y H del equipo son la suma de su lineup
 *  - scorecard: la jugada de cada turno en su entrada; de ahí se calculan el bateo de cada
 *    jugador (salvo E), las carreras por entrada y R y H del equipo
 * Cada modalidad guarda solo su sección; lo capturado en las demás se conserva.
 * Los turnos del scorecard viajan con el lineup, así que se conservan al guardar en Bateo.
 */
class BoxscoreController extends Controller
{
    // Posiciones defensivas del lineup (PH bateador emergente, PR corredor emergente)
    public const POSITIONS = ['P', 'C', '1B', '2B', '3B', 'SS', 'LF', 'CF', 'RF', 'DH', 'PH', 'PR'];

    public function show(Tournament $tournament, Game $game)
    {
        $this->ensureGame($tournament, $game);

        return response()->json($this->payload($tournament, $game));
    }

    public function update(Request $request, Tournament $tournament, Game $game)
    {
        $this->ensureGame($tournament, $game);

        $mode = $request->validate(['mode' => ['required', Rule::in(Game::BOXSCORE_MODES)]])['mode'];
        $stat = 'required|integer|min:0|max:999';

        $rules = [
            'away_score' => $stat,
            'home_score' => $stat,
            'away_hits' => $stat,
            'home_hits' => $stat,
            'away_errors' => $stat,
            'home_errors' => $stat,
        ];

        if ($mode === 'linescore') {
            $rules += [
                'line_score' => 'required|array',
                'line_score.away' => 'required|array|min:1|max:30',
                'line_score.away.*' => 'required|integer|min:0|max:99',
                'line_score.home' => 'required|array|min:1|max:30',
                // null: el local no batea la última entrada si ya va ganando
                'line_score.home.*' => 'nullable|integer|min:0|max:99',
            ];
        }

        if ($mode === 'scorecard') {
            $rules['innings'] = 'required|integer|min:1|max:30';
        }

        if (in_array($mode, ['batting', 'batting_full', 'scorecard'], true)) {
            $playerIds = Player::ownedBy($tournament->user_id)->pluck('id')->all();
            $rules += [
                'batting' => 'present|array',
                'batting.*.team_id' => ['required', 'integer', Rule::in([$game->away_team_id, $game->home_team_id])],
                'batting.*.player_id' => ['required', 'integer', 'distinct', Rule::in($playerIds)],
                'batting.*.position' => ['nullable', Rule::in(self::POSITIONS)],
                'batting.*.is_substitute' => 'boolean',
                'batting.*.plate_appearances' => 'nullable|array',
                'batting.*.plate_appearances.*.inning' => 'required|integer|min:1|max:30',
                'batting.*.plate_appearances.*.result' => ['required', Rule::in(array_keys(GamePlateAppearance::RESULTS))],
                'batting.*.plate_appearances.*.notation' => 'nullable|string|max:20',
                'batting.*.plate_appearances.*.rbi' => 'nullable|integer|min:0|max:4',
                'batting.*.plate_appearances.*.scored' => 'boolean',
            ];
            foreach (GameBattingLine::STATS as $column) {
                $rules["batting.*.{$column}"] = 'nullable|integer|min:0|max:99';
            }
        }

        $validated = $request->validate($rules, [
            'batting.*.player_id.distinct' => 'Un jugador aparece más de una vez en el lineup.',
            'batting.*.player_id.in' => 'El jugador seleccionado no existe en el catálogo.',
        ], [
            'away_score' => 'carreras del visitante',
            'home_score' => 'carreras del local',
            'away_hits' => 'hits del visitante',
            'home_hits' => 'hits del local',
            'away_errors' => 'errores del visitante',
            'home_errors' => 'errores del local',
            'line_score.away' => 'carreras por entrada del visitante',
            'line_score.home' => 'carreras por entrada del local',
            'batting.*.player_id' => 'jugador',
            'batting.*.plate_appearances.*.result' => 'jugada',
            'batting.*.plate_appearances.*.rbi' => 'carreras impulsadas',
        ]);

        if ($mode === 'linescore' && count($validated['line_score']['away']) !== count($validated['line_score']['home'])) {
            throw ValidationException::withMessages(['line_score' => 'Ambos equipos deben tener el mismo número de entradas.']);
        }

        $score = collect($validated)->only([
            'away_score', 'home_score', 'away_hits', 'home_hits', 'away_errors', 'home_errors',
        ])->all();

        // El lineup se reemplaza completo; el orden al bat es el orden en que llega por equipo
        $lines = [];
        $order = [];
        foreach ($validated['batting'] ?? [] as $line) {
            $teamId = $line['team_id'];
            $stats = [];
            foreach (GameBattingLine::STATS as $column) {
                $stats[$column] = (int) ($line[$column] ?? 0);
            }

            $appearances = [];
            foreach ($line['plate_appearances'] ?? [] as $appearance) {
                if ($mode === 'scorecard' && $appearance['inning'] > $validated['innings']) {
                    throw ValidationException::withMessages(['innings' => 'Hay turnos capturados en entradas que ya no están en el scorecard.']);
                }

                $appearances[] = [
                    'inning' => $appearance['inning'],
                    'result' => $appearance['result'],
                    'notation' => $appearance['notation'] ?? null,
                    'rbi' => (int) ($appearance['rbi'] ?? 0),
                    // Con home run el bateador siempre anota
                    'scored' => $appearance['result'] === 'HR' || ! empty($appearance['scored']),
                ];
            }

            // En el scorecard el bateo sale de los turnos; los errores (defensivos) se capturan aparte
            if ($mode === 'scorecard') {
                $stats = GamePlateAppearance::stats($appearances) + ['e' => $stats['e']];
            }

            // Sin apariciones al plato capturadas se toman los turnos al bat
            if ($stats['pa'] === 0) {
                $stats['pa'] = $stats['ab'];
            }

            // En la modalidad detallada H y SO se derivan de su desglose
            if ($mode === 'batting_full') {
                $stats['h'] = $stats['singles'] + $stats['doubles'] + $stats['triples'] + $stats['hr'];
                $stats['so'] = $stats['so_swinging'] + $stats['so_looking'];
            }

            $order[$teamId] = ($order[$teamId] ?? 0) + 1;
            $lines[] = [
                'team_id' => $teamId,
                'player_id' => $line['player_id'],
                'batting_order' => $order[$teamId],
                // El primer bateador de cada equipo siempre es titular
                'is_substitute' => $order[$teamId] > 1 && ! empty($line['is_substitute']),
                'position' => $line['position'] ?? null,
                'plate_appearances' => $appearances,
            ] + $stats;
        }

        // En el scorecard las carreras por entrada, R y H del equipo salen de los turnos
        if ($mode === 'scorecard') {
            $validated['line_score'] = [];
            foreach (['away' => $game->away_team_id, 'home' => $game->home_team_id] as $side => $teamId) {
                $teamLines = collect($lines)->where('team_id', $teamId);
                $teamAppearances = $teamLines->flatMap(fn ($line) => $line['plate_appearances']);

                $innings = [];
                for ($inning = 1; $inning <= $validated['innings']; $inning++) {
                    $inningAppearances = $teamAppearances->where('inning', $inning);
                    // X: el local no batea la última entrada
                    $innings[] = $side === 'home' && $inning === $validated['innings'] && $inningAppearances->isEmpty()
                        ? null
                        : $inningAppearances->where('scored', true)->count();
                }

                $validated['line_score'][$side] = $innings;
                $score["{$side}_score"] = $teamLines->sum('r');
                $score["{$side}_hits"] = $teamLines->sum('h');
            }
        }

        // En la modalidad detallada, las carreras y hits del equipo son la suma de su lineup
        if ($mode === 'batting_full') {
            foreach (['away' => $game->away_team_id, 'home' => $game->home_team_id] as $side => $teamId) {
                $teamLines = collect($lines)->where('team_id', $teamId);
                if ($teamLines->isNotEmpty()) {
                    $score["{$side}_score"] = $teamLines->sum('r');
                    $score["{$side}_hits"] = $teamLines->sum('h');
                }
            }
        }

        DB::transaction(function () use ($game, $mode, $validated, $score, $lines) {
            $game->update($score + [
                'boxscore_mode' => $mode,
            ] + (in_array($mode, ['linescore', 'scorecard'], true) ? ['line_score' => $validated['line_score']] : []));

            if (! isset($validated['batting'])) {
                return;
            }

            // Al borrar el lineup se borran también sus turnos (cascade)
            $game->battingLines()->delete();
            foreach ($lines as $line) {
                $appearances = $line['plate_appearances'];
                unset($line['plate_appearances']);

                $sequence = [];
                $game->battingLines()->create($line)->plateAppearances()->createMany(
                    array_map(function ($appearance) use (&$sequence) {
                        $sequence[$appearance['inning']] = ($sequence[$appearance['inning']] ?? 0) + 1;

                        return $appearance + ['sequence' => $sequence[$appearance['inning']]];
                    }, $appearances)
                );
            }
        });

        return response()->json($this->payload($tournament, $game->fresh()));
    }

    /**
     * Partido con su box score y los jugadores disponibles para el lineup
     * (los del propietario del torneo).
     */
    private function payload(Tournament $tournament, Game $game): array
    {
        $game->load([
            'homeTeam:id,name,short_name,abbreviation',
            'awayTeam:id,name,short_name,abbreviation',
            'battingLines.plateAppearances',
        ]);

        $players = Player::ownedBy($tournament->user_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $game->battingLines->pluck('player_id')))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'second_last_name']);

        return [
            'game' => $game,
            'players' => $players,
            'positions' => self::POSITIONS,
        ];
    }

    private function ensureGame(Tournament $tournament, Game $game): void
    {
        $this->ensureOwner($tournament);
        abort_unless($game->tournament_id === $tournament->id, 404);
    }
}
