<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class ShippingTokenExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $carrierName,
        protected string $carrierType,
        protected string $errorMessage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Shipping Token Refresh Failed: {$this->carrierName}")
            ->error()
            ->line("Failed to refresh the API token for shipping carrier \"{$this->carrierName}\" ({$this->carrierType}).")
            ->line("Error: {$this->errorMessage}")
            ->line('Shipment tracking/label generation for this carrier may fail until this is resolved.')
            ->action('View Shipping Carriers', url('/shipping'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shipping_token_expired',
            'carrier_name' => $this->carrierName,
            'carrier_type' => $this->carrierType,
            'error' => $this->errorMessage,
            'message' => "Shipping token refresh failed for {$this->carrierName} ({$this->carrierType}): {$this->errorMessage}",
        ];
    }
}
