<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CogsReportSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle
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

        // Add summary section
        $rows[] = ['COGS Report Summary'];
        $rows[] = ['Total Items Sold', number_format($this->summary['total_items_sold'], 0)];
        $rows[] = ['Total Revenue', number_format($this->summary['total_revenue'], 2)];
        $rows[] = ['Total COGS', number_format($this->summary['total_cogs'], 2)];
        $rows[] = ['Gross Profit', number_format($this->summary['gross_profit'], 2)];
        $rows[] = ['Gross Margin %', number_format($this->summary['gross_margin'], 2) . '%'];
        $rows[] = []; // Empty row

        // Add data rows based on grouping
        foreach ($this->data as $item) {
            $row = [];

            if ($this->groupBy === 'product') {
                $row = [
                    $item['name'],
                    $item['sku'] ?? '',
                    number_format($item['quantity_sold'], 0),
                    number_format($item['avg_cost'], 2),
                    number_format($item['avg_price'], 2),
                    number_format($item['total_cogs'], 2),
                    number_format($item['total_revenue'], 2),
                    number_format($item['gross_profit'], 2),
                    number_format($item['gross_margin'], 2) . '%',
                ];
            } elseif ($this->groupBy === 'channel') {
                $row = [
                    $item['name'],
                    number_format($item['items_sold'], 0),
                    number_format($item['total_cogs'], 2),
                    number_format($item['total_revenue'], 2),
                    number_format($item['gross_profit'], 2),
                    number_format($item['gross_margin'], 2) . '%',
                ];
            } elseif ($this->groupBy === 'date') {
                $row = [
                    $item['formatted_date'],
                    number_format($item['items_sold'], 0),
                    number_format($item['total_cogs'], 2),
                    number_format($item['total_revenue'], 2),
                    number_format($item['gross_profit'], 2),
                    number_format($item['gross_margin'], 2) . '%',
                ];
            } elseif ($this->groupBy === 'order') {
                $row = [
                    $item['order_number'],
                    $item['ebay_order_id'] ?? '-',
                    $item['formatted_date'],
                    $item['channel'],
                    number_format($item['items_count'], 0),
                    number_format($item['total_cogs'], 2),
                    number_format($item['total_revenue'], 2),
                    number_format($item['gross_profit'], 2),
                    number_format($item['gross_margin'], 2) . '%',
                    ($item['is_refunded'] ?? false) ? 'Refunded' : 'Completed',
                ];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        if ($this->groupBy === 'product') {
            return ['Product', 'SKU', 'Qty Sold', 'Avg Cost', 'Avg Price', 'Total COGS', 'Total Revenue', 'Gross Profit', 'Margin %'];
        } elseif ($this->groupBy === 'channel') {
            return ['Sales Channel', 'Items Sold', 'Total COGS', 'Total Revenue', 'Gross Profit', 'Margin %'];
        } elseif ($this->groupBy === 'date') {
            return ['Date', 'Items Sold', 'Total COGS', 'Total Revenue', 'Gross Profit', 'Margin %'];
        } else { // order
            return ['Order #', 'eBay Order ID', 'Date', 'Channel', 'Items', 'Total COGS', 'Total Revenue', 'Gross Profit', 'Margin %', 'Status'];
        }
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            8 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'COGS Summary';
    }
}
