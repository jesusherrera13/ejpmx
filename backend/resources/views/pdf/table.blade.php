{{-- Tabla genérica para PDF (Dompdf): posiciones, estadísticas... Ver Concerns\ExportsTables --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .subtitle { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; padding: 5px 4px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        td { padding: 4px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        tbody tr:nth-child(even) td { background: #f4f7fb; }
        .left { text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .first { font-weight: bold; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="subtitle">{{ $subtitle }}</div>

    @if ($rows)
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ $column['align'] ?? 'left' }}">{{ $column['title'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $value)
                            <td class="{{ $columns[$index]['align'] ?? 'left' }} {{ $index === 0 ? 'first' : '' }}">{{ $value ?? '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">No hay datos a esta fecha.</div>
    @endif
</body>
</html>
