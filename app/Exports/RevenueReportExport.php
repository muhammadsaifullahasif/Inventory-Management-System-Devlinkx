<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $summary;
    protected $groupBy;

    public function __construct(array $data, array $summary, string $groupBy)
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->groupBy = $groupBy;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['Revenue Report Summary'];
        $rows[] = ['Gross Revenue', number_format($this->summary['gross_revenue'], 2)];
        $rows[] = ['Total Refunds', number_format($this->summary['total_refunds'], 2)];
        $rows[] = ['Net Revenue', number_format($this->summary['net_revenue'], 2)];
        $rows[] = ['Refund Rate', number_format($this->summary['refund_rate'], 2) . '%'];
        $rows[] = ['Items Sold', number_format($this->summary['total_items_sold'], 0)];
        $rows[] = ['Avg Order Value (Net)', number_format($this->summary['average_order_value'], 2)];
        $rows[] = []; // Empty row

        foreach ($this->data as $item) {
            if ($this->groupBy === 'product') {
                $row = [
                    $item['name'],
                    $item['sku'] ?? '',
                    number_format($item['quantity_sold'], 0),
                    number_format($item['total_revenue'], 2),
                ];
            } elseif ($this->groupBy === 'category') {
                $row = [
                    $item['name'],
                    number_format($item['quantity_sold'], 0),
                    number_format($item['total_revenue'], 2),
                ];
            } elseif ($this->groupBy === 'date') {
                $row = [
                    $item['formatted_date'],
                    number_format($item['order_count'], 0),
                    number_format($item['items_sold'], 0),
                    number_format($item['gross_revenue'], 2),
                    number_format($item['total_refunds'], 2),
                    number_format($item['net_revenue'], 2),
                ];
            } else { // channel
                $row = [
                    $item['name'],
                    number_format($item['order_count'], 0),
                    number_format($item['items_sold'], 0),
                    number_format($item['gross_revenue'], 2),
                    number_format($item['total_refunds'], 2),
                    number_format($item['net_revenue'], 2),
                ];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        if ($this->groupBy === 'product') {
            return ['Product', 'SKU', 'Qty Sold', 'Revenue'];
        } elseif ($this->groupBy === 'category') {
            return ['Category', 'Qty Sold', 'Revenue'];
        } elseif ($this->groupBy === 'date') {
            return ['Date', 'Orders', 'Items Sold', 'Gross Revenue', 'Refunds', 'Net Revenue'];
        }

        return ['Sales Channel', 'Orders', 'Items Sold', 'Gross Revenue', 'Refunds', 'Net Revenue'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            9 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Revenue Report';
    }
}
