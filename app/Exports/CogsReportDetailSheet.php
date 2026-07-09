<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CogsReportDetailSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected $orderItems;

    public function __construct($orderItems)
    {
        $this->orderItems = $orderItems;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->orderItems as $item) {
            $isRefunded = in_array($item->order_status, ['cancelled', 'refunded']) || $item->payment_status === 'refunded';
            $itemCogs = $isRefunded ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            $itemRevenue = $isRefunded ? 0 : (float) $item->total_price;

            $rows[] = [
                $item->order->order_date ? $item->order->order_date->format('M d, Y') : '-',
                $item->order->order_number,
                $item->order->ebay_order_id ?? '-',
                $item->order->salesChannel->name ?? '-',
                $item->product->name ?? $item->title,
                $item->sku ?? '-',
                $item->quantity,
                number_format($item->cost_at_sale ?? 0, 2),
                number_format($itemCogs, 2),
                number_format($itemRevenue, 2),
                number_format($itemRevenue - $itemCogs, 2),
                $isRefunded ? 'Refunded' : 'Completed',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Order #', 'eBay Order ID', 'Channel', 'Product', 'SKU', 'Qty', 'Cost/Unit', 'Total COGS', 'Revenue', 'Profit', 'Status'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Detailed Items';
    }
}
