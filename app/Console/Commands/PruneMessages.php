<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageSetting;
use App\Models\MessageThread;
use App\Services\Messaging\MessageAttachments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the messages tables from growing forever.
 *
 *   1. Conversations: RESOLVED conversations with no activity for the retention period are deleted
 *      together with their messages and notes. The period comes from Settings > Messages; blank = keep forever.
 *      Open conversations (pending / awaiting buyer) are never deleted.
 *   2. Raw platform payloads stored next to old messages are cleared. They are only a debugging aid; the
 *      readable conversation is untouched.
 */
class PruneMessages extends Command
{
    protected $signature = 'messages:prune
                            {--days= : Delete resolved conversations idle for this many days (overrides the setting; minimum 30)}
                            {--raw-days=60 : Clear stored raw platform payloads older than this many days (0 = leave them)}
                            {--dry-run : Only report what would be removed}';

    protected $description = 'Apply the Messages retention policy (old resolved conversations and raw platform payloads)';

    private const MIN_DAYS = 30;
    private const CHUNK = 200;

    public function handle(MessageAttachments $attachments): int
    {
        $dry = (bool) $this->option('dry-run');
        $days = $this->option('days') !== null ? (int) $this->option('days') : MessageSetting::current()->retention_days;

        if ($days !== null && $days < self::MIN_DAYS) {
            $this->error('The retention period must be at least ' . self::MIN_DAYS . ' days.');
            return 1;
        }

        $threads = 0;
        $messages = 0;

        if ($days === null) {
            $this->line('Conversation retention is off (blank in Settings > Messages): no conversations deleted.');
        } else {
            $cutoff = now()->subDays($days);

            $ids = fn () => MessageThread::query()
                ->where('status', MessageThread::STATUS_RESOLVED)
                ->whereRaw('COALESCE(last_message_at, external_updated_at, created_at) < ?', [$cutoff->toDateTimeString()]);

            $threads = $ids()->count();
            $messages = $threads ? Message::query()->whereIn('thread_id', $ids()->select('id'))->count() : 0;

            if (!$dry) {
                $ids()->select('id')->orderBy('id')->chunkById(self::CHUNK, function ($chunk) use ($attachments) {
                    $chunkIds = $chunk->pluck('id')->all();

                    // Files we stored for replies in these conversations go with them.
                    Message::query()->whereIn('thread_id', $chunkIds)->whereNotNull('attachments')->get(['id', 'attachments'])
                        ->each(fn (Message $m) => $attachments->delete($m->attachments));
                    DB::transaction(function () use ($chunkIds) {
                        Message::query()->whereIn('thread_id', $chunkIds)->delete();
                        MessageThread::query()->whereIn('id', $chunkIds)->delete();
                    });
                });
            }

            $this->line(($dry ? 'Would delete' : 'Deleted') . " {$threads} resolved conversation(s) idle for {$days}+ days, with {$messages} message(s).");
        }

        $rawDays = (int) $this->option('raw-days');
        if ($rawDays > 0) {
            $rawCutoff = now()->subDays($rawDays)->toDateTimeString();
            $count = Message::query()->whereNotNull('raw')->where('created_at', '<', $rawCutoff)->count()
                + MessageThread::query()->whereNotNull('raw')->where('updated_at', '<', $rawCutoff)->count();

            if (!$dry && $count) {
                Message::query()->whereNotNull('raw')->where('created_at', '<', $rawCutoff)->update(['raw' => null]);
                MessageThread::query()->whereNotNull('raw')->where('updated_at', '<', $rawCutoff)->update(['raw' => null]);
            }

            $this->line(($dry ? 'Would clear' : 'Cleared') . " stored platform payloads on {$count} old record(s).");
        }

        $this->info('[' . now()->toDateTimeString() . '] Messages prune ' . ($dry ? '(dry run) ' : '') . 'complete.');

        return 0;
    }
}
