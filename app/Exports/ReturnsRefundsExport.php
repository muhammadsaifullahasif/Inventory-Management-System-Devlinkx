<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReturnsRefundsExport implements FromArray, WithHeadings, WithStyles
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
            $type = [];
            if ($order->return_status) {
                $type[] = 'Return: ' . str_replace('_', ' ', $order->return_status);
            }
            if ($order->order_status === 'cancelled' || $order->cancel_status) {
                $type[] = 'Cancelled';
            }
            if ($order->refund_status) {
                $type[] = 'Refund: ' . str_replace('_', ' ', $order->refund_status);
            }

            $rows[] = [
                $order->order_number,
                $order->ebay_order_id ?? '-',
                $order->salesChannel->name ?? 'Local',
                $order->buyer_name ?? 'N/A',
                $order->buyer_email ?? '-',
                implode(', ', $type) ?: '-',
                $order->refund_initiated_at ? $order->refund_initiated_at->format('M d, Y H:i') : '-',
                $order->total_refunded > 0 ? number_format($order->total_refunded, 2) : '-',
                number_format($order->total, 2),
                $order->currency ?? 'USD',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Order #', 'eBay Order ID', 'Channel', 'Customer', 'Email',
            'Type/Status', 'Refund Initiated At', 'Refunded', 'Order Total', 'Currency',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
