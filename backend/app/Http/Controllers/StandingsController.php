<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsTables;
use App\Models\Game;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tabla de posiciones de un torneo a una fecha: se calcula con los partidos con marcador
 * final jugados hasta ese día (inclusive).
 */
class StandingsController extends Controller
{
    use ExportsTables;

    // Exponente de la fórmula pitagórica (récord esperado por carreras anotadas y permitidas)
    private const PYTHAGOREAN_EXPONENT = 1.83;

    public function index(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($this->standings($request, $tournament));
    }

    /**
     * Descarga la tabla a la fecha en Excel o PDF (format: xlsx o pdf).
     */
    public function export(Request $request, Tournament $tournament, string $format)
    {
        $this->ensureOwner($tournament);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $data = $this->standings($request, $tournament);

        $columns = [
            ['title' => 'Equipo', 'width' => 28],
            ['title' => 'G', 'width' => 6, 'align' => 'center'],
            ['title' => 'P', 'width' => 6, 'align' => 'center'],
            ['title' => 'PCT', 'width' => 8, 'align' => 'center'],
            ['title' => 'DIF.', 'width' => 7, 'align' => 'center'],
            ['title' => 'U10', 'width' => 8, 'align' => 'center'],
            ['title' => 'RACHA', 'width' => 8, 'align' => 'center'],
            ['title' => 'CA', 'width' => 7, 'align' => 'center'],
            ['title' => 'CP', 'width' => 7, 'align' => 'center'],
            ['title' => 'DIF C', 'width' => 8, 'align' => 'center'],
            ['title' => 'X-G/P', 'width' => 8, 'align' => 'center'],
            ['title' => 'CASA', 'width' => 8, 'align' => 'center'],
            ['title' => 'VISITANTE', 'width' => 11, 'align' => 'center'],
            ['title' => '≥.500', 'width' => 8, 'align' => 'center'],
        ];

        $rows = $data['standings']->map(fn ($row) => [
            $row['team']->name,
            $row['w'],
            $row['l'],
            $row['pct'] === null ? '-' : ltrim(number_format($row['pct'], 3), '0'),
            $row['gb'] == 0 ? '-' : number_format($row['gb'], 1),
            $row['last10'],
            $row['streak'],
            $row['rs'],
            $row['ra'],
            $row['diff'] > 0 ? "+{$row['diff']}" : $row['diff'],
            $row['expected'],
            $row['home'],
            $row['away'],
            $row['vs_500'],
        ])->all();

        $title = "{$tournament->name} · Posiciones";
        $subtitle = 'Al '.Carbon::parse($data['date'])->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
            .": {$data['games_played']} de {$data['games_total']} partidos jugados";
        $filename = 'posiciones_'.Str::slug($tournament->name, '_')."_{$data['date']}.{$format}";

        return $format === 'xlsx'
            ? $this->downloadTableXlsx($title, $subtitle, 'Posiciones', $columns, $rows, $filename)
            : $this->downloadTablePdf($title, $subtitle, $columns, $rows, $filename);
    }

