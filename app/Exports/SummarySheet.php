<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummarySheet implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    protected $summary;

    public function __construct(array $summary)
    {
        $this->summary = $summary;
    }

    public function array(): array
    {
        return [
            ['Inventory Valuation Report - Summary'],
            [],
            ['Metric', 'Value'],
            ['Total Products', $this->summary['total_products']],
            ['Total Quantity', number_format($this->summary['total_quantity'], 2)],
            ['Total Inventory Value', number_format($this->summary['total_value'], 2)],
            ['Average Cost per Unit', number_format($this->summary['avg_cost_per_unit'], 4)],
        ];
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Title
            1 => [
                'font' => ['bold' => true, 'size' => 16],
            ],
            // Header row
            3 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
            // Metric column
            'A4:A7' => [
                'font' => ['bold' => true],
            ],
        ];
    }
}
