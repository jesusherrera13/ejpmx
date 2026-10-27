<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Support\RoundRobinScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rol de juegos (calendario) de un torneo.
 */
class GameController extends Controller
{
    private const TEAM_COLUMNS = 'id,name,short_name,abbreviation';

    /**
     * Partidos del torneo y, por jornada, el equipo que descansa (número impar de equipos).
     */
    public function index(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $games = $tournament->games()
            ->with(['homeTeam:' . self::TEAM_COLUMNS, 'awayTeam:' . self::TEAM_COLUMNS])
            ->orderBy('game_number')
            ->get();

        $teams = $tournament->teams()->get(['teams.id', 'teams.name', 'teams.short_name', 'teams.abbreviation'])->keyBy('id');

        $byes = [];
        foreach ($games->groupBy('matchday') as $matchday => $matchdayGames) {
            $playing = $matchdayGames->pluck('home_team_id')->merge($matchdayGames->pluck('away_team_id'));
            $resting = $teams->keys()->diff($playing)->first();

            if ($resting !== null) {
                $byes[$matchday] = $teams[$resting];
            }
        }

        return response()->json([
            'games' => $games,
            'byes' => (object) $byes,
        ]);
    }

    /**
     * Genera (o vuelve a generar) el calendario todos contra todos con los equipos inscritos.
     */
    public function generate(Request $request, Tournament $tournament, RoundRobinScheduler $scheduler)
    {
        $this->ensureOwner($tournament);
        $this->ensureNoResults($tournament);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'rounds' => 'required|integer|min:1|max:4',
            'games_per_series' => 'required|integer|min:1|max:7',
            'rest_days' => 'required|integer|min:0|max:7',
        ], [], [
            'start_date' => 'fecha de inicio',
            'rounds' => 'vueltas',
            'games_per_series' => 'juegos por serie',
            'rest_days' => 'días de descanso',
        ]);

        $teamIds = $tournament->teams()->pluck('teams.id')->all();
        abort_if(count($teamIds) < 2, 422, 'Se necesitan al menos 2 equipos inscritos para generar el calendario.');

        $schedule = $scheduler->generate(
            $teamIds,
            $validated['rounds'],
            $validated['games_per_series'],
            CarbonImmutable::parse($validated['start_date']),
            $validated['rest_days']
        );

        DB::transaction(function () use ($tournament, $schedule) {
            $tournament->games()->delete();

            $now = now();
            $rows = array_map(fn ($game) => $game + [
                'tournament_id' => $tournament->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $schedule['games']);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('games')->insert($chunk);
            }
        });

        return $this->index($tournament);
    }

    /**
     * Elimina el calendario completo del torneo.
     */
    public function destroy(Tournament $tournament)
    {
        $this->ensureOwner($tournament);
        $this->ensureNoResults($tournament);

        $tournament->games()->delete();

        return response()->json([
            'message' => 'Calendario eliminado exitosamente'
        ], 200);
    }

    // Con resultados capturados el calendario ya no se puede rehacer
    private function ensureNoResults(Tournament $tournament): void
    {
        abort_if(
            $tournament->games()->where(fn ($query) => $query->whereNotNull('home_score')->orWhereNotNull('away_score'))->exists(),
            422,
            'El calendario ya tiene resultados capturados y no se puede modificar.'
        );
    }
}
