<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PDFs de los equipos inscritos en un torneo (menú Acciones de la pestaña Equipos):
 *  - roster: los jugadores del roster de cada equipo, un equipo por página
 *  - stats: el mismo roster con el bateo acumulado de cada jugador en el torneo
 */
class TournamentTeamPdfController extends Controller
{
    // Columnas de bateo en el orden del box score detallado (ver BoxscorePdfController)
    private const STATS = [
        'pa' => 'PA', 'ab' => 'AB', 'h' => 'H', 'singles' => '1B', 'ih' => 'IH', 'doubles' => '2B', 'triples' => '3B',
        'hr' => 'HR', 'r' => 'R', 'rbi' => 'RBI', 'bb' => 'BB', 'ibb' => 'IBB', 'hbp' => 'HBP', 'sf' => 'SF', 'sh' => 'SH',
        'roe' => 'ROE', 'so' => 'K', 'so_swinging' => 'KS', 'so_looking' => 'KC', 'lob' => 'LOB',
    ];

    public function roster(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $teams = $this->teams($tournament);

        // Imprimir Roster de un solo equipo (menú de su renglón): team_id
        $teamId = request()->validate(['team_id' => 'nullable|integer'])['team_id'] ?? null;
        if ($teamId) {
            $teams = $teams->where('id', (int) $teamId)->values();
            abort_if($teams->isEmpty(), 404);
        }

        $html = view('pdf.team_rosters', [
            'tournament' => $tournament,
            'teams' => $teams,
            'date' => $this->longDate(Carbon::today()),
        ])->render();

        $name = $teamId ? "{$tournament->name} {$teams->first()->name}" : $tournament->name;

        return $this->pdf($html, 'portrait', 'roster_'.Str::slug($name, '_').'.pdf');
    }

    public function stats(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $teams = $this->teams($tournament);

        // Bateo acumulado por jugador y equipo en los box scores capturados del torneo
        $sums = DB::table('game_batting_lines')
            ->join('games', 'games.id', '=', 'game_batting_lines.game_id')
            ->where('games.tournament_id', $tournament->id)
            ->whereNotNull('game_batting_lines.player_id')
            ->groupBy('game_batting_lines.player_id', 'game_batting_lines.team_id')
            ->select('game_batting_lines.player_id', 'game_batting_lines.team_id')
            ->selectRaw('COUNT(DISTINCT game_batting_lines.game_id) as g')
            ->selectRaw(collect(self::STATS)->keys()->map(fn ($column) => "SUM(game_batting_lines.{$column}) as {$column}")->implode(', '))
            ->get()
            ->keyBy(fn ($row) => "{$row->player_id}-{$row->team_id}");

        $columns = array_merge(['g'], array_keys(self::STATS));
        $teams->each(function ($team) use ($sums, $columns) {
            $team->players->each(function ($player) use ($sums, $columns, $team) {
                $row = $sums->get("{$player->id}-{$team->id}");
                $player->stats = collect($columns)->mapWithKeys(fn ($column) => [$column => (int) ($row->{$column} ?? 0)])->all();
                $player->avg = $player->stats['ab'] ? ltrim(number_format($player->stats['h'] / $player->stats['ab'], 3), '0') : '-';
            });

            // Sin total de juegos: no es la suma de los juegos de sus jugadores
            $totals = collect($columns)->mapWithKeys(fn ($column) => [$column => $team->players->sum(fn ($player) => $player->stats[$column])])->all();
            $totals['g'] = null;
            $team->totals = $totals;
            $team->avg = $totals['ab'] ? ltrim(number_format($totals['h'] / $totals['ab'], 3), '0') : '-';
        });

        $games = DB::table('game_batting_lines')
            ->join('games', 'games.id', '=', 'game_batting_lines.game_id')
            ->where('games.tournament_id', $tournament->id)
            ->distinct()
            ->count('game_batting_lines.game_id');

        $html = view('pdf.team_stats', [
            'tournament' => $tournament,
            'teams' => $teams,
            'columns' => array_merge(['g' => 'J'], self::STATS),
            'games' => $games,
            'date' => $this->longDate(Carbon::today()),
        ])->render();

        return $this->pdf($html, 'landscape', 'estadisticas_roster_'.Str::slug($tournament->name, '_').'.pdf');
    }

    /**
     * Equipos inscritos ordenados por nombre, cada uno con los jugadores de su roster.
     */
    private function teams(Tournament $tournament): Collection
    {
        $players = DB::table('tournament_roster_players')
            ->join('tournament_team', 'tournament_team.id', '=', 'tournament_roster_players.tournament_team_id')
            ->join('players', 'players.id', '=', 'tournament_roster_players.player_id')
            ->leftJoin('fielding_positions', 'fielding_positions.id', '=', 'tournament_roster_players.fielding_position_id')
            ->where('tournament_roster_players.tournament_id', $tournament->id)
            ->orderBy('players.first_name')
            ->orderBy('players.last_name')
            ->orderBy('players.second_last_name')
            ->get([
                'players.id', 'players.first_name', 'players.last_name', 'players.second_last_name', 'players.curp', 'players.birthdate',
                'tournament_team.team_id', 'fielding_positions.code as position',
            ])
            ->each(function ($player) {
                $player->full_name = collect([$player->first_name, $player->last_name, $player->second_last_name])->filter()->implode(' ');
                $player->birthdate = $player->birthdate ? Carbon::parse($player->birthdate) : null;
            })
            ->groupBy('team_id');

        return $tournament->teams()->reorder()->orderBy('teams.name')->get()
            ->each(fn ($team) => $team->setRelation('players', $players->get($team->id, collect())->values()));
    }

    private function longDate(Carbon $date): string
    {
        return $date->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }

    private function pdf(string $html, string $orientation, string $filename)
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', $orientation);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
