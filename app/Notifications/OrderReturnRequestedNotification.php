<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrderReturn $orderReturn) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderReturn->order;

        return (new MailMessage)
            ->subject("Return Requested: Order #{$order?->order_number}")
            ->line("A return has been requested for order #{$order?->order_number}.")
            ->line("Source: {$this->orderReturn->source}")
            ->line("Reason: {$this->orderReturn->reason}")
            ->action('View Order', url('/orders/'.$this->orderReturn->order_id));
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->orderReturn->order;

        return [
            'type' => 'order_return_requested',
            'order_return_id' => $this->orderReturn->id,
            'order_id' => $this->orderReturn->order_id,
            'order_number' => $order?->order_number,
            'reason' => $this->orderReturn->reason,
            'message' => "Return requested for order #{$order?->order_number}: {$this->orderReturn->reason}",
        ];
    }
}
