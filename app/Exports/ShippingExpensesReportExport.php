<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShippingExpensesReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $summary;
    protected $source;
    protected $groupBy;

    public function __construct(array $data, array $summary, string $source, string $groupBy)
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->source = $source;
        $this->groupBy = $groupBy;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['Shipping Expenses Summary - ' . ($this->source === 'ebay' ? 'eBay Labels' : 'System Labels')];
        $rows[] = ['Labels Generated', number_format($this->summary['label_count'], 0)];
        $rows[] = ['Total Cost', number_format($this->summary['total_cost'], 2)];
        $rows[] = ['Avg Cost per Label', number_format($this->summary['avg_cost'], 2)];
        $rows[] = ['Cost % of Order Revenue', number_format($this->summary['cost_pct_of_revenue'], 2) . '%'];
        $rows[] = []; // Empty row

        foreach ($this->data as $item) {
            $rows[] = [
                $item['name'],
                number_format($item['label_count'], 0),
                number_format($item['total_cost'], 2),
                number_format($item['avg_cost'], 2),
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        $groupLabel = match ($this->groupBy) {
            'carrier' => 'Carrier',
            'date' => 'Date',
            default => 'Sales Channel',
        };

        return [$groupLabel, 'Labels', 'Total Cost', 'Avg Cost'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            7 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Shipping Expenses';
    }
}
