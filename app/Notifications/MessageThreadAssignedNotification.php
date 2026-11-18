<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bell notification: a conversation was assigned to you by a teammate.
 */
class MessageThreadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected int $threadId, protected string $buyer, protected string $assignedBy)
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
            'type' => 'message_thread_assigned',
            'thread_id' => $this->threadId,
            'url' => route('messages.index', ['thread' => $this->threadId], false),
            'message' => "{$this->assignedBy} assigned you the conversation with {$this->buyer}.",
        ];
    }
}
