<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesChannelDetailSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $channel;

    public function __construct($channel)
    {
        $this->channel = $channel;
    }

    public function array(): array
    {
        $rows = [];

        // Channel summary info
        $rows[] = ['Channel:', $this->channel['name']];
        $rows[] = ['Total Orders:', $this->channel['order_count']];
        $rows[] = ['Paid Orders:', $this->channel['paid_count']];
        $rows[] = ['Items Sold:', number_format($this->channel['items_sold'], 0)];
        $rows[] = ['Total Revenue:', number_format($this->channel['total_revenue'], 2)];
        $rows[] = []; // Empty row

        // Column headers for orders
        $rows[] = [
            'Order Date',
            'Order Number',
            'eBay Order ID',
            'Customer',
            'Items',
            'Subtotal',
            'Shipping',
            'Tax',
            'Discount',
            'Total',
            'Payment Status',
            'Order Status',
            'Order Link',
        ];

        // Orders
        $totalSubtotal = 0;
        $totalShipping = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $totalAmount = 0;

        foreach ($this->channel['orders'] as $order) {
            $customerName = $order->buyer_name ?? 'N/A';
            $itemCount = $order->items->count();

            $subtotal = (float) $order->subtotal;
            $shipping = (float) $order->shipping_cost;
            $tax = (float) $order->tax;
            $discount = (float) $order->discount;
            $total = (float) $order->total;

            if ($order->payment_status === 'paid') {
                $totalSubtotal += $subtotal;
                $totalShipping += $shipping;
                $totalTax += $tax;
                $totalDiscount += $discount;
                $totalAmount += $total;
            }

            $rows[] = [
                $order->order_date->format('M d, Y'),
                $order->order_number,
                $order->ebay_order_id ?? '-',
                $customerName,
                $itemCount,
                number_format($subtotal, 2),
                number_format($shipping, 2),
                number_format($tax, 2),
                number_format($discount, 2),
                number_format($total, 2),
                ucfirst($order->payment_status),
                ucfirst($order->order_status),
                route('orders.show', $order->id),
            ];
        }

        // Totals row (only for paid orders)
        $rows[] = [
            '',
            '',
            '',
            'PAID TOTALS:',
            '',
            number_format($totalSubtotal, 2),
            number_format($totalShipping, 2),
            number_format($totalTax, 2),
            number_format($totalDiscount, 2),
            number_format($totalAmount, 2),
            '',
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
        $title = $this->channel['name'];
        if (strlen($title) > 31) {
            $title = substr($title, 0, 31);
        }
        return $title;
    }

    public function styles(Worksheet $sheet): array
    {
        $orderStartRow = 6; // Row where order headers start
        $orderCount = count($this->channel['orders']);
        $totalsRow = $orderStartRow + $orderCount + 1;

        $styles = [
            // Channel info section (rows 1-5)
            '1:5' => [
                'font' => ['bold' => true],
            ],
            // Order headers row
            $orderStartRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
            // Totals row
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
