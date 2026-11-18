<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageSavedReply;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;
use App\Models\User;
use App\Notifications\MessageThreadAssignedNotification;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Exceptions\MessagingException;
use App\Services\Messaging\MessageDriverManager;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\MessageSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Messages inbox (read-only in this release): one list of buyer conversations from every
 * connected store. Channel-agnostic; nothing here knows which platform a thread came from.
 */
class MessageController extends Controller
{
    private const PAGE_SIZE = 30;
    private const HEALTHY_WITHIN_MINUTES = 15;
    private const TABS = ['all', 'unread', 'pending', 'mine', 'awaiting', 'resolved'];

    private const STATUS_LABELS = [
        MessageThread::STATUS_PENDING => 'Needs reply',
        MessageThread::STATUS_AWAITING_BUYER => 'Awaiting buyer',
        MessageThread::STATUS_RESOLVED => 'Resolved',
    ];

    private const KIND_LABELS = [
        MessageThread::KIND_PRE_PURCHASE => 'Before purchase',
        MessageThread::KIND_POST_PURCHASE => 'After purchase',
        MessageThread::KIND_PLATFORM_SYSTEM => 'Platform notice',
        MessageThread::KIND_UNKNOWN => 'Conversation',
    ];

    public function __construct(
        private MessageSyncService $sync,
        private MessageDriverManager $drivers,
        private MessageSender $sender,
    ) {
        $this->middleware(PermissionMiddleware::using('view messages'));
        $this->middleware(PermissionMiddleware::using('reply messages'), ['only' => ['reply', 'retry', 'note', 'status', 'read']]);
        $this->middleware(PermissionMiddleware::using('assign messages'), ['only' => ['assign']]);
    }

    public function index()
    {
        return view('messages.index', [
            'stores' => $this->stores(),
            'health' => $this->health(),
        ]);
    }

    /**
     * Thread list for the current tab / store / search, plus tab counts. Also used for polling.
     */
    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => 'nullable|in:' . implode(',', self::TABS),
            'store' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $tab = $data['tab'] ?? 'all';
        $page = (int) ($data['page'] ?? 1);

        $query = MessageThread::query()
            ->with('salesChannel:id,name')
            ->when($data['store'] ?? null, fn (Builder $q, $id) => $q->where('sales_channel_id', $id));

        $this->applySearch($query, $data['q'] ?? null);
        $this->applyTab($query, $tab);

