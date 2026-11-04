<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromArray, WithHeadings, WithStyles
{
    protected $orders;

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            // Bundle summary lines are display-only; their components carry the real SKUs.
            $items = $order->items->where('is_bundle_summary', false);

            $rows[] = [
                $order->order_number,
                $order->channel_order_id,
                $order->salesChannel->name ?? '-',
                $order->buyer_name,
                $order->buyer_email,
                $items->map(fn ($i) => $i->sku . ' x ' . (float) $i->quantity)->implode(', '),
                $order->items->where('was_out_of_stock', true)
                    ->map(fn ($i) => $i->sku . ' x ' . (float) $i->quantity)->implode(', '),
                number_format((float) $order->total, 2),
                $order->currency ?? 'USD',
                ucfirst(str_replace('_', ' ', (string) $order->order_status)),
                ucfirst(str_replace('_', ' ', (string) $order->payment_status)),
                ucfirst(str_replace('_', ' ', (string) $order->fulfillment_status)),
                $order->order_date ? $order->order_date->format('Y-m-d H:i') : '-',
                $order->shipment_deadline ? $order->shipment_deadline->format('Y-m-d') : '-',
                $order->shipped_at ? $order->shipped_at->format('Y-m-d') : '-',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Order #', 'Channel Order ID', 'Channel', 'Customer', 'Email', 'Items',
            'Out of Stock Items (at order time)', 'Total', 'Currency', 'Status',
            'Payment', 'Fulfillment', 'Order Date', 'Ship By', 'Shipped Date',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
