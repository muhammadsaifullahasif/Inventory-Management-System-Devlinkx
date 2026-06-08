<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryByProductSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $groupedData;
    protected $summary;
    protected $groupBy;

    public function __construct(array $groupedData, array $summary, string $groupBy)
    {
        $this->groupedData = $groupedData;
        $this->summary = $summary;
        $this->groupBy = $groupBy;
    }

    public function array(): array
    {
        $rows = [];

        // Grouped data section
        foreach ($this->groupedData as $group) {
            $percentage = $this->summary['total_value'] > 0
                ? ($group['total_value'] / $this->summary['total_value']) * 100
                : 0;

            $rows[] = [
                $group['name'],
                $group['item_count'],
                number_format($group['quantity'], 2),
                number_format($group['avg_cost'], 4),
                number_format($group['total_value'], 2),
                number_format($percentage, 1) . '%',
            ];
        }

        // Add totals row
        $totalItems = array_sum(array_column($this->groupedData, 'item_count'));
        $rows[] = [
            'TOTAL',
            $totalItems,
            number_format($this->summary['total_quantity'], 2),
            '',
            number_format($this->summary['total_value'], 2),
            '100.0%',
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [
            ucfirst($this->groupBy),
            'Items',
            'Quantity',
            'Avg Cost',
            'Total Value',
            '% of Total',
        ];
    }

    public function title(): string
    {
        return 'Inventory by ' . ucfirst($this->groupBy);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->groupedData) + 2; // +1 for heading, +1 for totals row

        return [
            // Header row
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
            // Totals row
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ]
            ],
        ];
    }
}