        $rows = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        return response()->json([
            'threads' => $rows->take(self::PAGE_SIZE)->map(fn (MessageThread $t) => $this->presentListItem($t))->values(),
            'has_more' => $rows->count() > self::PAGE_SIZE,
            'counts' => $this->tabCounts($data['store'] ?? null),
        ]);
    }

    /**
     * Lightweight numbers for the sidebar badge, plus what the browser needs to raise a desktop
     * notification: how many buyer messages arrived after message id `after`, and the newest one.
     * Without `after` it only returns the newest id, so the browser can set a baseline silently.
     */
    public function counts(Request $request): JsonResponse
    {
        $c = $this->tabCounts(null);

        $inbound = Message::query()
            ->where('messages.direction', Message::DIRECTION_INBOUND)
            ->whereHas('thread', fn (Builder $q) => $q->where('thread_kind', '!=', MessageThread::KIND_PLATFORM_SYSTEM));

        $latest = (clone $inbound)->with('thread.salesChannel')->orderByDesc('messages.id')->first();
        $after = $request->integer('after', 0);

        return response()->json([
            'unread' => $c['unread'],
            'pending' => $c['pending'],
            'latest_id' => $latest?->id ?? 0,
            'new' => $after > 0 ? (clone $inbound)->where('messages.id', '>', $after)->count() : 0,
            'latest' => $latest ? [
                'thread_id' => $latest->thread_id,
                'buyer' => $latest->thread->buyer_username ?: ($latest->sender_name ?: 'Buyer'),
                'store' => $this->shortStoreName((string) $latest->thread->salesChannel?->name),
                'preview' => \Illuminate\Support\Str::limit(trim((string) $latest->body), 140),
            ] : null,
        ]);
    }

    /**
     * Thread counts per tab, optionally for one store.
     *
     * @return array{all:int, unread:int, pending:int, mine:int, awaiting:int, resolved:int}
     */
    private function tabCounts(?string $store): array
    {
        $row = MessageThread::query()
            ->when($store, fn (Builder $q, $id) => $q->where('sales_channel_id', $id))
            ->selectRaw(
                'COUNT(*) AS total,'
                . ' COALESCE(SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END), 0) AS unread,'
                . ' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending,'
                . ' COALESCE(SUM(CASE WHEN assigned_user_id = ? THEN 1 ELSE 0 END), 0) AS mine,'
                . ' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS awaiting,'
                . ' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS resolved',
                [MessageThread::STATUS_PENDING, auth()->id() ?? 0, MessageThread::STATUS_AWAITING_BUYER, MessageThread::STATUS_RESOLVED],
            )
            ->first();

        return [
            'all' => (int) $row->total,
            'unread' => (int) $row->unread,
            'pending' => (int) $row->pending,
            'mine' => (int) $row->mine,
            'awaiting' => (int) $row->awaiting,
            'resolved' => (int) $row->resolved,
        ];
    }

    /**
     * One conversation with its messages and the linked product / order, from our database.
     */
    public function show(MessageThread $thread): JsonResponse
    {
        return response()->json($this->presentDetail($thread));
    }

    /**
     * Re-read the conversation from the platform (a read-only call), then return it as show() does.
     * If the platform cannot be reached, the stored copy is still returned with an error note.
     */
    public function refresh(MessageThread $thread): JsonResponse
    {
        try {
            $new = $this->sync->refreshThread($thread);
        } catch (MessagingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ] + $this->presentDetail($thread->refresh()), 502);
        }

        return response()->json([
            'success' => true,
            'new_messages' => $new,
        ] + $this->presentDetail($thread->refresh()));
    }

    // ------------------------------------------------------------------ team actions

    /**
     * Send a reply to the buyer through the store's sales channel (synchronously).
     */
    public function reply(Request $request, MessageThread $thread): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:' . MessageSender::MAX_LENGTH]]);

        try {
            $message = $this->sender->reply($thread, $request->user(), $data['body']);
        } catch (MessagingException $e) {
            return $this->failure($thread, $e->getMessage(), 422);
        }

        return $this->afterSend($thread, $message);
    }

    /**
     * Try again to send a reply that failed.
     */
    public function retry(Request $request, MessageThread $thread, Message $message): JsonResponse
    {
        abort_unless($message->thread_id === $thread->id, 404);

        try {
            $message = $this->sender->retry($message, $request->user());
        } catch (MessagingException $e) {
            return $this->failure($thread, $e->getMessage(), 422);
        }

        return $this->afterSend($thread, $message);
    }

    /**
     * Internal note, visible to the team only.
     */
    public function note(Request $request, MessageThread $thread): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:' . MessageSender::MAX_LENGTH]]);

        $this->sender->note($thread, $request->user(), $data['body']);

        return $this->done($thread);
    }

    public function status(Request $request, MessageThread $thread): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))]]);

        $this->sender->setStatus($thread, $data['status']);

        return $this->done($thread);
    }

    public function assign(Request $request, MessageThread $thread): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id']]);

        $assignee = null;
        if (!empty($data['user_id'])) {
            $assignee = User::findOrFail($data['user_id']);

            if (!$assignee->can('view messages')) {
                return $this->failure($thread, 'That person cannot access the Messages inbox.', 422);
            }
        }

        $previous = $thread->assigned_user_id;
        $this->sender->assign($thread, $assignee);

        if ($assignee && $assignee->id !== $previous && $assignee->id !== $request->user()->id) {
            try {
                $assignee->notify(new MessageThreadAssignedNotification($thread->id, $this->buyerLabel($thread), $request->user()->name));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->done($thread);
    }

    /**
     * Mark a conversation read or unread (also on the sales channel).
     */
    public function read(Request $request, MessageThread $thread): JsonResponse
    {
        $data = $request->validate(['read' => ['required', 'boolean']]);

        try {
            $warning = $this->sender->setRead($thread, (bool) $data['read']);
        } catch (MessagingException $e) {
            return $this->failure($thread, $e->getMessage(), 502);
        }

        return $this->done($thread, $warning ? ['warning' => $warning] : []);
    }

    private function afterSend(MessageThread $thread, Message $message): JsonResponse
    {
        if ($message->delivery_status === Message::DELIVERY_FAILED) {
            return $this->failure($thread, 'The message was not delivered: ' . ($message->error ?: 'unknown error') . ' You can retry it from the conversation.', 502);
        }

        return $this->done($thread);
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function done(MessageThread $thread, array $extra = []): JsonResponse
    {
        return response()->json(['success' => true] + $extra + $this->presentDetail($thread->refresh()));
    }

    private function failure(MessageThread $thread, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $this->presentDetail($thread->refresh()), $status);
    }

    // ------------------------------------------------------------------ queries

    private function applySearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        $query->where(function (Builder $w) use ($like) {
            $w->where('buyer_username', 'like', $like)
                ->orWhere('last_message_preview', 'like', $like)
                ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', $like))
                ->orWhereHas('messages', fn (Builder $m) => $m->where('body', 'like', $like));
        });
    }

    private function applyTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'unread' => $query->where('unread_count', '>', 0),
            'pending' => $query->where('status', MessageThread::STATUS_PENDING),
            'mine' => $query->where('assigned_user_id', auth()->id()),
            'awaiting' => $query->where('status', MessageThread::STATUS_AWAITING_BUYER),
            'resolved' => $query->where('status', MessageThread::STATUS_RESOLVED),
            default => null,
        };
    }

    /**
     * Stores that have messaging support, for the store filter.
     */
    private function stores(): array
    {
        return SalesChannel::query()
            ->whereIn('type', $this->drivers->supportedTypes())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SalesChannel $c) => $this->presentStore($c))
            ->all();
    }

    /**
     * Sync health for the header chip: how many stores synced recently and which have problems.
     *
     * @return array{total:int, healthy:int, problems:array<int,array{name:string,reason:string}>}
     */
    private function health(): array
    {
        $channels = SalesChannel::query()
            ->whereIn('type', $this->drivers->supportedTypes())
            ->with('messageState')
            ->get();

        $total = 0;
        $problems = [];
        $cutoff = now()->subMinutes(self::HEALTHY_WITHIN_MINUTES);

        foreach ($channels as $channel) {
            $state = $channel->messageState;

            if ($state && !$state->messaging_enabled) {
                continue; // switched off on purpose
            }

            $total++;
            $name = $this->shortStoreName($channel->name);

            if ($state?->last_error) {
                $problems[] = ['name' => $name, 'reason' => $state->last_error];
            } elseif (!$state?->last_synced_at) {
                $problems[] = ['name' => $name, 'reason' => 'Not synced yet'];
            } elseif ($state->last_synced_at->lt($cutoff)) {
                $problems[] = ['name' => $name, 'reason' => 'No successful sync in the last ' . self::HEALTHY_WITHIN_MINUTES . ' minutes'];
            }
        }

        return ['total' => $total, 'healthy' => $total - count($problems), 'problems' => $problems];
    }

    // ------------------------------------------------------------- presentation

    /**
     * @return array<string,mixed>
     */
    private function presentListItem(MessageThread $t): array
    {
        $buyer = $this->buyerLabel($t);

        return [
            'id' => $t->id,
            'buyer' => $buyer,
            'initials' => $this->initials($buyer),
            'preview' => $t->last_message_preview ?? '',
            'last_at' => $t->last_message_at?->toIso8601String(),
            'direction' => $t->last_message_direction,
            'unread' => (int) $t->unread_count,
            'status' => $t->status,
            'status_label' => self::STATUS_LABELS[$t->status] ?? $t->status,
            'store' => $t->salesChannel ? $this->presentStore($t->salesChannel) : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentDetail(MessageThread $thread): array
    {
        $thread->load(['salesChannel', 'assignee:id,name', 'order', 'product', 'messages' => fn ($q) => $q->orderBy('sent_at')->orderBy('id')]);

        $buyer = $this->buyerLabel($thread);

        return [
            'thread' => [
                'id' => $thread->id,
                'buyer' => $buyer,
                'buyer_username' => $thread->buyer_username,
                'initials' => $this->initials($buyer),
                'status' => $thread->status,
                'status_label' => self::STATUS_LABELS[$thread->status] ?? $thread->status,
                'kind' => $thread->thread_kind,
                'kind_label' => self::KIND_LABELS[$thread->thread_kind] ?? self::KIND_LABELS[MessageThread::KIND_UNKNOWN],
                'subject' => $thread->subject,
                'unread' => (int) $thread->unread_count,
                'store' => $thread->salesChannel ? $this->presentStore($thread->salesChannel) : null,
                'assignee' => $thread->assignee ? ['id' => $thread->assignee->id, 'name' => $thread->assignee->name] : null,
                'last_at' => $thread->last_message_at?->toIso8601String(),
            ],
            'messages' => $thread->messages->map(fn (Message $m) => [
                'id' => $m->id,
                'kind' => $m->is_auto_reply ? 'auto_reply' : ($m->isInternalNote() ? 'note' : $m->direction),
                'sender' => $m->sender_name,
                'body' => $m->body,
                'attachments' => $m->attachments ?? [],
                'sent_at' => $m->sent_at?->toIso8601String(),
                'failed' => $m->delivery_status === Message::DELIVERY_FAILED,
                'error' => $m->delivery_status === Message::DELIVERY_FAILED ? $m->error : null,
                'unread' => $m->isInbound() && !$m->read_at,
                'by_me' => $m->sent_by_user_id !== null && $m->sent_by_user_id === auth()->id(),
            ])->values(),
            'product' => $this->presentProduct($thread),
            'order' => $this->presentOrder($thread),
            'actions' => $this->presentActions($thread),
        ];
    }

    /**
     * What the current user may do with this conversation, plus the data the reply box needs.
     *
     * @return array<string,mixed>
     */
    private function presentActions(MessageThread $thread): array
    {
        $user = auth()->user();
        $canReplyPerm = (bool) $user?->can('reply messages');
        $canAssign = (bool) $user?->can('assign messages');

        $caps = $this->drivers->forChannel($thread->salesChannel)->capabilities();
        $system = $thread->isPlatformSystem();

        $canReply = $canReplyPerm && !$system && in_array(MessageChannelDriver::CAP_SEND, $caps, true);

        return [
            'can_reply' => $canReply,
            'can_note' => $canReplyPerm,
            'can_assign' => $canAssign,
            'can_mark_read' => $canReplyPerm && !$system,
            'can_mark_unread' => $canReplyPerm && !$system && in_array(MessageChannelDriver::CAP_MARK_READ, $caps, true),
            'reply_blocked_reason' => $system ? 'Platform notices cannot be replied to.' : null,
            'max_length' => MessageSender::MAX_LENGTH,
            'assignable' => $canAssign ? $this->assignableUsers() : [],
            'saved_replies' => $canReply ? $this->savedReplies($thread) : [],
        ];
    }

    /**
     * Team members who can open the inbox.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function assignableUsers(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name'])
            ->filter(fn (User $u) => $u->can('view messages'))
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /**
     * Active saved replies with {buyer_name}, {item_title} and {order_number} filled in for this conversation.
     *
     * @return array<int,array{id:int,title:string,body:string}>
     */
    private function savedReplies(MessageThread $thread): array
    {
        $vars = [
            'buyer_name' => $this->buyerLabel($thread),
            'item_title' => $thread->product?->name,
            'order_number' => $thread->order?->order_number,
        ];

        return MessageSavedReply::query()->where('is_active', true)->orderBy('title')->get()
            ->map(fn (MessageSavedReply $r) => ['id' => $r->id, 'title' => $r->title, 'body' => $r->render(array_filter($vars, fn ($v) => $v !== null && $v !== ''))])
            ->all();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function presentProduct(MessageThread $thread): ?array
    {
        if (!$thread->external_reference_id) {
            return null;
        }

        $listing = SalesChannelProduct::query()
            ->where('sales_channel_id', $thread->sales_channel_id)
            ->where('external_listing_id', $thread->external_reference_id)
            ->first(['listing_url', 'listing_status', 'title']);

        $product = $thread->product;

        return [
            'reference' => $thread->external_reference_id,
            'name' => $product?->name ?? $listing?->title,
            'sku' => $product?->sku,
            'price' => $product?->price,
            'image' => $product?->getImageUrl(),
            'listing_url' => $listing?->listing_url,
            'listing_status' => $listing?->listing_status,
            'linked' => $product !== null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function presentOrder(MessageThread $thread): ?array
    {
        $order = $thread->order;
        if (!$order) {
            return null;
        }

        return [
            'number' => $order->order_number,
            'status' => $order->order_status,
            'total' => $order->total,
            'currency' => $order->currency,
            'url' => auth()->user()?->can('view orders') ? route('orders.show', $order->id) : null,
        ];
    }

    /**
     * @return array{id:int, name:string, short:string, color:int}
     */
    private function presentStore(SalesChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'short' => $this->shortStoreName($channel->name),
            'color' => ($channel->id % 8) + 1,
        ];
    }

    /**
     * "Store 1 (Apex Auto Body Parts)" -> "Apex Auto Body Parts".
     */
    private function shortStoreName(string $name): string
    {
        return preg_match('/\(([^)]+)\)\s*$/', $name, $m) ? trim($m[1]) : $name;
    }

    private function buyerLabel(MessageThread $thread): string
    {
        if ($thread->buyer_name) {
            return $thread->buyer_name;
        }

        if ($thread->buyer_username) {
            return $thread->buyer_username;
        }

        return $thread->isPlatformSystem() ? 'Platform notice' : 'Unknown buyer';
    }

    private function initials(string $label): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $label) ?: '?';

        return strtoupper(substr($clean, 0, 2));
    }
}
