<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Support\RoundRobinScheduler;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        [$games, $byes] = $this->savedSchedule($tournament);

        return response()->json([
            'games' => $games,
            'byes' => (object) $byes,
        ]);
    }

    /**
     * Descarga el calendario en Excel, un renglón por partido o por serie (?view=games|series).
     */
    public function exportXlsx(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $view = $this->exportView($request);
        [$games, $byes] = $this->savedSchedule($tournament);
        abort_if($games->isEmpty(), 422, 'El torneo aún no tiene calendario.');

        // Encabezado => ancho de columna, y los renglones con el mismo orden
        if ($view === 'series') {
            $columns = ['Jornada' => 10, 'Vuelta' => 9, 'Serie' => 8, 'Visitante' => 30, 'Local' => 30, 'Inicio' => 13, 'Fin' => 13, 'Juegos' => 9, 'Descansa' => 30];
            $dateColumns = ['F', 'G'];
            $rows = $this->seriesForExport($games)->map(fn ($series) => [
                $series->matchday,
                $series->round,
                $series->number,
                $series->away->name,
                $series->home->name,
                ExcelDate::PHPToExcel($series->start),
                ExcelDate::PHPToExcel($series->end),
                $series->games,
                $byes[$series->matchday]->name ?? null,
            ]);
        } else {
            $columns = ['Jornada' => 10, 'Vuelta' => 9, 'Serie' => 8, 'Juego' => 8, 'Fecha' => 13, 'Visitante' => 30, 'Local' => 30, 'Carreras visitante' => 18, 'Carreras local' => 15, 'Descansa' => 30];
            $dateColumns = ['E'];
            $rows = $this->sortedForExport($games)->map(fn ($game) => [
                $game->matchday,
                $game->round,
                $game->series,
                $game->series_game,
                ExcelDate::PHPToExcel($game->scheduled_date),
                $game->awayTeam->name,
                $game->homeTeam->name,
                $game->away_score,
                $game->home_score,
                $byes[$game->matchday]->name ?? null,
            ]);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($view === 'series' ? 'Series' : 'Partidos');

        $sheet->setCellValue('A1', $tournament->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $lastRow = $rows->count() + 3;

        $sheet->fromArray(array_keys($columns), null, 'A3');
        $sheet->getStyle("A3:{$lastColumn}3")->getFont()->setBold(true);
        $sheet->getStyle("A3:{$lastColumn}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F7');
        $sheet->fromArray($rows->all(), null, 'A4');

        foreach ($dateColumns as $column) {
            $sheet->getStyle("{$column}4:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }
        foreach (array_values($columns) as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
        $sheet->freezePane('A4');
        $sheet->setAutoFilter("A3:{$lastColumn}{$lastRow}");

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->exportFilename($tournament, $view, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga el calendario en PDF, una tabla por jornada con sus partidos o sus series (?view=games|series).
     */
    public function exportPdf(Request $request, Tournament $tournament)
    {
        $this->ensureOwner($tournament);

        $view = $this->exportView($request);
        [$games, $byes] = $this->savedSchedule($tournament);
        abort_if($games->isEmpty(), 422, 'El torneo aún no tiene calendario.');

        $html = view('pdf.calendar', [
            'tournament' => $tournament,
            'view' => $view,
            'matchdays' => $this->sortedForExport($games)->groupBy('matchday'),
            'series' => $this->seriesForExport($games)->groupBy('matchday'),
            'byes' => $byes,
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->exportFilename($tournament, $view, 'pdf') . '"',
        ]);
    }

    /**
     * Partidos guardados con sus equipos y, por jornada, el equipo que descansa.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: array<int, \App\Models\Team>}
     */
    private function savedSchedule(Tournament $tournament): array
    {
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

        return [$games, $byes];
    }

    // Mismo orden que la vista Partidos: por jornada y, dentro de ella, por fecha
    private function sortedForExport(Collection $games): Collection
    {
        return $games->sortBy([
            ['matchday', 'asc'],
            fn ($a, $b) => $a->scheduled_date <=> $b->scheduled_date,
            ['game_number', 'asc'],
        ])->values();
    }

    // Una serie por renglón: equipos, primer y último día y número de juegos
    private function seriesForExport(Collection $games): Collection
    {
        return $games->groupBy('series')->map(fn ($seriesGames, $number) => (object) [
            'number' => $number,
            'matchday' => $seriesGames->first()->matchday,
            'round' => $seriesGames->first()->round,
            'away' => $seriesGames->first()->awayTeam,
            'home' => $seriesGames->first()->homeTeam,
            'start' => $seriesGames->min('scheduled_date'),
            'end' => $seriesGames->max('scheduled_date'),
            'games' => $seriesGames->count(),
        ])->values();
    }

    private function exportView(Request $request): string
    {
        return $request->validate(['view' => 'nullable|in:games,series'])['view'] ?? 'games';
    }

    private function exportFilename(Tournament $tournament, string $view, string $extension): string
    {
        $prefix = $view === 'series' ? 'calendario_series_' : 'calendario_partidos_';

        return $prefix . Str::slug($tournament->name, '_') . '.' . $extension;
    }

    /**
     * Vista previa del calendario con los parámetros indicados, sin guardar nada.
     */
    public function preview(Request $request, Tournament $tournament, RoundRobinScheduler $scheduler)
    {
        $this->ensureOwner($tournament);

        $schedule = $this->buildSchedule($request, $tournament, $scheduler);
        $teams = $tournament->teams()->get(['teams.id', 'teams.name', 'teams.short_name', 'teams.abbreviation'])->keyBy('id');

        // Misma forma que index() para que el frontend lo pinte igual
        $games = array_map(fn ($game) => $game + [
            'home_team' => $teams[$game['home_team_id']],
            'away_team' => $teams[$game['away_team_id']],
        ], $schedule['games']);

        $byes = array_map(fn ($teamId) => $teams[$teamId], $schedule['byes']);

        return response()->json([
            'games' => $games,
            'byes' => (object) $byes,
        ]);
    }

    /**
     * Genera (o vuelve a generar) el calendario todos contra todos con los equipos inscritos
     * y guarda en el torneo los juegos por serie con que se generó.
     */
    public function generate(Request $request, Tournament $tournament, RoundRobinScheduler $scheduler)
    {
        $this->ensureOwner($tournament);
        $this->ensureNoResults($tournament);

        $schedule = $this->buildSchedule($request, $tournament, $scheduler);

        DB::transaction(function () use ($tournament, $schedule, $request) {
            $tournament->update(['games_per_series' => $request->integer('games_per_series')]);
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

    /**
     * Valida los parámetros y arma el rol de juegos con los equipos inscritos.
     *
     * @return array{games: array<int, array<string, mixed>>, byes: array<int, int>}
     */
    private function buildSchedule(Request $request, Tournament $tournament, RoundRobinScheduler $scheduler): array
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'rounds' => 'required|integer|min:1|max:4',
            'games_per_series' => ['required', 'integer', Rule::in(Tournament::GAMES_PER_SERIES_OPTIONS)],
            'rest_days' => 'required|integer|min:0|max:7',
        ], [], [
            'start_date' => 'fecha de inicio',
            'rounds' => 'vueltas',
            'games_per_series' => 'juegos por serie',
            'rest_days' => 'días de descanso',
        ]);

        $teamIds = $tournament->teams()->pluck('teams.id')->all();
        abort_if(count($teamIds) < 2, 422, 'Se necesitan al menos 2 equipos inscritos para generar el calendario.');

        return $scheduler->generate(
            $teamIds,
            $validated['rounds'],
            $validated['games_per_series'],
            CarbonImmutable::parse($validated['start_date']),
            $validated['rest_days']
        );
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
