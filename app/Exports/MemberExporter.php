<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExporter
{
    private const HEADERS = [
        'ID', 'CID', 'Name', 'Branch', 'Email', 'Phone', 'Address',
        'Age', 'Birth Date', 'Gender', 'Marital Status', 'Occupation', 'Employment Status',
        'Status', 'Joined Date', 'Account Name', 'Account Number', 'Amount',
        'Payment Date', 'Subscription Date', 'Remarks', 'Note: Date Format',
    ];

    private const AMOUNT_COL_INDEX = 18; // 1-based; column R

    public function export(Collection $records): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $totalCols   = count(self::HEADERS);
        $totalRows   = $records->count() + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

        $sheet->fromArray(self::HEADERS, null, 'A1');

        // Set every column to text format except Amount (col R)
        for ($i = 1; $i <= $totalCols; $i++) {
            if ($i === self::AMOUNT_COL_INDEX) continue;
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$col}1:{$col}{$totalRows}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $row = 2;
        foreach ($records->sortBy('last_name') as $record) {
            $sub     = $record->latestSubscription;
            $rowData = $this->buildRow($record, $sub);

            foreach ($rowData as $colIdx => $value) {
                $col = Coordinate::stringFromColumnIndex($colIdx + 1);

                if ($colIdx + 1 === self::AMOUNT_COL_INDEX) {
                    $sheet->setCellValue("{$col}{$row}", $value);
                    $sheet->getStyle("{$col}{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                } else {
                    $sheet->setCellValueExplicit("{$col}{$row}", $value, DataType::TYPE_STRING);
                }
            }

            $this->applyAgeHighlight($sheet, $row, $lastCol, (int) $record->age);
            $this->applyAmountHighlight($sheet, $row, (float) ($sub?->amount ?? 0));

            $row++;
        }

        $this->appendSumRow($sheet, $row);

        $filename = 'pre-need_export-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function buildRow($record, $sub): array
    {
        return [
            (string) $record->id,
            (string) $record->cid,
            (string) $record->full_name,
            (string) ($record->branch?->branch_name ?? 'N/A'),
            (string) $record->email,
            (string) $record->contact_number,
            (string) $record->full_address,
            (string) $record->age,
            (string) $record->birth_date,
            (string) $record->gender_label,
            (string) $record->marital_status_label,
            (string) $record->occupation,
            (string) $record->employment_status,
            (string) ($record->is_active ? 'Active' : 'Archived'),
            (string) $record->created_at->format('m/d/Y'),
            (string) ($sub?->productAccount?->product_name ?? ''),
            (string) ($sub?->productAccount?->account_number ?? ''),
            $sub?->amount ?? 0,
            '',
            (string) ($sub?->expires_at?->format('m/d/Y') ?? ''),
            'RENEWAL',
            'month/day/Year (12/18/2025)',
        ];
    }

    private function applyAgeHighlight($sheet, int $row, string $lastCol, int $age): void
    {
        if ($age < 65) return;

        [$bg, $fg] = $age >= 70 ? ['FFFF0000', 'FFFFFFFF'] : ['FFFF6600', 'FFFFFFFF'];

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($bg);

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFont()->getColor()->setARGB($fg);
    }

    private function applyAmountHighlight($sheet, int $row, float $amount): void
    {
        if (in_array($amount, [180.0, 360.0], true)) return;

        $sheet->getStyle("R{$row}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');

        $sheet->getStyle("R{$row}")
            ->getFont()->getColor()->setARGB('FF000000');
    }

    private function appendSumRow($sheet, int $row): void
    {
        $sheet->setCellValueExplicit("A{$row}", 'TOTAL', DataType::TYPE_STRING);
        $sheet->setCellValue("R{$row}", "=SUM(R2:R" . ($row - 1) . ")");
        $sheet->getStyle("R{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$row}:R{$row}")->getFont()->setBold(true);
    }
}
