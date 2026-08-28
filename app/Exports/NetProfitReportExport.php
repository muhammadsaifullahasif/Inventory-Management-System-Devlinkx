<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NetProfitReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
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

        $rows[] = ['Net Profit Summary'];
        $rows[] = ['Gross Revenue', number_format($this->summary['gross_revenue'], 2)];
        $rows[] = ['Refunds', number_format($this->summary['total_refunds'], 2)];
        $rows[] = ['Net Revenue', number_format($this->summary['net_revenue'], 2)];
        $rows[] = ['COGS', number_format($this->summary['cogs'], 2)];
        $rows[] = ['Gross Profit', number_format($this->summary['gross_profit'], 2)];
        $rows[] = ['Gross Margin %', number_format($this->summary['gross_margin'], 2) . '%'];
        $rows[] = ['eBay Fees', number_format($this->summary['ebay_fees'], 2)];
        $rows[] = ['Shipping Costs', number_format($this->summary['shipping_costs'], 2)];
        $rows[] = ['Operating Expenses', number_format($this->summary['operating_expenses'], 2)];
        $rows[] = ['Net Profit', number_format($this->summary['net_profit'], 2)];
        $rows[] = ['Net Margin %', number_format($this->summary['net_margin'], 2) . '%'];
        $rows[] = []; // Empty row

        foreach ($this->data as $item) {
            $rows[] = [
                $item['name'],
                number_format($item['net_revenue'], 2),
                number_format($item['cogs'], 2),
                number_format($item['ebay_fees'], 2),
                number_format($item['shipping_costs'], 2),
                number_format($item['contribution_profit'], 2),
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        $groupLabel = $this->groupBy === 'date' ? 'Date' : 'Sales Channel';

        return [$groupLabel, 'Net Revenue', 'COGS', 'eBay Fees', 'Shipping Costs', 'Contribution Profit'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            13 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Net Profit';
    }
}
