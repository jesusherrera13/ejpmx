{{-- Jugadores de la temporada para PDF (Dompdf): una tabla por equipo con su roster --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Jugadores {{ $tournament->name }}</title>
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
        .player { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $tournament->name }}</h1>
    <div class="subtitle">
        Jugadores de la temporada · {{ $total }} {{ $total === 1 ? 'jugador' : 'jugadores' }} en {{ $teams->count() }} {{ $teams->count() === 1 ? 'equipo' : 'equipos' }}
    </div>

    @forelse ($teams as $team => $players)
        <table>
            <thead>
                <tr class="group">
                    <td colspan="4">
                        <strong>{{ $team }}</strong>
                        <span class="muted"> · {{ $players->count() }} {{ $players->count() === 1 ? 'jugador' : 'jugadores' }}</span>
                    </td>
                </tr>
                <tr>
                    <th style="width: 6%">#</th>
                    <th style="width: 50%">Jugador</th>
                    <th style="width: 28%">CURP</th>
                    <th style="width: 16%">Nacimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($players as $player)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td class="player">{{ $player->full_name }}</td>
                        <td>{{ $player->curp }}</td>
                        <td>{{ $player->birthdate?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="muted">Aún no hay jugadores en los rosters de este torneo.</div>
    @endforelse
</body>
</html>
