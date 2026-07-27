<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasesBySupplierSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $groupedData;
    protected $summary;

    public function __construct($groupedData, array $summary)
    {
        $this->groupedData = $groupedData;
        $this->summary = $summary;
    }

    public function array(): array
    {
        $rows = [];

        // Grouped data by supplier
        foreach ($this->groupedData as $group) {
            $rows[] = [
                $group['name'],
                $group['purchase_count'],
                number_format($group['ordered_qty'], 2),
                number_format($group['received_qty'], 2),
                number_format($group['ordered_value'], 2),
                number_format($group['received_value'], 2),
                number_format($group['ordered_value'] - $group['received_value'], 2),
            ];
        }

        // Add totals row
        $rows[] = [
            'TOTAL',
            $this->summary['total_purchases'],
            number_format($this->summary['total_ordered_qty'], 2),
            number_format($this->summary['total_received_qty'], 2),
            number_format($this->summary['total_ordered_value'], 2),
            number_format($this->summary['total_received_value'], 2),
            number_format($this->summary['pending_value'], 2),
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Supplier',
            'Purchases',
            'Ordered Qty',
            'Received Qty',
            'Ordered Value',
            'Received Value',
            'Pending Value',
        ];
    }

    public function title(): string
    {
        return 'Purchases by Supplier';
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
