<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameBattingLine;
use App\Models\GamePlateAppearance;
use App\Models\Tournament;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Box score guardado de un partido en PDF, según la modalidad que se está viendo:
 *  - score: marcador final (R H E LOB)
 *  - linescore: carreras por entrada y R H E LOB
 *  - batting / batting_full: marcador y el lineup de cada equipo con sus columnas de bateo
 *  - scorecard: line score y, por equipo, la cuadrícula de turnos por entrada como en la captura
 *    (jugada, corrido, impulsadas, outs en base, LOB y "Dejó N")
 */
class BoxscorePdfController extends Controller
{
    // Columnas de bateo por modalidad, en el orden de la captura (ver BoxscoreModal)
    private const BATTING_COLUMNS = [
        'batting' => ['ab' => 'AB', 'h' => 'H', 'r' => 'R', 'rbi' => 'RBI', 'so' => 'K'],
        'batting_full' => [
            'pa' => 'PA', 'ab' => 'AB', 'h' => 'H', 'singles' => '1B', 'ih' => 'IH', 'doubles' => '2B', 'triples' => '3B',
            'hr' => 'HR', 'r' => 'R', 'rbi' => 'RBI', 'bb' => 'BB', 'ibb' => 'IBB', 'hbp' => 'HBP', 'sf' => 'SF', 'sh' => 'SH',
            'roe' => 'ROE', 'so' => 'K', 'so_swinging' => 'KS', 'so_looking' => 'KC', 'lob' => 'LOB',
        ],
    ];

    // Nombre del archivo por modalidad; el scorecard no lleva sufijo
    private const SUFFIXES = ['score' => '_score', 'linescore' => '_line_score', 'batting' => '_bateo', 'batting_full' => '_bateo_detallado', 'scorecard' => ''];

    // Outs que hace cada jugada; en DP y FC uno de ellos es de un corredor (ver BoxscoreModal)
    private const OUTS = ['KS' => 1, 'KC' => 1, 'GO' => 1, 'FO' => 1, 'LO' => 1, 'DP' => 2, 'FC' => 1, 'SF' => 1, 'SH' => 1];

    private const RUNNER_OUTS_IN_PLAY = ['DP' => 1, 'FC' => 1];

    private const HITS = ['1B', 'IH', '2B', '3B', 'HR'];

    // Etiqueta de la jugada cuando no tiene anotación (KC se dibuja como K invertida en la vista)
    private const LABELS = ['KS' => 'K', 'KC' => 'K', 'KDS' => 'K+', 'KDC' => 'K+'];

