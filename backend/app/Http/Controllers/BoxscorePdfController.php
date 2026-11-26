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
 *    (jugada, corrido, robos, impulsadas, outs en base, DP, PK, pisa y corre y LOB)
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

    // Estadísticas por jugador del scorecard (como en The Perfect Boxscore); AVG se calcula
    private const SCORECARD_STATS = ['ab' => 'AB', 'r' => 'R', 'h' => 'H', 'rbi' => 'RBI', 'bb' => 'BB', 'so' => 'K', 'sb' => 'SB', 'pk' => 'PK', 'gidp' => 'DP', 'lob' => 'LOB'];

    // Robos en la casilla: SB2·3·H
    private const STEAL_SHORT = [2 => '2', 3 => '3', 4 => 'H'];

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

        [$nextUp, $closed, $dpOuts] = $this->inningEnders($lines, $innings);

        $rows = [];
        $slot = 0;
        foreach ($lines as $line) {
            if (! $line->is_substitute || $slot === 0) {
                $slot++;
            }

            $cells = [];
            foreach ($line->plateAppearances->groupBy('inning') as $inning => $appearances) {
                $cells[$inning] = $appearances->sortBy('sequence')->map(function (GamePlateAppearance $play) use ($dpOuts, $closed, $inning) {
                    $dpOut = $dpOuts[$play->id] ?? null;
                    $onBase = $play->bases >= 1 && $play->bases <= 3 && ! $play->out_on_base && ! $dpOut;
                    $steals = GamePlateAppearance::parseSteals($play->stolen_bases);
                    $out = match (true) {
                        $dpOut !== null => trim(($play->dp_notation ?: '').' DP'.(['', '①', '②', '③'][$dpOut] ?? '')),
                        $play->out_on_base && $play->out_type === 'PK' => trim('PK '.$play->out_notation),
                        $play->out_on_base && $play->out_type === 'PC' => trim('PC '.$play->out_notation),
                        $play->out_on_base => $play->out_notation ?: 'Out',
                        default => null,
                    };

                    return [
                        'label' => $this->label($play),
                        'looking' => in_array($play->result, ['KC', 'KDC'], true) && ! $play->notation,
                        'hit' => in_array($play->result, self::HITS, true),
                        'rbi' => $play->rbi,
                        'bases' => $play->bases,
                        'diamond' => $play->bases > 0 ? $this->diamond($play->bases, $play->out_on_base || $dpOut !== null, $steals) : null,
                        'steals' => $steals ? 'SB'.implode('·', array_map(fn ($base) => self::STEAL_SHORT[$base], $steals)) : null,
                        'out' => $out,
                        'stranded' => $onBase && in_array($inning, $closed, true),
                    ];
                })->values()->all();
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
                'stats' => [...array_values($line->only(array_keys(self::SCORECARD_STATS))), $this->avg($line->h, $line->ab)],
            ];
        }

        $appearances = $lines->flatMap->plateAppearances;
        $inningTotals = [];
        for ($inning = 1; $inning <= $innings; $inning++) {
            $plays = $appearances->where('inning', $inning);
            $inningTotals[$inning] = [
                'runs' => $plays->where('scored', true)->count(),
                'lob' => $plays->filter(fn ($play) => $play->bases >= 1 && $play->bases <= 3 && ! $play->out_on_base && ! isset($dpOuts[$play->id]))->count(),
                'closed' => in_array($inning, $closed, true),
                'played' => $plays->isNotEmpty(),
            ];
        }

        return [
            'side' => $side,
            'team' => $game->{"{$side}Team"},
            'rows' => $rows,
            'innings' => $inningTotals,
            'columns' => array_values(self::SCORECARD_STATS + ['avg' => 'AVG']),
            'totals' => collect(self::SCORECARD_STATS)->keys()->mapWithKeys(fn ($key) => [$key => $lines->sum($key)])
                ->put('avg', $this->avg($lines->sum('h'), $lines->sum('ab')))->all(),
        ];
    }

    // Promedio de bateo en formato beisbolero: .333, 1.000
    private function avg(int $hits, int $atBats): string
    {
        $text = number_format($atBats ? $hits / $atBats : 0, 3);

        return str_starts_with($text, '0') ? substr($text, 1) : $text;
    }

    /**
     * Entradas cerradas con tres outs, el bateador que se quedó en turno (abre la siguiente entrada) y los
     * corredores puestos out en un doble play. Reglas de The Perfect Boxscore: cada jugada suma los outs
     * del bateador (en un DP solo el suyo), +1 por cada out en base y +1 por el corredor indicado en el DP.
     * El orden de los turnos sale del orden al bat: cada entrada empieza con el turno que sigue al último.
     *
     * @return array{0: array<int, int[]>, 1: int[], 2: array<int, int>} [id del renglón => entradas en que se quedó
     *                                                                   en turno, entradas cerradas, id del turno del corredor => número de out del DP]
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

        $nextUp = [];
        $closed = [];
        $dpOuts = [];
        $slot = 0;
        for ($inning = 1; $inning <= $innings; $inning++) {
            $pending = $lines->flatMap->plateAppearances->where('inning', $inning)->sortBy('sequence')->values();
            if ($pending->isEmpty()) {
                continue;
            }

            // Turnos de la entrada en el orden al bat
            $ordered = [];
            $used = [];
            for ($guard = 0; count($used) < $pending->count() && $guard < 100; $guard++) {
                $batter = $batterFor($slots[$slot], $inning);
                $play = $pending->first(fn ($item) => $item->game_batting_line_id === $batter->id && ! isset($used[$item->id]));
                $slot = ($slot + 1) % count($slots);
                if ($play) {
                    $used[$play->id] = true;
                    $ordered[] = $play;
                }
            }

            $outs = 0;
            $last = null;
            foreach ($ordered as $index => $play) {
                // DP: el corredor indicado (si seguía en base) es out antes que el bateador
                if ($play->result === 'DP' && $play->dp_runner_order) {
                    $runnerLine = $lines[$play->dp_runner_order - 1] ?? null;
                    $runner = collect(array_slice($ordered, 0, $index))->last(fn ($item) => $runnerLine
                        && $item->game_batting_line_id === $runnerLine->id && $item->bases >= 1 && $item->bases <= 3
                        && ! $item->out_on_base && ! isset($dpOuts[$item->id]));
                    if ($runner) {
                        $outs++;
                        $dpOuts[$runner->id] = min($outs, 3);
                    }
                }
                $outs += (GamePlateAppearance::OUTS[$play->result] ?? 0) + ($play->out_on_base ? 1 : 0);
                $last = $play;
                if ($outs >= 3) {
                    break;
                }
            }

            if ($last && $outs >= 3) {
                $closed[] = $inning;
                // El turno que sigue al último es el que abre la siguiente entrada
                $lastSlot = collect($slots)->search(fn ($slotLines) => collect($slotLines)->contains('id', $last->game_batting_line_id));
                $slot = ($lastSlot + 1) % count($slots);
                if ($inning < $innings) {
                    $nextUp[$batterFor($slots[$slot], $inning)->id][] = $inning;
                }
            }
        }

        return [$nextUp, $closed, $dpOuts];
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

    // Diamante con el corrido del bateador, con los colores de impresión de The Perfect Boxscore:
    // recorrido azul (verde y relleno si anotó), tramo morado si robó esa base, X roja si fue out en base
    // y punto en la base en que se quedó
    private function diamond(int $bases, bool $out, array $steals = []): string
    {
        $points = [[10, 18], [18, 10], [10, 2], [2, 10], [10, 18]];
        $color = $bases === 4 ? '#1e7d46' : '#1f5fbf';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            .'<polygon points="10,2 18,10 10,18 2,10" fill="'.($bases === 4 ? '#c8e6d2' : 'none').'" stroke="#8a939e" stroke-width="1.1"/>';
        for ($i = 0; $i < min($bases, 4); $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[$i + 1];
            $svg .= '<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="'.(in_array($i + 1, $steals, true) ? '#7b3fb8' : $color).'" stroke-width="2.4" stroke-linecap="round"/>';
        }
        if ($bases >= 1 && $bases <= 3) {
            [$x, $y] = $points[$bases];
            $svg .= $out
                ? '<path d="M'.($x - 3).' '.($y - 3).' L'.($x + 3).' '.($y + 3).' M'.($x - 3).' '.($y + 3).' L'.($x + 3).' '.($y - 3).'" stroke="#c62828" stroke-width="2" stroke-linecap="round"/>'
                : '<circle cx="'.$x.'" cy="'.$y.'" r="2.9" fill="#1f5fbf"/>';
        }
        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
