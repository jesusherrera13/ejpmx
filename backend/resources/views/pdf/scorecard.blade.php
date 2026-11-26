{{-- Scorecard de un partido para PDF (Dompdf): line score y una página por equipo. Ver BoxscorePdfController --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Scorecard partido #{{ $game->game_number }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 0 0 6px; }
        .subtitle { color: #64748b; margin-bottom: 8px; font-size: 9px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table { border-collapse: collapse; }
        .linescore { margin-bottom: 10px; }
        .linescore th, .linescore td { border: 1px solid #e2e8f0; padding: 3px 7px; text-align: center; }
        .linescore th { background: #f8fafc; color: #475569; }
        .linescore .team { text-align: left; font-weight: bold; min-width: 150px; }
        .linescore .total { background: #f1f5f9; font-weight: bold; }
        .grid { width: 100%; table-layout: fixed; }
        .grid th { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 3px 2px; }
        .grid td { border: 1px solid #e2e8f0; padding: 2px; vertical-align: middle; }
        .grid .num { text-align: center; color: #64748b; }
        .grid .player { font-weight: bold; }
        .grid .player.sub { font-weight: normal; padding-left: 10px; }
        .grid .pos { text-align: center; }
        .grid .stat { text-align: center; }
        .grid .stat-head { font-size: 7px; }
        .cell { text-align: center; height: 26px; }
        .cell.played { background: #f0fdf4; }
        .cell.unused { background: #e5e7eb; }
        .cell.next-cell { background: #fef3c7; }
        .play { white-space: nowrap; line-height: 1.25; }
        .play img { width: 9px; height: 9px; vertical-align: middle; }
        .hit { color: #1565c0; font-weight: bold; }
        .scored { color: #2e7d32; }
        .looking { display: inline-block; transform: scaleX(-1); }
        .rbi { font-size: 6px; vertical-align: super; }
        .out { color: #c62828; font-size: 6.5px; }
        .stranded { color: #1565c0; font-size: 6px; }
        .steal { color: #7b3fb8; font-size: 6px; }
        .next { color: #92400e; font-size: 6.5px; font-weight: bold; }
        .entered { color: #94a3b8; font-weight: normal; font-size: 7px; }
        .totals td { background: #f1f5f9; font-weight: bold; text-align: center; }
        .lob-row td { background: #f8fafc; color: #64748b; text-align: center; }
        .label { text-align: right !important; padding-right: 6px !important; }
    </style>
</head>
<body>
    @foreach ($cards as $card)
        <div class="page">
            <h1>{{ $game->awayTeam->name }} @ {{ $game->homeTeam->name }}</h1>
            <div class="subtitle">
                {{ $tournament->name }} · Partido #{{ $game->game_number }} · {{ ucfirst($date) }}
            </div>

            {{-- Line score de ambos equipos --}}
            <table class="linescore">
                <thead>
                    <tr>
                        <th class="team">Equipo</th>
                        @for ($inning = 1; $inning <= $innings; $inning++)
                            <th>{{ $inning }}</th>
                        @endfor
                        <th class="total">R</th>
                        <th class="total">H</th>
                        <th class="total">E</th>
                        <th class="total">LOB</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['away', 'home'] as $side)
                        <tr>
                            <td class="team">{{ $game->{"{$side}Team"}->name }}</td>
                            @for ($inning = 1; $inning <= $innings; $inning++)
                                @php($runs = $game->line_score[$side][$inning - 1] ?? null)
                                <td>{{ $runs === null ? (array_key_exists($inning - 1, $game->line_score[$side] ?? []) ? 'X' : '–') : $runs }}</td>
                            @endfor
                            <td class="total">{{ $game->{"{$side}_score"} ?? 0 }}</td>
                            <td class="total">{{ $game->{"{$side}_hits"} ?? 0 }}</td>
                            <td class="total">{{ $game->{"{$side}_errors"} ?? 0 }}</td>
                            <td class="total">{{ $game->{"{$side}_lob"} ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h2>{{ $card['team']->name }} <span class="entered">· {{ $card['side'] === 'away' ? 'Visitante' : 'Local' }}</span></h2>

            {{-- Anchos fijos en el encabezado (Dompdf no respeta colgroup): el nombre cabe y las estadísticas ocupan poco --}}
            @php($inningWidth = round(60 / $innings, 2))
            <table class="grid">
                <thead>
                    <tr>
                        <th class="num" style="width: 2%">#</th>
                        <th class="player" style="width: 17%; text-align: left">Jugador</th>
                        <th class="pos" style="width: 3%">Pos</th>
                        @for ($inning = 1; $inning <= $innings; $inning++)
                            <th style="width: {{ $inningWidth }}%">{{ $inning }}</th>
                        @endfor
                        @foreach ($card['columns'] as $title)
                            <th class="stat-head" style="width: {{ $title === 'AVG' ? '2.8' : '1.5' }}%">{{ $title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($card['rows'] as $row)
                        <tr>
                            <td class="num">{{ $row['slot'] }}</td>
                            <td class="player {{ $row['substitute'] ? 'sub' : '' }}">
                                @if ($row['substitute'])↳ @endif{{ $row['name'] }}
                                @if ($row['substitute'] && $row['entered'])<span class="entered"> ({{ $row['entered'] }}ª)</span>@endif
                            </td>
                            <td class="pos">{{ $row['position'] }}</td>
                            @for ($inning = 1; $inning <= $innings; $inning++)
                                @php($plays = $row['cells'][$inning] ?? [])
                                <td class="cell {{ $plays ? 'played' : '' }} {{ in_array($inning, $row['unused'], true) ? 'unused' : '' }} {{ in_array($inning, $row['next'], true) ? 'next-cell' : '' }}">
                                    @foreach ($plays as $play)
                                        <div class="play">
                                            @if ($play['diamond'])<img src="{{ $play['diamond'] }}" alt="">@endif
                                            <span class="{{ $play['hit'] ? 'hit' : '' }} {{ $play['bases'] === 4 ? 'scored' : '' }}">@if ($play['looking'])<span class="looking">K</span>@else{{ $play['label'] }}@endif</span>@if ($play['rbi'])<span class="rbi">{{ $play['rbi'] }}</span>@endif
                                        </div>
                                        @if ($play['steals'])
                                            <div class="steal">{{ $play['steals'] }}</div>
                                        @endif
                                        @if ($play['out'])
                                            <div class="out">× {{ $play['out'] }}</div>
                                        @elseif ($play['stranded'])
                                            <div class="stranded">LOB</div>
                                        @endif
                                    @endforeach
                                    @if (in_array($inning, $row['next'], true))
                                        <div class="next">→ {{ $inning + 1 }}ª</div>
                                    @endif
                                </td>
                            @endfor
                            @foreach ($row['stats'] as $value)
                                <td class="stat">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="totals">
                        <td colspan="3" class="label">Totales</td>
                        @for ($inning = 1; $inning <= $innings; $inning++)
                            <td>{{ $card['innings'][$inning]['played'] ? $card['innings'][$inning]['runs'] : '' }}</td>
                        @endfor
                        @foreach ($card['totals'] as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                    <tr class="lob-row">
                        <td colspan="3" class="label">LOB</td>
                        @for ($inning = 1; $inning <= $innings; $inning++)
                            <td>{{ $card['innings'][$inning]['closed'] ? $card['innings'][$inning]['lob'] : '' }}</td>
                        @endfor
                        <td colspan="{{ count($card['columns']) }}">{{ collect($card['innings'])->sum('lob') }} en el juego</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
