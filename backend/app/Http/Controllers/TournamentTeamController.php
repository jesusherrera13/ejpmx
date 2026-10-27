<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Equipos inscritos en un torneo.
 */
class TournamentTeamController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($tournament->teams()->get());
    }

    /**
     * Inscribe uno o varios equipos del mismo propietario que el torneo.
     */
    public function store(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);
        $this->ensureWithoutCalendar($tournament);

        $validated = $request->validate([
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('teams', 'id')->where('user_id', $tournament->user_id)->whereNull('deleted_at'),
            ],
        ], [], [
            'team_ids' => 'equipos',
            'team_ids.*' => 'equipo',
        ]);

        $tournament->teams()->syncWithoutDetaching($validated['team_ids']);

        return response()->json($tournament->teams()->get(), 201);
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
