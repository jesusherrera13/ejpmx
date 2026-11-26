<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Posiciones y líderes para el portal (sin sesión): solo torneos públicos y activos.
 *  - featuredStandings: posiciones de algunos torneos al azar (portada)
 *  - standings: posiciones completas de un torneo
 *  - leaders: líderes de bateo y de equipo de un torneo (o de uno al azar), como en MLB
 */
class PublicStatsController extends Controller
{
    // Turnos al bat por partido del equipo para calificar en promedios (MLB usa 3.1 con 9 entradas;
    // los torneos amateur juegan menos entradas)
    private const QUALIFYING_PA_PER_GAME = 2.5;

    private const LEADERS_PER_CATEGORY = 5;

    public function __construct(
        private StandingsController $standingsCalculator,
        private PlayerStatsController $statsCalculator,
    ) {
    }

    public function featuredStandings(Request $request)
    {
        $count = $request->validate(['count' => 'nullable|integer|min:1|max:6'])['count'] ?? 3;

        // Al azar, pero primero los que ya tienen partidos con marcador
        $tournaments = $this->publicTournaments()
            ->withCount(['games as played_count' => fn ($q) => $q->whereNotNull('home_score')->whereNotNull('away_score')])
            ->get()
            ->shuffle()
            ->sortByDesc(fn ($t) => $t->played_count > 0)
            ->take($count);

        return response()->json($tournaments->map(fn (Tournament $tournament) => $this->standingsPayload($request, $tournament))->values());
    }

    public function standings(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        return response()->json($this->standingsPayload($request, $tournament));
    }

    public function leaders(Request $request)
    {
        $validated = $request->validate(['tournament_id' => 'nullable|integer']);

        if (isset($validated['tournament_id'])) {
            $tournament = $this->publicTournaments()->findOrFail($validated['tournament_id']);
        } else {
            // Al azar entre los que ya tienen bateo capturado; si ninguno, cualquiera público
            $tournament = $this->publicTournaments()->whereHas('games.battingLines')->inRandomOrder()->first()
                ?? $this->publicTournaments()->inRandomOrder()->first();
        }

        if (! $tournament) {
            return response()->json(['tournament' => null, 'categories' => []]);
        }

        $stats = $this->statsCalculator->stats(new Request(), $tournament);
        $standings = $this->standingsCalculator->standings(new Request(), $tournament);

        $teamGames = collect($standings['standings'])->mapWithKeys(fn ($row) => [$row['team']->id => $row['w'] + $row['l']]);
        $players = collect($stats['players']);
        $qualified = $players->filter(fn ($p) => $p['pa'] > 0 && $p['pa'] >= ($teamGames[$p['team']['id']] ?? 0) * self::QUALIFYING_PA_PER_GAME);

        $rate = fn ($value) => $value === null ? null : ltrim(number_format($value, 3), '0');

        $categories = [
            $this->playerCategory('avg', 'Porcentaje de bateo', 'AVG', $qualified, $rate),
            $this->playerCategory('hr', 'Home runs', 'HR', $players),
            $this->playerCategory('rbi', 'Carreras impulsadas', 'RBI', $players),
            $this->playerCategory('h', 'Hits', 'H', $players),
            $this->playerCategory('r', 'Carreras anotadas', 'R', $players),
            $this->playerCategory('sb', 'Bases robadas', 'SB', $players),
            $this->playerCategory('ops', 'OPS', 'OPS', $qualified, $rate),
            [
                'key' => 'wins',
                'label' => 'Juegos ganados',
                'abbr' => 'G',
                'type' => 'team',
                'leaders' => collect($standings['standings'])
                    ->filter(fn ($row) => $row['w'] > 0)
                    ->sortByDesc('w')
                    ->take(self::LEADERS_PER_CATEGORY)
                    ->map(fn ($row) => [
                        'name' => $row['team']->name,
                        'team' => ['id' => $row['team']->id, 'name' => $row['team']->name, 'abbreviation' => $row['team']->abbreviation],
                        'value' => $row['w'],
                        'display' => "{$row['w']}-{$row['l']}",
                    ])->values(),
            ],
        ];

        return response()->json([
            'tournament' => ['id' => $tournament->id, 'name' => $tournament->name],
            'date' => $stats['date'],
            'games' => $stats['games'],
            'categories' => $categories,
        ]);
    }

    private function playerCategory(string $key, string $label, string $abbr, Collection $players, ?callable $format = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'abbr' => $abbr,
            'type' => 'player',
            'leaders' => $players
                ->filter(fn ($p) => $p[$key] !== null && $p[$key] > 0)
                ->sort(fn ($a, $b) => [$b[$key], $a['name']] <=> [$a[$key], $b['name']])
                ->take(self::LEADERS_PER_CATEGORY)
                ->map(fn ($p) => [
                    'name' => $p['name'],
                    'position' => $p['position'],
                    'team' => $p['team'],
                    'value' => $p[$key],
                    'display' => $format ? $format($p[$key]) : (string) $p[$key],
                ])->values(),
        ];
    }

    private function standingsPayload(Request $request, Tournament $tournament): array
    {
        $data = $this->standingsCalculator->standings($request, $tournament);

        return [
            'tournament' => ['id' => $tournament->id, 'name' => $tournament->name],
            'date' => $data['date'],
            'games_played' => $data['games_played'],
            'games_total' => $data['games_total'],
            'standings' => collect($data['standings'])->map(fn ($row) => [
                'team' => [
                    'id' => $row['team']->id,
                    'name' => $row['team']->name,
                    'short_name' => $row['team']->short_name,
                    'abbreviation' => $row['team']->abbreviation,
                ],
            ] + collect($row)->except('team')->all())->values(),
        ];
    }

    private function publicTournaments(): Builder
    {
        return Tournament::query()->where('is_public', true)->where('is_active', true);
    }

    private function ensurePublic(Tournament $tournament): void
    {
        abort_unless($tournament->is_public && $tournament->is_active, 404);
    }
}
