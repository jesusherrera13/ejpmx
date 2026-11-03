<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Tournament;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Jugadores de la temporada: los que están en el roster de los equipos inscritos en el torneo.
 */
class TournamentPlayerController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        return response()->json($this->players($tournament));
    }

    /**
     * Descarga los jugadores en Excel, un renglón por jugador con su equipo.
     */
    public function exportXlsx(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $players = $this->players($tournament);

        // Encabezado => ancho de columna, y los renglones con el mismo orden
        $columns = ['Equipo' => 30, 'Jugador' => 36, 'CURP' => 22, 'Nacimiento' => 13];
        $rows = $players->map(fn ($player) => [
            $player->team_name,
            $player->full_name,
            $player->curp,
            $player->birthdate ? ExcelDate::PHPToExcel(Carbon::parse($player->birthdate)) : null,
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jugadores');

        $sheet->setCellValue('A1', $tournament->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $lastRow = $rows->count() + 3;

        $sheet->fromArray(array_keys($columns), null, 'A3');
        $sheet->getStyle("A3:{$lastColumn}3")->getFont()->setBold(true);
        $sheet->getStyle("A3:{$lastColumn}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F7');
        $sheet->fromArray($rows->all(), null, 'A4');

        $sheet->getStyle("D4:D{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        foreach (array_values($columns) as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
        $sheet->freezePane('A4');
        $sheet->setAutoFilter("A3:{$lastColumn}{$lastRow}");

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->exportFilename($tournament, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga los jugadores en PDF, una tabla por equipo.
     */
    public function exportPdf(Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $players = $this->players($tournament);

        $html = view('pdf.players', [
            'tournament' => $tournament,
            'teams' => $players->groupBy('team_name'),
            'total' => $players->count(),
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->exportFilename($tournament, 'pdf') . '"',
        ]);
    }

    // Ordenados por equipo y nombre; incluye jugadores que ya se eliminaron del catálogo
    private function players(Tournament $tournament): Collection
    {
        return Player::withTrashed()
            ->join('tournament_roster_players', 'tournament_roster_players.player_id', '=', 'players.id')
            ->join('tournament_team', 'tournament_team.id', '=', 'tournament_roster_players.tournament_team_id')
            ->join('teams', 'teams.id', '=', 'tournament_team.team_id')
            ->where('tournament_roster_players.tournament_id', $tournament->id)
            ->orderBy('teams.name')
            ->orderBy('players.first_name')
            ->orderBy('players.last_name')
            ->get([
                'players.id', 'players.first_name', 'players.last_name', 'players.second_last_name', 'players.curp', 'players.birthdate',
                'teams.id as team_id', 'teams.name as team_name', 'teams.abbreviation as team_abbreviation',
            ])
            ->each(function ($player) {
                $player->full_name = collect([$player->first_name, $player->last_name, $player->second_last_name])->filter()->implode(' ');
            });
    }

    private function exportFilename(Tournament $tournament, string $extension): string
    {
        return 'jugadores_' . Str::slug($tournament->name, '_') . '.' . $extension;
    }
}
