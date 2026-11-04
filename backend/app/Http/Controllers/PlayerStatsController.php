<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsTables;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Estadísticas de bateo de los jugadores de un torneo a una fecha: suma de su bateo en los
 * box scores capturados de los partidos jugados hasta ese día (inclusive).
 */
class PlayerStatsController extends Controller
{
    use ExportsTables;

    private const SUMS = ['pa', 'ab', 'r', 'h', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'hbp', 'sf', 'so'];

    public function index(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($this->stats($request, $tournament));
    }

    /**
     * Descarga las estadísticas a la fecha en Excel o PDF (format: xlsx o pdf), ordenadas por HR
     * como la tabla; con team_id solo las de ese equipo.
     */
    public function export(Request $request, Tournament $tournament, string $format)
    {
        $this->ensureOwner($tournament);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $teamId = $request->validate(['team_id' => 'nullable|integer'])['team_id'] ?? null;
        $data = $this->stats($request, $tournament);

        $players = $data['players']
            ->when($teamId, fn ($players) => $players->where('team.id', (int) $teamId))
            ->sortBy([['hr', 'desc'], ['avg', 'desc'], ['name', 'asc']])
            ->values();

        $rate = fn ($value) => $value === null ? '-' : ltrim(number_format($value, 3), '0');
        $stat = fn ($title) => ['title' => $title, 'width' => 7, 'align' => 'right'];

        $columns = [
            ['title' => 'Jugador', 'width' => 34],
            ['title' => 'Pos', 'width' => 6],
            ['title' => 'Equipo', 'width' => 9],
            $stat('J'), $stat('TB'), $stat('C'), $stat('H'), $stat('2B'), $stat('3B'), $stat('HR'),
            $stat('CI'), $stat('BB'), $stat('P'), $stat('PRO'), $stat('OBP'), $stat('SLG'), $stat('OPS'),
        ];

        $rows = $players->map(fn ($player) => [
            $player['name'], $player['position'], $player['team']['abbreviation'],
            $player['g'], $player['ab'], $player['r'], $player['h'], $player['doubles'], $player['triples'], $player['hr'],
            $player['rbi'], $player['bb'], $player['so'],
            $rate($player['avg']), $rate($player['obp']), $rate($player['slg']), $rate($player['ops']),
        ])->all();

        $team = $teamId ? $data['players']->firstWhere('team.id', (int) $teamId)['team']['name'] ?? null : null;
        $title = "{$tournament->name} · Estadísticas de bateo".($team ? " · {$team}" : '');
        $subtitle = 'Al '.Carbon::parse($data['date'])->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
            .": {$data['games']} ".($data['games'] === 1 ? 'partido' : 'partidos').' con box score capturado';
        $filename = 'estadisticas_'.Str::slug($tournament->name.($team ? " {$team}" : ''), '_')."_{$data['date']}.{$format}";

        return $format === 'xlsx'
            ? $this->downloadTableXlsx($title, $subtitle, 'Bateo', $columns, $rows, $filename)
            : $this->downloadTablePdf($title, $subtitle, $columns, $rows, $filename);
    }

    /**
     * Bateo por jugador a la fecha del request (sin fecha, hoy).
     */
    private function stats(Request $request, Tournament $tournament): array
    {
        $date = $request->validate(['date' => 'nullable|date_format:Y-m-d'])['date'] ?? Carbon::today()->toDateString();

        $lines = DB::table('game_batting_lines')
            ->join('games', 'games.id', '=', 'game_batting_lines.game_id')
            ->join('players', 'players.id', '=', 'game_batting_lines.player_id')
            ->join('teams', 'teams.id', '=', 'game_batting_lines.team_id')
            ->where('games.tournament_id', $tournament->id)
            ->whereDate('games.scheduled_at', '<=', $date)
            ->get([
                'game_batting_lines.*',
                'players.first_name', 'players.last_name', 'players.second_last_name',
                'teams.name as team_name', 'teams.abbreviation as team_abbreviation',
            ]);

        // Un renglón por jugador y equipo
        $rows = $lines->groupBy(fn ($line) => "{$line->player_id}-{$line->team_id}")->map(function ($playerLines) {
            $first = $playerLines->first();
            $stats = [];
            foreach (self::SUMS as $column) {
                $stats[$column] = (int) $playerLines->sum($column);
            }

            // Posición más frecuente, sin contar emergentes si jugó a la defensiva
            $positions = $playerLines->pluck('position')->filter()->countBy()->sortDesc();
            $position = $positions->keys()->first(fn ($position) => ! in_array($position, ['PH', 'PR'], true)) ?? $positions->keys()->first();

            $singles = $stats['h'] - $stats['doubles'] - $stats['triples'] - $stats['hr'];
            $totalBases = $singles + 2 * $stats['doubles'] + 3 * $stats['triples'] + 4 * $stats['hr'];
            $obpBase = $stats['ab'] + $stats['bb'] + $stats['hbp'] + $stats['sf'];

            $avg = $stats['ab'] ? $stats['h'] / $stats['ab'] : null;
            $obp = $obpBase ? ($stats['h'] + $stats['bb'] + $stats['hbp']) / $obpBase : null;
            $slg = $stats['ab'] ? $totalBases / $stats['ab'] : null;

            return [
                'player_id' => $first->player_id,
                'name' => collect([$first->first_name, $first->last_name, $first->second_last_name])->filter()->implode(' '),
                'position' => $position,
                'team' => ['id' => $first->team_id, 'name' => $first->team_name, 'abbreviation' => $first->team_abbreviation],
                'g' => $playerLines->pluck('game_id')->unique()->count(),
            ] + $stats + [
                'tb' => $totalBases,
                'avg' => $avg === null ? null : round($avg, 3),
                'obp' => $obp === null ? null : round($obp, 3),
                'slg' => $slg === null ? null : round($slg, 3),
                'ops' => $obp === null && $slg === null ? null : round(($obp ?? 0) + ($slg ?? 0), 3),
            ];
        })->sortBy('name')->values();

        return [
            'date' => $date,
            'games' => $lines->pluck('game_id')->unique()->count(),
            'players' => $rows,
        ];
    }
}
