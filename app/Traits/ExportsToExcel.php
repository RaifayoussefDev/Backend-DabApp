<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsToExcel
{
    /**
     * Build a styled Excel (XLSX) StreamedResponse.
     *
     * @param  string   $filename  Without extension — e.g. "trainers-2026-06-13"
     * @param  array    $headers   ['ID', 'Name', ...]
     * @param  iterable $rows      Each element is an array of cell values
     */
    protected function excelResponse(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export');

        // Header row — bold, colored background
        $sheet->fromArray($headers, null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows — write cell-by-cell to preserve types (phone numbers, IBANs, etc.)
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach (array_values((array) $row) as $value) {
                $cell = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                // Force string type for: phone numbers (+...), IBAN, long numeric strings, nulls
                if (
                    is_string($value) &&
                    ($value === '' || str_starts_with($value, '+') || preg_match('/^\d{8,}$/', $value))
                ) {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, $value ?? '');
                }
                $colIndex++;
            }
            // Zebra striping
            if ($rowIndex % 2 === 0) {
                $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F5F7FA');
            }
            $rowIndex++;
        }

        // Auto-width columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
