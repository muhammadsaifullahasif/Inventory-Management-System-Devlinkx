<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BankSummaryExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $accountSummaries;
    protected $totalOpening;
    protected $totalInflow;
    protected $totalOutflow;
    protected $totalClosing;
    protected $totalRow;

    public function __construct($accountSummaries, $totalOpening, $totalInflow, $totalOutflow, $totalClosing)
    {
        $this->accountSummaries = $accountSummaries;
        $this->totalOpening = $totalOpening;
        $this->totalInflow = $totalInflow;
        $this->totalOutflow = $totalOutflow;
        $this->totalClosing = $totalClosing;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->accountSummaries as $account) {
            $rows[] = [
                $account['code'],
                $account['name'],
                $account['bank_name'] ?? '-',
                $account['account_number'] ?? '-',
                $account['transaction_count'],
                number_format($account['opening_balance'], 2),
                number_format($account['inflow'], 2),
                number_format($account['outflow'], 2),
                number_format($account['closing_balance'], 2),
            ];
        }

        // Add totals row
        $rows[] = [
            '',
            'TOTAL',
            '',
            '',
            '',
            number_format($this->totalOpening, 2),
            number_format($this->totalInflow, 2),
            number_format($this->totalOutflow, 2),
            number_format($this->totalClosing, 2),
        ];

        $this->totalRow = count($rows) + 1; // +1 for header

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Account Name',
            'Bank',
            'Account Number',
            'Transactions',
            'Opening Balance',
            'Inflow (Dr)',
            'Outflow (Cr)',
            'Closing Balance',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            // Header row
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
        ];

        // Total row
        if ($this->totalRow) {
            $styles[$this->totalRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ];
        }

        return $styles;
    }
}
