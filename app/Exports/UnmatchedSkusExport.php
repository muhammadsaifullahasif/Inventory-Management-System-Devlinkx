<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnmatchedSkusExport implements FromArray, WithHeadings, WithStyles
{
    protected $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->items as $item) {
            $rows[] = [
                $item->sku,
                $item->title,
                $item->order->order_number ?? '-',
                $item->order->salesChannel->name ?? '-',
                $item->order && $item->order->order_date ? $item->order->order_date->format('M d, Y') : '-',
                $item->quantity,
                number_format($item->unit_price, 2),
                number_format($item->total_price, 2),
                $item->currency ?? 'USD',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'SKU', 'Title', 'Order #', 'Channel', 'Order Date',
            'Qty', 'Unit Price', 'Total', 'Currency',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
