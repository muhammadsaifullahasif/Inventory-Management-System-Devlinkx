<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutOfStockItemsExport implements FromArray, WithHeadings, WithStyles
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
            $order = $item->order;

            $rows[] = [
                $item->title,
                $item->sku,
                $order->order_number ?? '-',
                $order->salesChannel->name ?? '-',
                $order && $order->order_date ? $order->order_date->format('Y-m-d H:i') : '-',
                $order ? ucfirst(str_replace('_', ' ', (string) $order->order_status)) : '-',
                (float) $item->quantity,
                $item->last_purchase_number ?? '-',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Product Title', 'SKU', 'Order #', 'Channel', 'Order Date', 'Status',
            'Out of Stock Qty', 'Last Shipment #',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
