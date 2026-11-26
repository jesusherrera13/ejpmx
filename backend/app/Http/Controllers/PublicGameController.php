<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calendario y resultados para el portal (sin sesión): solo torneos públicos y activos.
 *  - tournaments: torneos que se pueden ver, con su primer y último día de juego y sus equipos
 *  - dates: días con partidos de un torneo (para la tira de fechas y el calendario)
 *  - games: partidos de un día (date=) o de una ventana de días con juego alrededor de una fecha
 *    (around=, before= días antes, after= días desde esa fecha), como el ticker de MLB/LMB
 */
class PublicGameController extends Controller
{
    private const TEAM_COLUMNS = ['id', 'name', 'short_name', 'abbreviation'];

    public function tournaments()
    {
        $tournaments = Tournament::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->withMin('games', 'scheduled_at')
            ->withMax('games', 'scheduled_at')
            ->withCount('games')
            ->with(['teams' => fn ($query) => $query->withTrashed()->select(array_map(fn ($c) => "teams.{$c}", self::TEAM_COLUMNS))->orderBy('teams.name')])
            ->orderBy('name')
            ->get();

        return response()->json($tournaments->map(fn (Tournament $tournament) => [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'games_count' => $tournament->games_count,
            'first_date' => $tournament->games_min_scheduled_at ? substr($tournament->games_min_scheduled_at, 0, 10) : null,
            'last_date' => $tournament->games_max_scheduled_at ? substr($tournament->games_max_scheduled_at, 0, 10) : null,
            'teams' => $tournament->teams->map(fn (Team $team) => $team->only(self::TEAM_COLUMNS))->values(),
        ])->values());
    }

    public function dates(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);
        $teamId = $request->validate(['team_id' => 'nullable|integer'])['team_id'] ?? null;

        return response()->json($this->gameDates($tournament, $teamId)->values());
    }

    public function games(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'around' => 'nullable|date_format:Y-m-d',
            'before' => 'nullable|integer|min:0|max:14',
            'after' => 'nullable|integer|min:0|max:14',
            'team_id' => 'nullable|integer',
        ]);
        $teamId = $validated['team_id'] ?? null;
        $dates = $this->gameDates($tournament, $teamId);

        if (isset($validated['date'])) {
            $window = collect([$validated['date']]);
        } else {
            $around = $validated['around'] ?? Carbon::today()->toDateString();
            $window = $dates->filter(fn ($date) => $date < $around)->slice(-($validated['before'] ?? 1))
                ->merge($dates->filter(fn ($date) => $date >= $around)->take($validated['after'] ?? 3))
                ->values();
        }

        $from = $window->first();
        $to = $window->last();

        $games = $from === null ? collect() : Game::query()
            ->where('tournament_id', $tournament->id)
            ->whereBetween('scheduled_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->when($teamId, fn ($query) => $query->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId)))
            ->with(['homeTeam:' . implode(',', self::TEAM_COLUMNS), 'awayTeam:' . implode(',', self::TEAM_COLUMNS)])
            ->orderBy('scheduled_at')
            ->orderBy('game_number')
            ->get();

        $records = $this->records($tournament);

        return response()->json([
            'tournament' => ['id' => $tournament->id, 'name' => $tournament->name],
            'from' => $from,
            'to' => $to,
            'prev_date' => $from === null ? $dates->last() : $dates->filter(fn ($date) => $date < $from)->last(),
            'next_date' => $to === null ? null : $dates->first(fn ($date) => $date > $to),
            'games' => $games->map(fn (Game $game) => $this->present($game, $records))->values(),
        ]);
    }

    private function ensurePublic(Tournament $tournament): void
    {
        abort_unless($tournament->is_public && $tournament->is_active, 404);
    }

    // Días con partidos del torneo ("Y-m-d"), en orden
    private function gameDates(Tournament $tournament, ?int $teamId): Collection
    {
        return DB::table('games')
            ->where('tournament_id', $tournament->id)
            ->when($teamId, fn ($query) => $query->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId)))
            ->selectRaw('DATE(scheduled_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => (string) $day);
    }

    // Ganados y perdidos de cada equipo con los partidos terminados del torneo
    private function records(Tournament $tournament): array
    {
        $records = [];
        $games = Game::where('tournament_id', $tournament->id)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['id', 'scheduled_at', 'status', 'home_team_id', 'away_team_id', 'home_score', 'away_score']);

        foreach ($games as $game) {
            if ($game->effectiveStatus() !== 'final' || $game->home_score === $game->away_score) {
                continue;
            }
            $homeWon = $game->home_score > $game->away_score;
            foreach ([$game->home_team_id => $homeWon, $game->away_team_id => ! $homeWon] as $teamId => $won) {
                $records[$teamId] ??= ['w' => 0, 'l' => 0];
                $records[$teamId][$won ? 'w' : 'l']++;
            }
        }

        return $records;
    }

    private function present(Game $game, array $records): array
    {
        $status = $game->effectiveStatus();
        $showScore = in_array($status, ['in_progress', 'final', 'suspended'], true);

        $team = function (?Team $team, string $side) use ($game, $records, $showScore) {
            $record = $records[$team?->id] ?? ['w' => 0, 'l' => 0];

            return [
                'id' => $team?->id,
                'name' => $team?->name,
                'short_name' => $team?->short_name,
                'abbreviation' => $team?->abbreviation,
                'record' => "{$record['w']}-{$record['l']}",
                'runs' => $showScore ? $game->{"{$side}_score"} : null,
                'hits' => $showScore ? $game->{"{$side}_hits"} : null,
                'errors' => $showScore ? $game->{"{$side}_errors"} : null,
                'line' => $showScore ? ($game->line_score[$side] ?? null) : null,
            ];
        };

        return [
            'id' => $game->id,
            'date' => $game->scheduled_at?->toDateString(),
            'time' => $game->scheduledTime(),
            'status' => $status,
            'round' => $game->round,
            'matchday' => $game->matchday,
            'series' => $game->series,
            'series_game' => $game->series_game,
            'game_number' => $game->game_number,
            'away' => $team($game->awayTeam, 'away'),
            'home' => $team($game->homeTeam, 'home'),
        ];
    }
}
