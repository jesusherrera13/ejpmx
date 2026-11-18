<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Illuminate\Http\Request;

/**
 * Glosario del scorecard en PDF. El contenido lo envía el modal del box score (es la misma
 * definición que muestra), así hay una sola fuente para las abreviaciones.
 */
class GlossaryPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:100',
            'sections' => 'required|array|min:1|max:30',
            'sections.*.title' => 'required|string|max:100',
            'sections.*.note' => 'nullable|string|max:300',
            'sections.*.entries' => 'required|array|min:1|max:60',
            'sections.*.entries.*.term' => 'required|string|max:40',
            'sections.*.entries.*.meaning' => 'required|string|max:300',
        ]);

        // Dos columnas: las secciones se reparten en orden, una a la izquierda y otra a la derecha
        $sections = collect($validated['sections']);
        $rows = $sections->chunk(2)->map(fn ($pair) => $pair->values()->all())->values()->all();

        $html = view('pdf.glossary', [
            'title' => $validated['title'] ?? 'Glosario del scorecard',
            'rows' => $rows,
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="glosario_scorecard.pdf"',
        ]);
    }
}
