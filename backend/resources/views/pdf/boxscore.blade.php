{{-- Box score para PDF (Dompdf): marcador y, en Bateo, el lineup de cada equipo. Ver BoxscorePdfController --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} partido #{{ $game->game_number }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 6px; }
        .subtitle { color: #64748b; margin-bottom: 10px; font-size: 9px; }
        table { border-collapse: collapse; }
        .linescore th, .linescore td { border: 1px solid #e2e8f0; padding: 4px 9px; text-align: center; }
        .linescore th { background: #f8fafc; color: #475569; }
        .linescore .team { text-align: left; font-weight: bold; min-width: 170px; }
        .linescore .total { background: #f1f5f9; font-weight: bold; }
        .lineup { width: 100%; }
        .lineup th { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 4px 3px; }
        .lineup td { border: 1px solid #e2e8f0; padding: 3px; text-align: center; }
        .lineup .num { color: #64748b; }
        .lineup .player { text-align: left; font-weight: bold; white-space: nowrap; }
        .lineup .player.sub { font-weight: normal; padding-left: 12px; }
        .entered { color: #94a3b8; font-weight: normal; font-size: 7px; }
        .totals td { background: #f1f5f9; font-weight: bold; }
        .label { text-align: right !important; padding-right: 6px !important; }
    </style>
</head>
<body>
    <h1>{{ $game->awayTeam->name }} @ {{ $game->homeTeam->name }}</h1>
    <div class="subtitle">
        {{ $tournament->name }} · Partido #{{ $game->game_number }} · {{ ucfirst($date) }} · {{ $title }}
    </div>

    {{-- Marcador; con line score capturado lleva las carreras por entrada --}}
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
                        <td>{{ $runs ?? 'X' }}</td>
                    @endfor
                    <td class="total">{{ $game->{"{$side}_score"} ?? 0 }}</td>
                    <td class="total">{{ $game->{"{$side}_hits"} ?? 0 }}</td>
                    <td class="total">{{ $game->{"{$side}_errors"} ?? 0 }}</td>
                    <td class="total">{{ $game->{"{$side}_lob"} ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($lineups as $lineup)
        <h2>{{ $lineup['team']->name }} <span class="entered">· {{ $lineup['side'] === 'away' ? 'Visitante' : 'Local' }}</span></h2>
        <table class="lineup">
            <thead>
                <tr>
                    <th style="width: 2%">#</th>
                    <th style="width: 22%; text-align: left">Jugador</th>
                    <th style="width: 4%">Pos</th>
                    @foreach ($columns as $title)
                        <th>{{ $title }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($lineup['rows'] as $row)
                    <tr>
                        <td class="num">{{ $row['slot'] }}</td>
                        <td class="player {{ $row['substitute'] ? 'sub' : '' }}">
                            @if ($row['substitute'])↳ @endif{{ $row['name'] }}
                            @if ($row['substitute'] && $row['entered'])<span class="entered"> ({{ $row['entered'] }}ª)</span>@endif
                        </td>
                        <td>{{ $row['position'] }}</td>
                        @foreach ($row['stats'] as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="totals">
                    <td colspan="3" class="label">Totales</td>
                    @foreach ($lineup['totals'] as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
