<?php

namespace App\Jobs;

use App\Models\SalesChannel;
use App\Services\Messaging\MessageSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Syncs one store's messages on demand (e.g. refresh after a webhook). The regular
 * scheduled sync runs through the messages:sync command instead.
 */
class SyncChannelMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600;

    public function __construct(
        protected int|string $salesChannelId,
        protected ?string $from = null,
        protected bool $includeSystem = false,
    ) {
        $this->onQueue('messages');
    }

    public function handle(MessageSyncService $sync): void
    {
        $channel = SalesChannel::findOrFail($this->salesChannelId);

        $result = $sync->syncChannel(
            $channel,
            $this->from ? Carbon::parse($this->from) : null,
            $this->includeSystem,
        );

        Log::channel('messages')->info('Message sync job finished', [
            'sales_channel_id' => $this->salesChannelId,
            'result' => $result,
        ]);
    }

    public function failed(Exception $exception): void
    {
        Log::channel('messages')->error('Message sync job failed', [
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);
    }
}
