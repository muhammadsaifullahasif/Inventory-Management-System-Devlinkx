<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayMessageApiClient;
use App\Services\Messaging\Exceptions\MessagingException;
use App\Services\Messaging\Exceptions\MissingScopeException;
use Illuminate\Console\Command;

/**
 * Phase 0 spike: READ-ONLY probe of the eBay Message API for one store.
 *
 * Checks the store's messaging scope, lists a few conversations of each type, fetches one
 * conversation's messages, and saves every raw response as JSON so the real payload shapes
 * can be inspected. It never sends a message or changes anything on eBay.
 *
 * Output folder: storage/logs/ebay/messages/YYYY-MM-DD/ (contains buyer data; delete when done).
 */
class ProbeEbayMessages extends Command
{
    protected $signature = 'messages:probe
                            {channel : Sales channel ID of the eBay store}
                            {--limit=5 : Conversations to request per type}
                            {--no-detail : Skip fetching one conversation\'s messages}';

    protected $description = 'Read-only check of the eBay Message API for one store; saves raw responses for inspection';

    public function handle(EbayMessageApiClient $api): int
    {
        $channel = SalesChannel::find($this->argument('channel'));

        if (!$channel) {
            $this->error("Sales channel {$this->argument('channel')} not found.");
            return 1;
        }

        if (!$channel->isEbay()) {
            $this->error("Channel '{$channel->name}' is type '{$channel->type}', not eBay.");
            return 1;
        }

        $own = array_values(array_filter(array_merge(
            (array) ($channel->external_account_ids ?? []),
            [$channel->external_account_id],
        )));

        $this->info("Store: {$channel->name} (ID {$channel->id})");
        $this->line('Own eBay usernames on record: ' . ($own ? implode(', ', $own) : '(none)'));

        if (!$api->hasMessageScope($channel)) {
            $this->error('This store\'s User Scopes do not include commerce.message.');
            $this->line('Add ' . EbayMessageApiClient::SCOPE . ' to the store\'s User Scopes, re-authorize the store, then run this again.');
            return 1;
        }
        $this->line('Scope check: commerce.message is listed in User Scopes.');

        $limit = max(1, min(50, (int) $this->option('limit')));
        $dir = storage_path('logs/ebay/messages/' . now()->format('Y-m-d'));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $seenUsernames = [];
        $firstMemberConversationId = null;

        try {
            foreach ([EbayMessageApiClient::TYPE_FROM_MEMBERS, EbayMessageApiClient::TYPE_FROM_EBAY] as $type) {
                $this->newLine();
                $this->info("getConversations ({$type}, limit {$limit})");

                $body = $api->getConversations($channel, $type, [], $limit, 0);
                $file = $this->save($dir, "getConversations_{$type}", $channel, $body);

                $conversations = $body['conversations'] ?? [];
                $this->line('  Response keys: ' . ($body ? implode(', ', array_keys($body)) : '(empty)'));
                $this->line('  Conversations returned: ' . count($conversations) . (isset($body['total']) ? " (total {$body['total']})" : ''));
                if ($conversations) {
                    $this->line('  Conversation keys: ' . implode(', ', array_keys($conversations[0])));
                    if (isset($conversations[0]['latestMessage']) && is_array($conversations[0]['latestMessage'])) {
                        $this->line('  latestMessage keys: ' . implode(', ', array_keys($conversations[0]['latestMessage'])));
                    }
                }

                foreach ($conversations as $c) {
                    $m = $c['latestMessage'] ?? [];
                    $this->line(sprintf(
                        '  - %s | %s -> %s | %s',
                        $c['conversationId'] ?? '?',
                        $m['senderUsername'] ?? '?',
                        $m['recipientUsername'] ?? '?',
                        $m['createdDate'] ?? ($c['createdDate'] ?? '?'),
                    ));
                    foreach (['senderUsername', 'recipientUsername'] as $k) {
                        if (!empty($m[$k])) {
                            $seenUsernames[strtolower((string) $m[$k])] = true;
                        }
                    }
                }

                if ($type === EbayMessageApiClient::TYPE_FROM_MEMBERS && $conversations) {
                    $firstMemberConversationId = $conversations[0]['conversationId'] ?? null;
                }
                $this->line("  Saved: {$file}");
            }

            if ($firstMemberConversationId && !$this->option('no-detail')) {
                $this->newLine();
                $this->info("getConversation ({$firstMemberConversationId})");

                $body = $api->getConversation($channel, (string) $firstMemberConversationId, EbayMessageApiClient::TYPE_FROM_MEMBERS, $limit, 0);
                $file = $this->save($dir, 'getConversation', $channel, $body);

                $messages = $body['messages'] ?? [];
                $this->line('  Response keys: ' . ($body ? implode(', ', array_keys($body)) : '(empty)'));
                $this->line('  Messages returned: ' . count($messages));
                if ($messages) {
                    $this->line('  Message keys: ' . implode(', ', array_keys($messages[0])));
                }
                $this->line("  Saved: {$file}");
            }
        } catch (MissingScopeException $e) {
            $this->newLine();
            $this->error('eBay refused the request for lack of permission: ' . $e->getMessage());
            $this->line('Re-authorize the store so its token includes commerce.message, then run this again.');
            return 1;
        } catch (MessagingException $e) {
            $this->newLine();
            $this->error('Message API call failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->reportUsernameCheck($own, array_keys($seenUsernames));
        $this->newLine();
        $this->info("Raw responses are in: {$dir}");
        $this->line('They contain buyer names and message text; delete them when you are done inspecting.');

        return 0;
    }

    /**
     * Phase 0 question: do the usernames on record match the sender/recipient names eBay returns?
     *
     * @param array<int,string> $own
     * @param array<int,string> $seen lowercase usernames seen in the sample
     */
    private function reportUsernameCheck(array $own, array $seen): void
    {
        $ownLower = array_map('strtolower', $own);
        $matched = array_values(array_intersect($seen, $ownLower));

        if (!$seen) {
            $this->warn('Username check: no conversations to compare against, so this is still unconfirmed.');
        } elseif ($matched) {
            $this->info('Username check: OK, eBay returned the store\'s own username(s): ' . implode(', ', $matched));
        } else {
            $this->warn('Username check: none of the usernames on record (' . ($own ? implode(', ', $own) : 'none') . ') appear in the sample.');
            $this->line('  Usernames seen: ' . implode(', ', $seen));
            $this->line('  If the store\'s own name is among them, external_account_id(s) holds something else (e.g. a numeric id) and the driver\'s buyer/seller detection needs adjusting.');
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function save(string $dir, string $action, SalesChannel $channel, array $data): string
    {
        $file = $dir . DIRECTORY_SEPARATOR . now()->format('H-i-s') . "_{$action}_channel{$channel->id}.json";

        file_put_contents($file, json_encode([
            'action' => $action,
            'sales_channel_id' => $channel->id,
            'logged_at' => now()->toIso8601String(),
            'response' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $file;
    }
}
