<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $reportData;
    protected $grandTotal;
    protected $groupRows = [];
    protected $totalRow;

    public function __construct($reportData, float $grandTotal)
    {
        $this->reportData = $reportData;
        $this->grandTotal = $grandTotal;
    }

    public function array(): array
    {
        $rows = [];
        $currentRow = 2; // Start after header

        foreach ($this->reportData as $group) {
            // Add group header row
            $rows[] = [
                $group['code'],
                $group['name'],
                '',
                number_format($group['total'], 2),
                '',
            ];
            $this->groupRows[] = $currentRow;
            $currentRow++;

            // Add items
            foreach ($group['items'] as $item) {
                $percentage = $this->grandTotal > 0 ? ($item['total_amount'] / $this->grandTotal) * 100 : 0;
                $rows[] = [
                    $item['code'],
                    '  ' . $item['name'], // Indent item name
                    $item['bill_count'],
                    number_format($item['total_amount'], 2),
                    number_format($percentage, 2) . '%',
                ];
                $currentRow++;
            }
        }

        // Add grand total row
        $rows[] = [
            '',
            'GRAND TOTAL',
            '',
            number_format($this->grandTotal, 2),
            '100.00%',
        ];
        $this->totalRow = $currentRow;

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Account Name',
            'Bills',
            'Amount',
            '% of Total',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            // Header row
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
        ];

        // Group header rows
        foreach ($this->groupRows as $rowNumber) {
            $styles[$rowNumber] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F3F5']
                ]
            ];
        }

        // Grand total row
        if ($this->totalRow) {
            $styles[$this->totalRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ];
        }

        return $styles;
    }
}
