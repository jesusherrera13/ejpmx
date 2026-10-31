{{-- Calendario del torneo para PDF (Dompdf): una tabla por jornada con sus partidos o sus series --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario {{ $tournament->name }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .subtitle { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; page-break-inside: avoid; }
        th { background: #f8fafc; color: #475569; text-align: left; padding: 5px 6px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
        tr.group td { background: #eef2f7; padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .team { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $fmt = fn ($date) => $date->locale('es')->isoFormat('ddd DD [de] MMM');
        $range = fn ($start, $end) => $fmt($start) . ($end->ne($start) ? ' – ' . $fmt($end) : '');
        $isSeries = $view === 'series';
    @endphp

    <h1>{{ $tournament->name }}</h1>
    <div class="subtitle">
        Calendario por {{ $isSeries ? 'series' : 'partidos' }} ·
        {{ $series->flatten()->count() }} series y {{ $matchdays->flatten()->count() }} partidos en {{ $matchdays->count() }} jornadas ·
        {{ $tournament->games_per_series }} {{ $tournament->games_per_series === 1 ? 'juego' : 'juegos' }} por serie
    </div>

    @foreach ($matchdays as $matchday => $games)
        <table>
            <thead>
                <tr class="group">
                    <td colspan="{{ $isSeries ? 6 : 7 }}">
                        <strong>Jornada {{ $matchday }}</strong>
                        <span class="muted"> · Vuelta {{ $games->first()->round }} · {{ $range($games->first()->scheduled_date, $games->last()->scheduled_date) }}</span>
                        @isset($byes[$matchday])
                            <span class="muted"> · Descansa <strong>{{ $byes[$matchday]->name }}</strong></span>
                        @endisset
                    </td>
                </tr>
                @if ($isSeries)
                    <tr>
                        <th style="width: 7%">Serie</th>
                        <th class="right" style="width: 28%">Visitante</th>
                        <th class="center" style="width: 4%"></th>
                        <th style="width: 28%">Local</th>
                        <th style="width: 23%">Fechas</th>
                        <th class="center" style="width: 10%">Juegos</th>
                    </tr>
                @else
                    <tr>
                        <th style="width: 6%">#</th>
                        <th style="width: 14%">Fecha</th>
                        <th class="right" style="width: 28%">Visitante</th>
                        <th class="center" style="width: 4%"></th>
                        <th style="width: 28%">Local</th>
                        <th class="center" style="width: 10%">Juego</th>
                        <th class="center" style="width: 10%">Resultado</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @if ($isSeries)
                    @foreach ($series[$matchday] as $item)
                        <tr>
                            <td class="muted">{{ $item->number }}</td>
                            <td class="right team">{{ $item->away->name }}</td>
                            <td class="center muted">@</td>
                            <td class="team">{{ $item->home->name }}</td>
                            <td>{{ $range($item->start, $item->end) }}</td>
                            <td class="center">{{ $item->games }}</td>
                        </tr>
                    @endforeach
                @else
                    @foreach ($games as $game)
                        <tr>
                            <td class="muted">{{ $game->game_number }}</td>
                            <td>{{ $fmt($game->scheduled_date) }}</td>
                            <td class="right team">{{ $game->awayTeam->name }}</td>
                            <td class="center muted">@</td>
                            <td class="team">{{ $game->homeTeam->name }}</td>
                            <td class="center">{{ $game->series_game }} de {{ $tournament->games_per_series }}</td>
                            <td class="center">
                                @if ($game->away_score !== null && $game->home_score !== null)
                                    {{ $game->away_score }} - {{ $game->home_score }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    @endforeach
</body>
</html>
