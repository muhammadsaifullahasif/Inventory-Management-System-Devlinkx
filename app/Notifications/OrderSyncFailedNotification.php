<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderSyncFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $jobName,
        protected string $errorMessage,
        protected ?int $salesChannelId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order Sync Failed: {$this->jobName}")
            ->error()
            ->line("The job \"{$this->jobName}\" failed to complete after all retries.")
            ->when($this->salesChannelId, fn ($mail) => $mail->line("Sales Channel ID: {$this->salesChannelId}"))
            ->line("Error: {$this->errorMessage}")
            ->line('Check the application logs for the full stack trace.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_sync_failed',
            'job' => $this->jobName,
            'sales_channel_id' => $this->salesChannelId,
            'error' => $this->errorMessage,
            'message' => "{$this->jobName} failed: {$this->errorMessage}",
        ];
    }
}
