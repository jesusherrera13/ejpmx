{{-- Roster de los equipos con su bateo acumulado en el torneo, para PDF (Dompdf): un equipo por página. Ver TournamentTeamPdfController --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas {{ $tournament->name }}</title>
    <style>
        @page { margin: 26px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        .team { page-break-after: always; }
        .team:last-child { page-break-after: auto; }
        .tournament { color: #64748b; font-size: 10px; margin-bottom: 2px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .abbr { display: inline-block; background: #1e3a8a; color: #fff; border-radius: 3px; padding: 2px 5px; font-size: 10px; margin-right: 4px; }
        .subtitle { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; padding: 5px 3px; border-bottom: 2px solid #e2e8f0; text-align: right; white-space: nowrap; }
        td { padding: 4px 3px; border-bottom: 1px solid #f1f5f9; text-align: right; white-space: nowrap; }
        th.left, td.left { text-align: left; }
        tbody tr:nth-child(even) td { background: #f4f7fb; }
        tfoot td { font-weight: bold; border-top: 2px solid #e2e8f0; border-bottom: none; background: #f8fafc; }
        .muted { color: #64748b; }
        .player { font-weight: bold; }
        .zero { color: #94a3b8; }
    </style>
</head>
<body>
    @forelse ($teams as $team)
        <div class="team">
            <div class="tournament">
                {{ $tournament->name }} · Estadísticas de bateo al {{ $date }} · {{ $games }} {{ $games === 1 ? 'partido' : 'partidos' }} con box score capturado
            </div>
            <h1><span class="abbr">{{ $team->abbreviation }}</span>{{ $team->name }}</h1>
            <div class="subtitle">{{ $team->players->count() }} {{ $team->players->count() === 1 ? 'jugador' : 'jugadores' }} en el roster</div>

            @if ($team->players->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th class="left">#</th>
                            <th class="left">Jugador</th>
                            <th class="left">Pos</th>
                            @foreach ($columns as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>AVG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($team->players as $player)
                            <tr>
                                <td class="left muted">{{ $loop->iteration }}</td>
                                <td class="left player">{{ $player->full_name }}</td>
                                <td class="left">{{ $player->position ?? '—' }}</td>
                                @foreach ($columns as $key => $label)
                                    <td class="{{ $player->stats[$key] ? '' : 'zero' }}">{{ $player->stats[$key] }}</td>
                                @endforeach
                                <td>{{ $player->avg }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="left" colspan="3">Totales</td>
                            @foreach ($columns as $key => $label)
                                <td>{{ $team->totals[$key] ?? '' }}</td>
                            @endforeach
                            <td>{{ $team->avg }}</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <div class="muted">Este equipo aún no tiene jugadores en su roster.</div>
            @endif
        </div>
    @empty
        <h1>{{ $tournament->name }}</h1>
        <div class="muted">Aún no hay equipos inscritos en este torneo.</div>
    @endforelse
</body>
</html>
