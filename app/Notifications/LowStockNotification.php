<?php

namespace App\Notifications;

use App\Models\ProductStock;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ProductStock $productStock) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->productStock->product;
        $warehouse = $this->productStock->warehouse;

        return (new MailMessage)
            ->subject('Low Stock Alert: '.($product->name ?? 'Product'))
            ->line("Stock for \"{$product->name}\" has dropped below its reorder threshold.")
            ->line("Warehouse: {$warehouse?->name}")
            ->line("Current quantity: {$this->productStock->quantity}")
            ->line("Reorder threshold: {$this->productStock->reorder_threshold}")
            ->action('View Product', url('/products/'.$product->id));
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->productStock->product;

        return [
            'type' => 'low_stock',
            'product_id' => $product?->id,
            'product_stock_id' => $this->productStock->id,
            'warehouse_id' => $this->productStock->warehouse_id,
            'quantity' => (string) $this->productStock->quantity,
            'reorder_threshold' => $this->productStock->reorder_threshold,
            'message' => "Low stock: {$product?->name} ({$this->productStock->quantity} left, threshold {$this->productStock->reorder_threshold})",
        ];
    }
}
