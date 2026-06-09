<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByChannelSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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

        // Grouped data by channel
        foreach ($this->groupedData as $channel) {
            $rows[] = [
                $channel['name'],
                $channel['order_count'],
                $channel['paid_count'],
                number_format($channel['items_sold'], 0),
                number_format($channel['total_revenue'], 2),
                number_format($channel['total_shipping'], 2),
                number_format($channel['total_tax'], 2),
                $channel['paid_count'] > 0 ? number_format($channel['total_revenue'] / $channel['paid_count'], 2) : '0.00',
            ];
        }

        // Add totals row
        $rows[] = [
            'TOTAL',
            $this->summary['total_orders'],
            $this->summary['paid_count'],
            number_format($this->summary['total_items_sold'], 0),
            number_format($this->summary['total_revenue'], 2),
            number_format($this->summary['total_shipping'], 2),
            number_format($this->summary['total_tax'], 2),
            number_format($this->summary['average_order_value'], 2),
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Channel',
            'Total Orders',
            'Paid Orders',
            'Items Sold',
            'Revenue',
            'Shipping',
            'Tax',
            'Avg Order Value',
        ];
    }

    public function title(): string
    {
        return 'Sales by Channel';
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
