<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Lectura de archivos de carga masiva (Excel/CSV) y generación de plantillas.
 */
trait HandlesSpreadsheets
{
    /**
     * Valida el archivo "file" del request y devuelve las filas de todas sus hojas como
     * [fila => [campo => valor]], omitiendo filas y hojas vacías. Con varias hojas la fila
     * se identifica con el nombre de la hoja, p. ej. "2 (Baltimore)".
     *
     * @param  array<string, string>  $headers  Encabezado en el archivo => campo
     * @param  array<string, string>  $optional  Encabezados que pueden faltar (el campo queda en null)
     */
    protected function readSpreadsheet(Request $request, array $headers, array $optional = []): array
    {
        $request->validate([
            // "txt" porque los CSV suelen detectarse como text/plain; "extensions" valida el nombre del archivo
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|extensions:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'Selecciona un archivo.',
            'file.mimes' => 'El archivo debe ser de Excel (.xlsx, .xls) o CSV.',
            'file.extensions' => 'El archivo debe ser de Excel (.xlsx, .xls) o CSV.',
            'file.max' => 'El archivo no debe pesar más de 5 MB.',
        ]);

        try {
            $sheets = [];
            foreach (IOFactory::load($request->file('file')->getRealPath())->getAllSheets() as $sheet) {
                // Valores sin formato: las fechas llegan como número serial de Excel
                $rows = $sheet->toArray(null, true, false, false);
                if ($this->hasValues(array_slice($rows, 1))) {
                    $sheets[$sheet->getTitle()] = $rows;
                }
            }
        } catch (\Throwable $e) {
            $this->failImport('No se pudo leer el archivo. Verifica que sea un Excel válido.');
        }

        if (! $sheets) {
            $this->failImport('El archivo no contiene registros.');
        }

        // Mapear cada encabezado esperado a su campo
        $expected = [];
        foreach ($headers + $optional as $header => $field) {
            $expected[$this->normalizeHeader($header)] = $field;
        }

        $result = [];
        foreach ($sheets as $title => $rows) {
            $columns = [];
            foreach (array_shift($rows) as $index => $header) {
                $key = $this->normalizeHeader((string) $header);
                if (isset($expected[$key])) {
                    $columns[$expected[$key]] = $index;
                }
            }

            if (array_diff($headers, array_keys($columns))) {
                $where = count($sheets) > 1 ? " de la hoja \"{$title}\"" : ' del archivo';
                $this->failImport("Faltan encabezados en la primera fila{$where}. Se esperan: " . implode(', ', array_keys($headers + $optional)) . '.');
            }

            foreach ($rows as $i => $row) {
                $data = [];
                foreach ($headers + $optional as $field) {
                    $value = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
                    $data[$field] = is_string($value) ? trim($value) : $value;
                }

                if (! $this->hasValues([$data])) {
                    continue;
                }

                // +1 por el encabezado, +1 porque Excel inicia en 1
                $result[count($sheets) > 1 ? ($i + 2) . " ({$title})" : $i + 2] = $data;
            }
        }

        return $result;
    }

    /**
     * Descarga una plantilla .xlsx con los encabezados en negrita.
     *
     * @param  array<string, int>  $columns  Encabezado => ancho de columna
     * @param  callable(Worksheet): void|null  $customize  Ajustes extra (formatos de columna, etc.)
     */
    protected function downloadTemplate(array $columns, string $filename, string $sheetTitle, ?callable $customize = null)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray(array_keys($columns));

        $column = 'A';
        foreach ($columns as $width) {
            $sheet->getColumnDimension($column++)->setWidth($width);
        }
        $sheet->getStyle('A1:' . chr(ord('A') + count($columns) - 1) . '1')->getFont()->setBold(true);

        if ($customize) {
            $customize($sheet);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // Respuesta final de una importación con su resumen
    protected function importSummary(int $created, int $updated, array $errors)
    {
        return response()->json([
            'message' => "Importación completada: {$created} creados, {$updated} actualizados.",
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function failImport(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 422));
    }

    // Alguna fila trae al menos un valor
    private function hasValues(array $rows): bool
    {
        return collect($rows)->flatten()->contains(fn ($value) => $value !== null && trim((string) $value) !== '');
    }

    // "Abreviación " -> "abreviacion"; sin espacios ni guiones bajos, así "APELLIDO 1" = "APELLIDO1"
    // y "Nombre Corto" = "NOMBRE_CORTO"
    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->ascii()->lower()->replaceMatches('/[\s_]+/', '')->toString();
    }
}
