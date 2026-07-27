<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetailedInventorySheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $inventoryItems;
    protected $summary;

    public function __construct(array $inventoryItems, array $summary)
    {
        $this->inventoryItems = $inventoryItems;
        $this->summary = $summary;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->inventoryItems as $item) {
            $rows[] = [
                $item['product_name'],
                $item['product_sku'],
                $item['category_name'],
                $item['warehouse_name'],
                $item['rack_name'],
                number_format($item['quantity'], 2),
                $item['avg_cost'] > 0 ? number_format($item['avg_cost'], 4) : '-',
                number_format($item['total_value'], 2),
            ];
        }

        // Add totals row
        $rows[] = [
            '',
            '',
            '',
            '',
            '',
            number_format($this->summary['total_quantity'], 2),
            '',
            number_format($this->summary['total_value'], 2),
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Product',
            'SKU',
            'Category',
            'Warehouse',
            'Rack',
            'Quantity',
            'Avg Cost',
            'Total Value',
        ];
    }

    public function title(): string
    {
        return 'Detailed Inventory List';
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->inventoryItems) + 2; // +1 for heading, +1 for totals

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
