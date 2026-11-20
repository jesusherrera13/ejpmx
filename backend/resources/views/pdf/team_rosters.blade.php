{{-- Roster de los equipos de un torneo para PDF (Dompdf): un equipo por página. Ver TournamentTeamPdfController --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Roster {{ $tournament->name }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .team { page-break-after: always; }
        .team:last-child { page-break-after: auto; }
        .tournament { color: #64748b; font-size: 10px; margin-bottom: 2px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .abbr { display: inline-block; background: #1e3a8a; color: #fff; border-radius: 3px; padding: 2px 5px; font-size: 10px; margin-right: 4px; }
        .subtitle { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; text-align: left; padding: 5px 6px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:nth-child(even) td { background: #f4f7fb; }
        .muted { color: #64748b; }
        .player { font-weight: bold; }
    </style>
</head>
<body>
    @forelse ($teams as $team)
        <div class="team">
            <div class="tournament">{{ $tournament->name }} · Roster al {{ $date }}</div>
            <h1><span class="abbr">{{ $team->abbreviation }}</span>{{ $team->name }}</h1>
            <div class="subtitle">{{ $team->players->count() }} {{ $team->players->count() === 1 ? 'jugador' : 'jugadores' }}</div>

            @if ($team->players->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th style="width: 6%">#</th>
                            <th style="width: 44%">Jugador</th>
                            <th style="width: 8%">Pos</th>
                            <th style="width: 26%">CURP</th>
                            <th style="width: 16%">Nacimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($team->players as $player)
                            <tr>
                                <td class="muted">{{ $loop->iteration }}</td>
                                <td class="player">{{ $player->full_name }}</td>
                                <td>{{ $player->position ?? '—' }}</td>
                                <td>{{ $player->curp }}</td>
                                <td>{{ $player->birthdate?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
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
