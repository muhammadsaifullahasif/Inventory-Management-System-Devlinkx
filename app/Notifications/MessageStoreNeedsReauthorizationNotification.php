<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bell notification for admins: a store lost (or never had) the eBay messaging permission, so its
 * messages are not syncing until the store is re-authorized.
 */
class MessageStoreNeedsReauthorizationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected int $channelId, protected string $store)
    {
        $this->onQueue('messages');
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message_store_needs_reauthorization',
            'sales_channel_id' => $this->channelId,
            'url' => route('sales-channels.edit', $this->channelId, false),
            'message' => "Messages from {$this->store} have stopped syncing: the store must be re-authorized with eBay (messaging permission).",
        ];
    }
}
