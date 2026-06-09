<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseDetailSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    public function array(): array
    {
        $rows = [];

        // Purchase header info
        $rows[] = ['Purchase Number:', $this->purchase->purchase_number];
        $rows[] = ['Supplier:', $this->purchase->supplier->full_name ?? 'N/A'];
        $rows[] = ['Warehouse:', $this->purchase->warehouse->name ?? 'N/A'];
        $rows[] = ['Date:', $this->purchase->created_at->format('M d, Y')];
        $rows[] = ['Status:', ucfirst($this->purchase->purchase_status)];
        $rows[] = ['Note:', $this->purchase->purchase_note ?? ''];
        $rows[] = []; // Empty row

        // Column headers for items
        $rows[] = [
            'Product',
            'SKU',
            'Barcode',
            'Ordered Qty',
            'Received Qty',
            'Price',
            'Ordered Value',
            'Received Value',
            'Note',
        ];

        // Purchase items
        $totalOrderedValue = 0;
        $totalReceivedValue = 0;

        foreach ($this->purchase->purchase_items as $item) {
            $orderedValue = (float) $item->quantity * (float) $item->price;
            $receivedValue = (float) $item->received_quantity * (float) $item->price;

            $totalOrderedValue += $orderedValue;
            $totalReceivedValue += $receivedValue;

            $rows[] = [
                $item->name,
                $item->sku ?? '',
                $item->barcode ?? '',
                number_format($item->quantity, 2),
                number_format($item->received_quantity, 2),
                number_format($item->price, 2),
                number_format($orderedValue, 2),
                number_format($receivedValue, 2),
                $item->note ?? '',
            ];
        }

        // Totals row
        $rows[] = [
            '',
            '',
            '',
            '',
            '',
            'SUBTOTAL:',
            number_format($totalOrderedValue, 2),
            number_format($totalReceivedValue, 2),
            '',
        ];

        // Additional charges
        if ((float) $this->purchase->duties_customs > 0) {
            $rows[] = [
                '',
                '',
                '',
                '',
                '',
                'Duties & Customs:',
                number_format($this->purchase->duties_customs, 2),
                '',
                '',
            ];
        }

        if ((float) $this->purchase->freight_charges > 0) {
            $rows[] = [
                '',
                '',
                '',
                '',
                '',
                'Freight Charges:',
                number_format($this->purchase->freight_charges, 2),
                '',
                '',
            ];
        }

        // Grand total
        $grandTotal = $totalOrderedValue + (float) $this->purchase->duties_customs + (float) $this->purchase->freight_charges;
        $rows[] = [
            '',
            '',
            '',
            '',
            '',
            'GRAND TOTAL:',
            number_format($grandTotal, 2),
            '',
            '',
        ];

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        // Sheet names can't exceed 31 characters
        $title = $this->purchase->purchase_number;
        if (strlen($title) > 31) {
            $title = substr($title, 0, 31);
        }
        return $title;
    }

    public function styles(Worksheet $sheet): array
    {
        $itemStartRow = 7; // Row where item headers start
        $itemCount = $this->purchase->purchase_items->count();
        $totalsRow = $itemStartRow + $itemCount + 1;

        $styles = [
            // Purchase info section (rows 1-6)
            '1:6' => [
                'font' => ['bold' => true],
            ],
            // Item headers row
            $itemStartRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
            // Totals rows
            $totalsRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ]
            ],
        ];

        return $styles;
    }
}