    /**
     * mode: modalidad del box score (por omisión, scorecard).
     * team: away, home o both (por omisión, los dos equipos); solo aplica a las modalidades con lineup.
     */
    public function __invoke(Request $request, Tournament $tournament, Game $game)
    {
        $this->ensureOwner($tournament);
        abort_unless($game->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(Game::BOXSCORE_MODES)],
            'team' => 'nullable|in:away,home,both',
        ]);
        $mode = $validated['mode'] ?? 'scorecard';
        $team = $validated['team'] ?? 'both';
        $sides = $team === 'both' ? ['away', 'home'] : [$team];

        $game->load(['homeTeam', 'awayTeam', 'battingLines.plateAppearances', 'battingLines.player']);

        $html = $mode === 'scorecard'
            ? $this->scorecardHtml($tournament, $game, $sides)
            : $this->boxscoreHtml($tournament, $game, $mode, $sides);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        // [partido]_[visitante]_vs_[local]_[aaaa-mm-dd].pdf (las otras modalidades llevan su sufijo)
        $filename = $game->game_number.'_'.Str::slug($game->awayTeam->name, '_').'_vs_'.Str::slug($game->homeTeam->name, '_')
            .'_'.Carbon::parse($game->scheduled_at)->toDateString().self::SUFFIXES[$mode].'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function scorecardHtml(Tournament $tournament, Game $game, array $sides): string
    {
        abort_if($game->battingLines->isEmpty(), 422, 'El partido aún no tiene lineup capturado.');

        // Entradas del scorecard: las del line score o, si no hay, la última con turnos
        $innings = max(9, count($game->line_score['away'] ?? []), (int) $game->battingLines->flatMap->plateAppearances->max('inning'));

        $cards = collect($sides)->map(fn ($side) => $this->card($game, $side, $innings));

        return view('pdf.scorecard', [
            'tournament' => $tournament,
            'game' => $game,
            'date' => $this->longDate($game),
            'innings' => $innings,
            'cards' => $cards,
        ])->render();
    }

    /**
     * Score, Line Score, Bateo y Bateo detallado: el marcador y, en las de bateo, el lineup de cada equipo.
     */
    private function boxscoreHtml(Tournament $tournament, Game $game, string $mode, array $sides): string
    {
        abort_if($game->away_score === null || $game->home_score === null, 422, 'El partido aún no tiene marcador capturado.');

        $columns = self::BATTING_COLUMNS[$mode] ?? null;
        abort_if($columns && $game->battingLines->isEmpty(), 422, 'El partido aún no tiene lineup capturado.');

        $lineups = [];
        foreach ($columns ? $sides : [] as $side) {
            $lines = $game->battingLines->where('team_id', $game->{"{$side}_team_id"})->values();
            $slot = 0;
            $rows = $lines->map(function (GameBattingLine $line) use (&$slot, $columns) {
                if (! $line->is_substitute || $slot === 0) {
                    $slot++;
                }

                return [
                    'slot' => $line->is_substitute ? null : $slot,
                    'substitute' => $line->is_substitute,
                    'entered' => $line->entered_inning,
                    'name' => $this->playerName($line),
                    'position' => $line->position,
                    'stats' => collect($columns)->keys()->map(fn ($key) => $line->{$key})->all(),
                ];
            })->all();

            $lineups[] = [
                'side' => $side,
                'team' => $game->{"{$side}Team"},
                'rows' => $rows,
                'totals' => collect($columns)->keys()->map(fn ($key) => $lines->sum($key))->all(),
            ];
        }

        // El line score se muestra si se capturó (en Line Score o Scorecard)
        $innings = $mode === 'score' ? 0 : count($game->line_score['away'] ?? []);

        return view('pdf.boxscore', [
            'tournament' => $tournament,
            'game' => $game,
            'date' => $this->longDate($game),
            'title' => ['score' => 'Score', 'linescore' => 'Line Score', 'batting' => 'Bateo', 'batting_full' => 'Bateo detallado'][$mode],
            'innings' => $innings,
            'columns' => $columns ? array_values($columns) : [],
            'lineups' => $lineups,
        ])->render();
    }

    private function longDate(Game $game): string
    {
        return Carbon::parse($game->scheduled_at)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    }

    private function playerName(GameBattingLine $line): string
    {
        return $line->player
            ? collect([$line->player->first_name, $line->player->last_name, $line->player->second_last_name])->filter()->implode(' ')
            : ($line->placeholder ?: 'Por asignar');
    }

    /**
     * Cuadrícula de un equipo: renglones del lineup con sus turnos por entrada.
     */
    private function card(Game $game, string $side, int $innings): array
    {
        $teamId = $game->{"{$side}_team_id"};
        $lines = $game->battingLines->where('team_id', $teamId)->values();

        [$ending, $nextUp, $closed] = $this->inningEnders($lines, $innings);

        $rows = [];
        $slot = 0;
        foreach ($lines as $line) {
            if (! $line->is_substitute || $slot === 0) {
                $slot++;
            }

            $cells = [];
            foreach ($line->plateAppearances->groupBy('inning') as $inning => $appearances) {
                $cells[$inning] = $appearances->sortBy('sequence')->map(fn (GamePlateAppearance $play) => [
                    'label' => $this->label($play),
                    'looking' => in_array($play->result, ['KC', 'KDC'], true) && ! $play->notation,
                    'hit' => in_array($play->result, self::HITS, true),
                    'rbi' => $play->rbi,
                    'bases' => $play->bases,
                    'diamond' => $play->bases > 0 ? $this->diamond($play->bases, $play->out_on_base) : null,
                    'out' => $play->out_on_base ? ($play->out_notation ?: 'Out') : null,
                    'stranded' => $play->bases >= 1 && $play->bases <= 3 && ! $play->out_on_base,
                    'left' => $ending[$play->id] ?? 0,
                ])->values()->all();
            }

            // Se quedó en turno al cerrar la entrada: su turno pasa a la siguiente
            $next = $nextUp[$line->id] ?? [];

            // En una entrada cerrada, la casilla de quien no bateó (ni se quedó en turno) ya no se usa
            $unused = array_values(array_filter($closed, fn ($inning) => ! isset($cells[$inning]) && ! in_array($inning, $next, true)));

            $rows[] = [
                'slot' => $line->is_substitute && $slot > 0 ? null : $slot,
                'substitute' => $line->is_substitute,
                'entered' => $line->entered_inning,
                'name' => $this->playerName($line),
                'position' => $line->position,
                'cells' => $cells,
                'next' => $next,
                'unused' => $unused,
                'stats' => $line->only(['ab', 'r', 'h', 'rbi', 'bb', 'so', 'lob']),
            ];
        }

        $appearances = $lines->flatMap->plateAppearances;
        $inningTotals = [];
        for ($inning = 1; $inning <= $innings; $inning++) {
            $plays = $appearances->where('inning', $inning);
            $inningTotals[$inning] = [
                'runs' => $plays->where('scored', true)->count(),
                'lob' => $plays->filter(fn ($play) => $play->bases >= 1 && $play->bases <= 3 && ! $play->out_on_base)->count(),
                'played' => $plays->isNotEmpty(),
            ];
        }

        return [
            'side' => $side,
            'team' => $game->{"{$side}Team"},
            'rows' => $rows,
            'innings' => $inningTotals,
            'totals' => collect(['ab', 'r', 'h', 'rbi', 'bb', 'so', 'lob'])->mapWithKeys(fn ($key) => [$key => $lines->sum($key)])->all(),
        ];
    }

    /**
     * Turno que cerró cada entrada con tres outs => corredores que dejó en base ("Dejó N"), y el bateador
     * que se quedó en turno (abre la siguiente entrada).
     * El orden de los turnos sale del orden al bat: cada entrada empieza con el turno que sigue al último.
     *
     * @return array{0: array<int, int>, 1: array<int, int[]>, 2: int[]} [id del turno => corredores dejados, id del renglón => entradas
     *                                                                   en que se quedó en turno, entradas cerradas con tres outs]
     */
    private function inningEnders(Collection $lines, int $innings): array
    {
        // Turnos al bat: el titular seguido de sus cambios
        $slots = [];
        foreach ($lines as $line) {
            if ($line->is_substitute && $slots) {
                $slots[count($slots) - 1][] = $line;
            } else {
                $slots[] = [$line];
            }
        }
        if (! $slots) {
            return [[], [], []];
        }

        // Batea el último cambio que ya había entrado en esa entrada, o el titular
        $batterFor = fn (array $slotLines, int $inning) => collect($slotLines)->reduce(
            fn ($carry, $line) => $line->is_substitute && $line->entered_inning && $line->entered_inning <= $inning ? $line : $carry,
            $slotLines[0]
        );

        $result = [];
        $nextUp = [];
        $closed = [];
        $slot = 0;
        for ($inning = 1; $inning <= $innings; $inning++) {
            $pending = $lines->flatMap->plateAppearances->where('inning', $inning)->sortBy('sequence')->values();
            if ($pending->isEmpty()) {
                continue;
            }

            $used = [];
            $last = null;
            for ($guard = 0; count($used) < $pending->count() && $guard < 100; $guard++) {
                $batter = $batterFor($slots[$slot], $inning);
                $play = $pending->first(fn ($item) => $item->game_batting_line_id === $batter->id && ! isset($used[$item->id]));
                $slot = ($slot + 1) % count($slots);
                if ($play) {
                    $used[$play->id] = true;
                    $last = $play;
                }
            }

            $runnerOuts = $pending->where('out_on_base', true)->count();
            $inPlay = $pending->sum(fn ($play) => self::RUNNER_OUTS_IN_PLAY[$play->result] ?? 0);
            $outs = $pending->sum(fn ($play) => self::OUTS[$play->result] ?? 0) + $runnerOuts - min($runnerOuts, $inPlay);

            $stranded = $pending->filter(fn ($play) => $play->id !== $last?->id && $play->bases >= 1 && $play->bases <= 3 && ! $play->out_on_base)->count();
            if ($last && $outs >= 3 && $stranded) {
                $result[$last->id] = $stranded;
            }

            if ($last && $outs >= 3) {
                $closed[] = $inning;
            }

            // El turno que sigue al último es el que abre la siguiente entrada
            if ($last && $outs >= 3 && $inning < $innings) {
                $nextUp[$batterFor($slots[$slot], $inning)->id][] = $inning;
            }
        }

        return [$result, $nextUp, $closed];
    }

    // Como en la captura: 1B-8 en hits, K+WP en tercer strike no retenido, 6-4-3 DP y la anotación en las demás
    private function label(GamePlateAppearance $play): string
    {
        $base = self::LABELS[$play->result] ?? $play->result;
        if (! $play->notation) {
            return $base;
        }

        return match (true) {
            in_array($play->result, self::HITS, true) => "{$base}-{$play->notation}",
            in_array($play->result, ['KDS', 'KDC'], true) => "{$base}{$play->notation}",
            $play->result === 'DP' => "{$play->notation} DP",
            default => $play->notation,
        };
    }

    // Diamante con el corrido del bateador (verde si anotó, rojo si lo pusieron out), como imagen SVG
    private function diamond(int $bases, bool $out): string
    {
        $points = ['10,18', '18,10', '10,2', '2,10', '10,18'];
        $color = $bases === 4 ? '#2e7d32' : ($out ? '#c62828' : '#1565c0');
        $fill = $bases === 4 ? '#2e7d32' : 'none';
        $path = implode(' ', array_slice($points, 0, $bases + 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            .'<polygon points="10,2 18,10 10,18 2,10" fill="'.$fill.'" stroke="#cbd5e1" stroke-width="1.5"/>'
            .'<polyline points="'.$path.'" fill="none" stroke="'.$color.'" stroke-width="3" stroke-linejoin="round"/>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
