<?php

namespace App\Notifications;

use App\Models\MessageThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bell notification: a buyer wrote in a conversation. Goes to the assignee, or to the admins when unassigned.
 */
class NewBuyerMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $threadId,
        protected string $buyer,
        protected string $preview,
        protected ?string $store = null,
    ) {
        $this->onQueue('messages');
    }

    public static function forThread(MessageThread $thread): self
    {
        return new self(
            $thread->id,
            $thread->buyer_username ?: ($thread->buyer_name ?: 'a buyer'),
            (string) $thread->last_message_preview,
            $thread->salesChannel?->name,
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_buyer_message',
            'thread_id' => $this->threadId,
            'url' => route('messages.index', ['thread' => $this->threadId], false),
            'message' => "New message from {$this->buyer}: {$this->preview}",
        ];
    }
}
