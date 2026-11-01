<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRosterPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Roster de un equipo en un torneo: los jugadores que lo componen en esa temporada.
 */
class TournamentRosterController extends Controller
{
    private const PLAYER_COLUMNS = ['players.id', 'players.first_name', 'players.last_name', 'players.second_last_name', 'players.curp', 'players.birthdate'];

    /**
     * Roster del equipo y jugadores disponibles: los activos del propietario del torneo
     * que no están en el roster de ningún equipo del torneo.
     */
    public function index(Tournament $tournament, Team $team)
    {
        $this->rosterOwner($tournament, $team);

        return response()->json($this->payload($tournament, $team));
    }

    public function store(Request $request, Tournament $tournament, Team $team)
    {
        $tournamentTeamId = $this->rosterOwner($tournament, $team);

        $validated = $request->validate([
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('players', 'id')->where('user_id', $tournament->user_id)->whereNull('deleted_at'),
                Rule::unique('tournament_roster_players', 'player_id')->where('tournament_id', $tournament->id),
            ],
        ], [
            'player_ids.*.unique' => 'Uno de los jugadores ya está en el roster de un equipo de este torneo.',
        ], [
            'player_ids' => 'jugadores',
            'player_ids.*' => 'jugador',
        ]);

        DB::transaction(function () use ($validated, $tournament, $tournamentTeamId) {
            foreach ($validated['player_ids'] as $playerId) {
                TournamentRosterPlayer::create([
                    'tournament_team_id' => $tournamentTeamId,
                    'tournament_id' => $tournament->id,
                    'player_id' => $playerId,
                ]);
            }
        });

        return response()->json($this->payload($tournament, $team), 201);
    }

    // Por id para poder quitar también a un jugador que ya se eliminó del catálogo
    public function destroy(Tournament $tournament, Team $team, int $player)
    {
        $tournamentTeamId = $this->rosterOwner($tournament, $team);

        TournamentRosterPlayer::where('tournament_team_id', $tournamentTeamId)->where('player_id', $player)->delete();

        return response()->json($this->payload($tournament, $team));
    }

    private function payload(Tournament $tournament, Team $team): array
    {
        $tournamentTeamId = $this->tournamentTeamId($tournament, $team);

        $roster = Player::withTrashed()
            ->join('tournament_roster_players', 'tournament_roster_players.player_id', '=', 'players.id')
            ->where('tournament_roster_players.tournament_team_id', $tournamentTeamId)
            ->orderBy('players.first_name')
            ->orderBy('players.last_name')
            ->get([...self::PLAYER_COLUMNS, 'tournament_roster_players.created_at as added_at']);

        $available = Player::ownedBy($tournament->user_id)
            ->where('is_active', true)
            ->whereNotIn('id', TournamentRosterPlayer::where('tournament_id', $tournament->id)->select('player_id'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(self::PLAYER_COLUMNS);

        return ['roster' => $roster, 'available' => $available];
    }

    // El equipo debe estar inscrito en el torneo; devuelve el id de la inscripción
    private function rosterOwner(Tournament $tournament, Team $team): int
    {
        $this->ensureOwner($tournament);

        return $this->tournamentTeamId($tournament, $team);
    }

    private function tournamentTeamId(Tournament $tournament, Team $team): int
    {
        $id = DB::table('tournament_team')->where('tournament_id', $tournament->id)->where('team_id', $team->id)->value('id');
        abort_if($id === null, 404, 'El equipo no está inscrito en este torneo.');

        return $id;
    }
}
