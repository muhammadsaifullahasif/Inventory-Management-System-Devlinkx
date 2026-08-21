<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Monitor $monitor, protected ?string $downtime = null) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Uptime Alert: {$this->monitor->name} is back UP")
            ->success()
            ->line("The monitor \"{$this->monitor->name}\" ({$this->monitor->url}) is back up.");

        if ($this->downtime) {
            $message->line("It was down for approximately {$this->downtime}.");
        }

        return $message->action('View Uptime Monitor', route('uptime-monitor.show', $this->monitor));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monitor_up',
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'url' => $this->monitor->url,
            'downtime' => $this->downtime,
            'message' => "Monitor \"{$this->monitor->name}\" is back up" . ($this->downtime ? " after {$this->downtime}" : '') . '.',
        ];
    }
}
