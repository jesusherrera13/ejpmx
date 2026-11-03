<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Equipos inscritos en un torneo.
 */
class TournamentTeamController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($this->withRosterCount($tournament));
    }

    /**
     * Inscribe uno o varios equipos del mismo propietario que el torneo.
     */
    /**
     * Equipos que se pueden inscribir: activos, del mismo propietario que el torneo y aún no inscritos.
     */
    public function available(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json(
            Team::ownedBy($tournament->user_id)
                ->where('is_active', true)
                ->whereNotIn('id', $tournament->teams()->select('teams.id'))
                ->orderByRaw('LOWER(name)')
                ->get(['id', 'name', 'short_name', 'abbreviation'])
        );
    }

    public function store(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);
        $this->ensureWithoutCalendar($tournament);

        $validated = $request->validate([
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('teams', 'id')->where('user_id', $tournament->user_id)->where('is_active', true)->whereNull('deleted_at'),
            ],
        ], [], [
            'team_ids' => 'equipos',
            'team_ids.*' => 'equipo',
        ]);

        $tournament->teams()->syncWithoutDetaching($validated['team_ids']);

        return response()->json($this->withRosterCount($tournament), 201);
    }

    /**
     * Deja inscritos exactamente los equipos de team_ids: agrega los nuevos y retira los que ya no vienen
     * (junto con su roster). Los nuevos deben ser activos y del mismo propietario que el torneo;
     * los que ya estaban inscritos pueden quedarse aunque se hayan desactivado.
     */
    public function sync(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);
        $this->ensureWithoutCalendar($tournament);

        $validated = $request->validate([
            'team_ids' => 'present|array',
            'team_ids.*' => 'integer|distinct',
        ], [], [
            'team_ids' => 'equipos',
            'team_ids.*' => 'equipo',
        ]);

        $newIds = array_diff($validated['team_ids'], $tournament->teams()->pluck('teams.id')->all());
        $allowed = Team::ownedBy($tournament->user_id)->where('is_active', true)->whereIn('id', $newIds)->count();
        abort_if($allowed !== count($newIds), 422, 'Alguno de los equipos no está disponible para este torneo.');

        $tournament->teams()->sync($validated['team_ids']);

        return response()->json($this->withRosterCount($tournament));
    }

    public function destroy(Tournament $tournament, Team $team)
    {
        $this->ensureOwner($tournament);
        $this->ensureWithoutCalendar($tournament);

        $tournament->teams()->detach($team->id);

        return response()->json([
            'message' => 'Equipo retirado del torneo'
        ], 200);
    }

    // Equipos inscritos con cuántos jugadores tiene su roster
    private function withRosterCount(Tournament $tournament)
    {
        $counts = DB::table('tournament_roster_players')
            ->join('tournament_team', 'tournament_team.id', '=', 'tournament_roster_players.tournament_team_id')
            ->where('tournament_roster_players.tournament_id', $tournament->id)
            ->groupBy('tournament_team.team_id')
            ->pluck(DB::raw('count(*)'), 'tournament_team.team_id');

        return $tournament->teams()->get()->each(fn ($team) => $team->setAttribute('roster_count', (int) ($counts[$team->id] ?? 0)));
    }

    // Cambiar los equipos dejaría el calendario inconsistente
    private function ensureWithoutCalendar(Tournament $tournament): void
    {
        abort_if(
            $tournament->games()->exists(),
            422,
            'El torneo ya tiene calendario. Elimínalo para modificar los equipos participantes.'
        );
    }
}
