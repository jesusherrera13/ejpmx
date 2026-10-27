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
     * Valida el archivo "file" del request y devuelve sus filas como
     * [numeroDeFila => [campo => valor]], omitiendo filas vacías.
     *
     * @param  array<string, string>  $headers  Encabezado en el archivo => campo
     */
    protected function readSpreadsheet(Request $request, array $headers): array
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
            $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
            // Valores sin formato: las fechas llegan como número serial de Excel
            $rows = $sheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            $this->failImport('No se pudo leer el archivo. Verifica que sea un Excel válido.');
        }

        if (count($rows) < 2) {
            $this->failImport('El archivo no contiene registros.');
        }

        // Mapear cada encabezado esperado a su índice de columna
        $expected = [];
        foreach ($headers as $header => $field) {
            $expected[$this->normalizeHeader($header)] = $field;
        }

        $columns = [];
        foreach (array_shift($rows) as $index => $header) {
            $key = $this->normalizeHeader((string) $header);
            if (isset($expected[$key])) {
                $columns[$expected[$key]] = $index;
            }
        }

        if (array_diff($expected, array_keys($columns))) {
            $this->failImport('Faltan encabezados en el archivo. Se esperan: ' . implode(', ', array_keys($headers)) . '.');
        }

        $result = [];
        foreach ($rows as $i => $row) {
            $data = [];
            foreach ($columns as $field => $index) {
                $value = $row[$index] ?? null;
                $data[$field] = is_string($value) ? trim($value) : $value;
            }

            if (collect($data)->every(fn ($value) => $value === null || $value === '')) {
                continue;
            }

            // +1 por el encabezado, +1 porque Excel inicia en 1
            $result[$i + 2] = $data;
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

    // "Abreviación " -> "abreviacion"
    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->ascii()->lower()->squish()->toString();
    }
}
