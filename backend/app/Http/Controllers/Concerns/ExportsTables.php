<?php

namespace App\Http\Controllers\Concerns;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Descarga de una tabla (posiciones, estadísticas...) en Excel o PDF con título y subtítulo.
 *
 * Columnas: [['title' => 'PCT', 'width' => 8, 'align' => 'right'], ...]; los renglones son
 * arreglos con un valor por columna, en el mismo orden.
 */
trait ExportsTables
{
    protected function downloadTableXlsx(string $title, string $subtitle, string $sheetTitle, array $columns, array $rows, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('64748B');

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $lastRow = count($rows) + 4;

        $sheet->fromArray(array_column($columns, 'title'), null, 'A4');
        $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F7');
        // Los números se escriben como número; el texto tal cual, para que Excel no convierta
        // porcentajes ".500" en 0.5 ni diferencias "+10" en 10
        foreach (array_values($rows) as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 5);
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, $value ?? '-', DataType::TYPE_STRING);
                }
            }
        }

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth($column['width'] ?? 10);
            if (($column['align'] ?? 'left') !== 'left') {
                $sheet->getStyle("{$letter}4:{$letter}{$lastRow}")->getAlignment()->setHorizontal(
                    $column['align'] === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_RIGHT
                );
            }
        }
        $sheet->freezePane('A5');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function downloadTablePdf(string $title, string $subtitle, array $columns, array $rows, string $filename)
    {
        $html = view('pdf.table', compact('title', 'subtitle', 'columns', 'rows'))->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        // Horizontal: las tablas de posiciones y estadísticas son anchas
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
