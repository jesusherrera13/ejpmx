<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSpreadsheets;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRosterPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Roster de un equipo en un torneo: los jugadores que lo componen en esa temporada.
 */
class TournamentRosterController extends Controller
{
    use HandlesSpreadsheets;

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

    /**
     * Vista previa de la carga del roster desde Excel/CSV: solo se lee la columna CURP
     * (las demás se ignoran, así sirve también la plantilla o el export de jugadores).
     */
    public function previewImport(Request $request, Tournament $tournament, Team $team)
    {
        $tournamentTeamId = $this->rosterOwner($tournament, $team);
        $rows = $this->readSpreadsheet($request, ['CURP' => 'curp']);

        return response()->json(['rows' => $this->analyzeImport($rows, $tournament, $tournamentTeamId)]);
    }

    // Agrega al roster los jugadores válidos del archivo; las filas con errores se omiten
    public function import(Request $request, Tournament $tournament, Team $team)
    {
        $tournamentTeamId = $this->rosterOwner($tournament, $team);
        $rows = $this->readSpreadsheet($request, ['CURP' => 'curp']);
        $analysis = $this->analyzeImport($rows, $tournament, $tournamentTeamId);

        $created = 0;
        $errors = [];

        DB::transaction(function () use ($analysis, $tournament, $tournamentTeamId, &$created, &$errors) {
            foreach ($analysis as $item) {
                if ($item['errors']) {
                    $errors[] = "Fila {$item['row']}: " . implode(' ', $item['errors']);
                    continue;
                }

                if ($item['action'] === 'create') {
                    TournamentRosterPlayer::create([
                        'tournament_team_id' => $tournamentTeamId,
                        'tournament_id' => $tournament->id,
                        'player_id' => $item['player_id'],
                    ]);
                    $created++;
                }
            }
        });

        return response()->json([
            'message' => "Carga completada: {$created} " . ($created === 1 ? 'jugador agregado' : 'jugadores agregados') . ' al roster.',
            'created' => $created,
            'updated' => 0,
            'errors' => $errors,
        ] + $this->payload($tournament, $team));
    }

    /**
     * Busca cada CURP entre los jugadores del propietario del torneo y decide: create (se agrega),
     * unchanged (ya está en este roster) o skip (con errores).
     *
     * @return list<array{row: int|string, data: array, player_id: ?int, action: string, errors: string[]}>
     */
    private function analyzeImport(array $rows, Tournament $tournament, int $tournamentTeamId): array
    {
        $curps = collect($rows)->map(fn ($row) => Str::upper(trim((string) $row['curp'])))->unique();

        $players = Player::ownedBy($tournament->user_id)
            ->whereIn('curp', $curps)
            ->get(['id', 'first_name', 'last_name', 'second_last_name', 'curp', 'is_active'])
            ->keyBy(fn ($player) => Str::upper($player->curp));

        // Equipo del torneo en cuyo roster está ya cada jugador
        $rostered = DB::table('tournament_roster_players')
            ->join('tournament_team', 'tournament_team.id', '=', 'tournament_roster_players.tournament_team_id')
            ->join('teams', 'teams.id', '=', 'tournament_team.team_id')
            ->where('tournament_roster_players.tournament_id', $tournament->id)
            ->whereIn('tournament_roster_players.player_id', $players->pluck('id'))
            ->get(['tournament_roster_players.player_id', 'tournament_roster_players.tournament_team_id', 'teams.name'])
            ->keyBy('player_id');

        $result = [];
        $seenCurps = [];

        foreach ($rows as $rowNumber => $row) {
            $curp = Str::upper(trim((string) $row['curp']));
            $player = $players->get($curp);
            $errors = [];
            $action = 'create';

            // readSpreadsheet ya omite las filas sin CURP
            if (isset($seenCurps[$curp])) {
                $errors[] = "CURP repetida en el archivo (fila {$seenCurps[$curp]}).";
            } elseif (! $player) {
                $errors[] = 'No existe un jugador con esta CURP en tu catálogo.';
            } elseif (! $player->is_active) {
                $errors[] = 'El jugador está inactivo.';
            } elseif ($roster = $rostered->get($player->id)) {
                if ((int) $roster->tournament_team_id === $tournamentTeamId) {
                    $action = 'unchanged';
                } else {
                    $errors[] = "Ya está en el roster de {$roster->name} en este torneo.";
                }
            }

            $seenCurps[$curp] ??= $rowNumber;

            $result[] = [
                'row' => $rowNumber,
                'data' => [
                    'curp' => $curp,
                    'name' => $player ? collect([$player->first_name, $player->last_name, $player->second_last_name])->filter()->implode(' ') : null,
                ],
                'player_id' => $player?->id,
                'action' => $errors ? 'skip' : $action,
                'errors' => $errors,
            ];
        }

        return $result;
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
