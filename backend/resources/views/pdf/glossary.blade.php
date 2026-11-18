{{-- Glosario del scorecard para PDF (Dompdf): secciones en dos columnas. Ver GlossaryPdfController --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2937; }
        h1 { font-size: 17px; margin: 0 0 12px; }
        .layout { width: 100%; border-collapse: collapse; }
        .layout > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 10px 12px 0; }
        .layout > tbody > tr > td + td { padding: 0 0 12px 10px; }
        h2 { font-size: 11px; margin: 0 0 3px; }
        .note { color: #64748b; margin-bottom: 3px; }
        .entries { width: 100%; border-collapse: collapse; }
        .entries td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .term { width: 62px; font-weight: bold; white-space: nowrap; }
        .looking { display: inline-block; transform: scaleX(-1); vertical-align: top; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>

    <table class="layout">
        <tbody>
            @foreach ($rows as $pair)
                <tr>
                    @foreach ($pair as $section)
                        <td>
                            <h2>{{ $section['title'] }}</h2>
                            @if (! empty($section['note']))
                                <div class="note">{{ $section['note'] }}</div>
                            @endif
                            <table class="entries">
                                <tbody>
                                    @foreach ($section['entries'] as $entry)
                                        <tr>
                                            {{-- La K invertida (ponche cantado) no está en la fuente: se dibuja volteando una K --}}
                                            <td class="term">@if (str_starts_with($entry['term'], 'ꓘ'))<span class="looking">K</span>{{ mb_substr($entry['term'], 1) }}@else{{ $entry['term'] }}@endif</td>
                                            <td>{{ $entry['meaning'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
