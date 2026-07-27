<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupplierLedgerExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $supplier;
    protected $transactions;
    protected $openingBalance;
    protected $dateFrom;
    protected $dateTo;
    protected $openingBalanceRow;
    protected $closingBalanceRow;

    public function __construct($supplier, $transactions, float $openingBalance, $dateFrom, $dateTo)
    {
        $this->supplier = $supplier;
        $this->transactions = $transactions;
        $this->openingBalance = $openingBalance;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function array(): array
    {
        $rows = [];
        $currentRow = 2; // Start after header

        // Add opening balance row if applicable
        if ($this->openingBalance != 0 || $this->dateFrom) {
            $rows[] = [
                $this->dateFrom ? date('M d, Y', strtotime($this->dateFrom)) : '-',
                'Opening Balance',
                '',
                '',
                '',
                '',
                number_format($this->openingBalance, 2),
            ];
            $this->openingBalanceRow = $currentRow;
            $currentRow++;
        }

        // Add transactions
        $runningBalance = $this->openingBalance;

        foreach ($this->transactions as $txn) {
            $runningBalance += ($txn['debit'] - $txn['credit']);

            $rows[] = [
                date('M d, Y', strtotime($txn['date'])),
                ucfirst($txn['type']),
                $txn['reference'],
                $txn['description'],
                $txn['debit'] > 0 ? number_format($txn['debit'], 2) : '',
                $txn['credit'] > 0 ? number_format($txn['credit'], 2) : '',
                number_format($runningBalance, 2),
            ];
            $currentRow++;
        }

        // Add closing balance row
        $closingBalance = $this->openingBalance;
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($this->transactions as $txn) {
            $totalDebits += $txn['debit'];
            $totalCredits += $txn['credit'];
        }

        $closingBalance = $this->openingBalance + $totalDebits - $totalCredits;

        $rows[] = [
            '',
            'Closing Balance',
            '',
            '',
            number_format($totalDebits, 2),
            number_format($totalCredits, 2),
            number_format($closingBalance, 2),
        ];
        $this->closingBalanceRow = $currentRow;

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Type',
            'Reference',
            'Description',
            'Debit (Bill)',
            'Credit (Payment)',
            'Balance',
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

        // Opening balance row
        if ($this->openingBalanceRow) {
            $styles[$this->openingBalanceRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ]
            ];
        }

        // Closing balance row
        if ($this->closingBalanceRow) {
            $styles[$this->closingBalanceRow] = [
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