    /**
     * Tabla a la fecha del request (sin fecha, la del último partido jugado).
     */
    private function standings(Request $request, Tournament $tournament): array
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);

        $games = Game::where('tournament_id', $tournament->id)->orderBy('scheduled_at')->orderBy('id')->get();
        $finished = $games->filter(fn (Game $game) => $game->home_score !== null && $game->away_score !== null && $game->home_score !== $game->away_score);

        // Sin fecha: la del último partido jugado (o el inicio del calendario si aún no hay)
        $date = $request->input('date')
            ?? ($finished->last() ?? $games->first())?->scheduled_at?->toDateString()
            ?? Carbon::today()->toDateString();

        $played = $finished->filter(fn (Game $game) => $game->scheduled_at->toDateString() <= $date)->values();

        $teamIds = DB::table('tournament_team')->where('tournament_id', $tournament->id)->pluck('team_id');
        $teams = Team::withTrashed()->whereIn('id', $teamIds)->get(['id', 'name', 'short_name', 'abbreviation'])->keyBy('id');

        // Resultados de cada equipo en orden cronológico
        $results = $teams->map(fn () => [])->all();
        foreach ($played as $game) {
            foreach (['home' => 'away', 'away' => 'home'] as $side => $other) {
                $teamId = $game->{"{$side}_team_id"};
                if (! isset($results[$teamId])) {
                    continue;
                }
                $runs = $game->{"{$side}_score"};
                $allowed = $game->{"{$other}_score"};
                $results[$teamId][] = [
                    'won' => $runs > $allowed,
                    'home' => $side === 'home',
                    'opponent' => $game->{"{$other}_team_id"},
                    'runs' => $runs,
                    'allowed' => $allowed,
                ];
            }
        }

        $rows = [];
        foreach ($teams as $teamId => $team) {
            $list = collect($results[$teamId]);
            $wins = $list->where('won', true)->count();
            $losses = $list->count() - $wins;
            $rows[$teamId] = [
                'team' => $team,
                'w' => $wins,
                'l' => $losses,
                'pct' => $list->isEmpty() ? null : round($wins / $list->count(), 3),
                'rs' => $list->sum('runs'),
                'ra' => $list->sum('allowed'),
                'last10' => $this->record($list->slice(-10)),
                'streak' => $this->streak($list),
                'home' => $this->record($list->where('home', true)),
                'away' => $this->record($list->where('home', false)),
            ];
        }

        // Rivales con récord de .500 o mejor a la fecha
        $winning = collect($rows)->filter(fn ($row) => $row['pct'] !== null && $row['pct'] >= 0.5)->keys()->all();

        foreach ($rows as $teamId => &$row) {
            $row['diff'] = $row['rs'] - $row['ra'];
            $row['expected'] = $this->expectedRecord($row['rs'], $row['ra'], $row['w'] + $row['l']);
            $row['vs_500'] = $this->record(collect($results[$teamId])->whereIn('opponent', $winning));
        }
        unset($row);

        // Orden: porcentaje, más victorias, diferencia de carreras y nombre
        $rows = collect($rows)->sort(fn ($a, $b) => [($b['pct'] ?? -1), $b['w'], $b['diff'], $a['team']->name]
            <=> [($a['pct'] ?? -1), $a['w'], $a['diff'], $b['team']->name])->values();

        // Juegos detrás del líder
        $leader = $rows->first();
        $rows = $rows->map(fn ($row) => $row + [
            'gb' => $leader ? (($leader['w'] - $row['w']) + ($row['l'] - $leader['l'])) / 2 : 0,
        ]);

        return [
            'date' => $date,
            'first_date' => $games->first()?->scheduled_at?->toDateString(),
            'last_date' => $games->last()?->scheduled_at?->toDateString(),
            'games_played' => $played->count(),
            'games_total' => $games->count(),
            'standings' => $rows,
        ];
    }

    // "G-P" de una lista de resultados
    private function record($results): string
    {
        $wins = collect($results)->where('won', true)->count();

        return $wins.'-'.(collect($results)->count() - $wins);
    }

    // Racha actual: "G3" (ganados seguidos) o "P2" (perdidos), null sin partidos
    private function streak($results): ?string
    {
        $last = $results->last();
        if (! $last) {
            return null;
        }

        $count = 0;
        foreach ($results->reverse() as $result) {
            if ($result['won'] !== $last['won']) {
                break;
            }
            $count++;
        }

        return ($last['won'] ? 'G' : 'P').$count;
    }

    // Récord esperado (pitagórico) para los partidos jugados, null si no hay carreras
    private function expectedRecord(int $scored, int $allowed, int $games): ?string
    {
        if ($scored + $allowed === 0) {
            return null;
        }

        $ratio = $scored ** self::PYTHAGOREAN_EXPONENT / ($scored ** self::PYTHAGOREAN_EXPONENT + $allowed ** self::PYTHAGOREAN_EXPONENT);
        $wins = (int) round($games * $ratio);

        return $wins.'-'.($games - $wins);
    }
}
