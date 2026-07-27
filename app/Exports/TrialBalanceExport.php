<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrialBalanceExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $totalDebit;
    protected $totalCredit;

    public function __construct(array $data, float $totalDebit, float $totalCredit)
    {
        $this->data = $data;
        $this->totalDebit = $totalDebit;
        $this->totalCredit = $totalCredit;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $account) {
            $rows[] = [
                $account['code'],
                $account['name'],
                $account['group'],
                ucfirst($account['nature']),
                $account['debit'] > 0 ? number_format($account['debit'], 2) : '-',
                $account['credit'] > 0 ? number_format($account['credit'], 2) : '-',
            ];
        }

        // Add totals row
        $rows[] = [
            '',
            '',
            '',
            'TOTAL',
            number_format($this->totalDebit, 2),
            number_format($this->totalCredit, 2),
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Account Name',
            'Group',
            'Nature',
            'Debit',
            'Credit',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data) + 2; // +1 for heading, +1 for totals

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
