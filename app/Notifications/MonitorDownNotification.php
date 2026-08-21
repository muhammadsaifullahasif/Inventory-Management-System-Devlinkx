<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorDownNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Monitor $monitor) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Uptime Alert: {$this->monitor->name} is DOWN")
            ->error()
            ->line("The monitor \"{$this->monitor->name}\" ({$this->monitor->url}) is reporting as down.")
            ->line("Reason: {$this->monitor->uptime_check_failure_reason}")
            ->action('View Uptime Monitor', route('uptime-monitor.show', $this->monitor));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monitor_down',
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'url' => $this->monitor->url,
            'reason' => $this->monitor->uptime_check_failure_reason,
            'message' => "Monitor \"{$this->monitor->name}\" is down: {$this->monitor->uptime_check_failure_reason}",
        ];
    }
}
