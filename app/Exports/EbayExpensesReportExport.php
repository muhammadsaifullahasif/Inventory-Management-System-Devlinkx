<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EbayExpensesReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
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

        $rows[] = ['eBay Expenses Summary'];
        $rows[] = ['Transactions', number_format($this->summary['transaction_count'], 0)];
        $rows[] = ['Unmatched (no local order)', number_format($this->summary['unmatched_count'], 0)];
        $rows[] = ['Final Value / Transaction Fees', number_format($this->summary['transaction_fee'], 2)];
        $rows[] = ['Shipping Label Cost', number_format($this->summary['shipping_label'], 2)];
        $rows[] = ['Ad Fees (Promoted Listings)', number_format($this->summary['ad_fee'], 2)];
        $rows[] = ['Other Fees', number_format($this->summary['other_fees'], 2)];
        $rows[] = ['Total Expenses', number_format($this->summary['total_expenses'], 2)];
        $rows[] = ['Refunds Issued (informational)', number_format($this->summary['refund'], 2)];
        $rows[] = []; // Empty row

        foreach ($this->data as $item) {
            $rows[] = [
                $item['name'],
                number_format($item['transaction_count'], 0),
                number_format($item['amount'], 2),
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        $groupLabel = match ($this->groupBy) {
            'date' => 'Date',
            'channel' => 'Sales Channel',
            default => 'Fee Category',
        };

        return [$groupLabel, 'Transactions', 'Amount'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            11 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'eBay Expenses';
    }
}
