<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayMessageApiClient;
use App\Services\Messaging\Exceptions\MessagingException;
use App\Services\Messaging\Exceptions\MissingScopeException;
use Illuminate\Console\Command;

/**
 * Phase 0 spike: READ-ONLY probe of the eBay Message API.
 *
 * For one store (or every eBay store with --all) it checks the messaging scope, lists a few
 * conversations of each type, fetches one conversation's messages, and saves every raw
 * response as JSON so the real payload shapes can be inspected. It never sends a message
 * or changes anything on eBay.
 *
 * Output folder: storage/logs/ebay/messages/YYYY-MM-DD/ (contains buyer data; delete when done).
 */
class ProbeEbayMessages extends Command
{
    protected $signature = 'messages:probe
                            {channel? : Sales channel ID of the eBay store (omit when using --all)}
                            {--all : Probe every eBay store and print a one-table summary}
                            {--limit=5 : Conversations to request per type}
                            {--no-detail : Skip fetching one conversation\'s messages}';

    protected $description = 'Read-only check of the eBay Message API for one store or all stores; saves raw responses for inspection';

    private string $dir;

    public function handle(EbayMessageApiClient $api): int
    {
        $limit = max(1, min(50, (int) $this->option('limit')));
        $detail = !$this->option('no-detail');

        $this->dir = storage_path('logs/ebay/messages/' . now()->format('Y-m-d'));

        if ($this->option('all')) {
            return $this->probeAll($api, $limit, $detail);
        }

        if (!$this->argument('channel')) {
            $this->error('Give a sales channel ID, or use --all.');
            return 1;
        }

        $channel = SalesChannel::find($this->argument('channel'));

        if (!$channel) {
            $this->error("Sales channel {$this->argument('channel')} not found.");
            return 1;
        }

        if (!$channel->isEbay()) {
            $this->error("Channel '{$channel->name}' is type '{$channel->type}', not eBay.");
            return 1;
        }

        $result = $this->probeChannel($channel, $api, $limit, $detail, verbose: true);

        $this->newLine();
        $this->info("Raw responses are in: {$this->dir}");
        $this->line('They contain buyer names and message text; delete them when you are done inspecting.');

        return $result['ok'] ? 0 : 1;
    }

    private function probeAll(EbayMessageApiClient $api, int $limit, bool $detail): int
    {
        $channels = SalesChannel::where('type', 'ebay')->orderBy('id')->get();

        if ($channels->isEmpty()) {
            $this->warn('No eBay stores found.');
            return 1;
        }

        $rows = [];
        $failed = 0;

        foreach ($channels as $channel) {
            $this->line("Probing {$channel->name} (ID {$channel->id})...");
            $r = $this->probeChannel($channel, $api, $limit, $detail, verbose: false);

            $rows[] = [$channel->id, $channel->name, $r['scope'], $r['call'], $r['conversations'], $r['usernames']];
            $failed += $r['ok'] ? 0 : 1;
        }

        $this->newLine();
        $this->table(['ID', 'Store', 'Scope listed', 'eBay call', 'Conversations', 'Usernames'], $rows);
        $this->line('Usernames: "OK" = eBay returned the store\'s own username; "no match" = external_account_id(s) may hold something else; "no data" = nothing to compare.');
        $this->line("Raw responses are in: {$this->dir} (they contain buyer data; delete when done).");

        $this->newLine();
        if ($failed === 0) {
            $this->info('All stores passed.');
        } else {
            $this->warn("{$failed} of {$channels->count()} store(s) need attention (re-authorize, or enable commerce.message for that keyset).");
        }

        return $failed === 0 ? 0 : 1;
    }

    /**
     * Probe one store.
     *
     * @return array{ok:bool, scope:string, call:string, conversations:string, usernames:string}
     */
    private function probeChannel(SalesChannel $channel, EbayMessageApiClient $api, int $limit, bool $detail, bool $verbose): array
    {
        $result = ['ok' => false, 'scope' => 'no', 'call' => 'not run', 'conversations' => '-', 'usernames' => '-'];

        $own = array_values(array_filter(array_merge(
            (array) ($channel->external_account_ids ?? []),
            [$channel->external_account_id],
        )));

        $this->say($verbose, 'info', "Store: {$channel->name} (ID {$channel->id})");
        $this->say($verbose, 'line', 'Own eBay usernames on record: ' . ($own ? implode(', ', $own) : '(none)'));

        if (!$api->hasMessageScope($channel)) {
            $this->say($verbose, 'error', 'This store\'s User Scopes do not include commerce.message.');
            $this->say($verbose, 'line', 'Add ' . EbayMessageApiClient::SCOPE . ' to the store\'s User Scopes, re-authorize the store, then run this again.');
            $result['call'] = 'skipped (scope not listed)';
            return $result;
        }

        $result['scope'] = 'yes';
        $this->say($verbose, 'line', 'Scope check: commerce.message is listed in User Scopes.');

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $seenUsernames = [];
        $counts = [];
        $firstMemberConversationId = null;

        try {
            foreach ([EbayMessageApiClient::TYPE_FROM_MEMBERS, EbayMessageApiClient::TYPE_FROM_EBAY] as $type) {
                $this->say($verbose, 'newLine');
                $this->say($verbose, 'info', "getConversations ({$type}, limit {$limit})");

                $body = $api->getConversations($channel, $type, [], $limit, 0);
                $file = $this->save("getConversations_{$type}", $channel, $body);

                $conversations = $body['conversations'] ?? [];
                $counts[$type] = count($conversations);

                $this->say($verbose, 'line', '  Response keys: ' . ($body ? implode(', ', array_keys($body)) : '(empty)'));
                $this->say($verbose, 'line', '  Conversations returned: ' . count($conversations) . (isset($body['total']) ? " (total {$body['total']})" : ''));
                if ($conversations) {
                    $this->say($verbose, 'line', '  Conversation keys: ' . implode(', ', array_keys($conversations[0])));
                    if (isset($conversations[0]['latestMessage']) && is_array($conversations[0]['latestMessage'])) {
                        $this->say($verbose, 'line', '  latestMessage keys: ' . implode(', ', array_keys($conversations[0]['latestMessage'])));
                    }
                }

                foreach ($conversations as $c) {
                    $m = $c['latestMessage'] ?? [];
                    $this->say($verbose, 'line', sprintf(
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
                $this->say($verbose, 'line', "  Saved: {$file}");
            }

            if ($firstMemberConversationId && $detail) {
                $this->say($verbose, 'newLine');
                $this->say($verbose, 'info', "getConversation ({$firstMemberConversationId})");

                $body = $api->getConversation($channel, (string) $firstMemberConversationId, EbayMessageApiClient::TYPE_FROM_MEMBERS, $limit, 0);
                $file = $this->save('getConversation', $channel, $body);

                $messages = $body['messages'] ?? [];
                $this->say($verbose, 'line', '  Response keys: ' . ($body ? implode(', ', array_keys($body)) : '(empty)'));
                $this->say($verbose, 'line', '  Messages returned: ' . count($messages));
                if ($messages) {
                    $this->say($verbose, 'line', '  Message keys: ' . implode(', ', array_keys($messages[0])));
                }
                $this->say($verbose, 'line', "  Saved: {$file}");
            }
        } catch (MissingScopeException $e) {
            $this->say($verbose, 'newLine');
            $this->say($verbose, 'error', 'eBay refused the request for lack of permission: ' . $e->getMessage());
            $this->say($verbose, 'line', 'Re-authorize the store so its token includes commerce.message, then run this again.');
            $result['call'] = 'refused: re-authorize / enable scope';
            return $result;
        } catch (MessagingException $e) {
            $this->say($verbose, 'newLine');
            $this->say($verbose, 'error', 'Message API call failed: ' . $e->getMessage());
            $result['call'] = 'failed: ' . mb_substr($e->getMessage(), 0, 60);
            return $result;
        }

        $result['ok'] = true;
        $result['call'] = 'OK';
        $result['conversations'] = ($counts[EbayMessageApiClient::TYPE_FROM_MEMBERS] ?? 0) . ' buyer / ' . ($counts[EbayMessageApiClient::TYPE_FROM_EBAY] ?? 0) . ' eBay';
        $result['usernames'] = $this->usernameCheck($own, array_keys($seenUsernames), $verbose);

        return $result;
    }

    /**
     * Phase 0 question: do the usernames on record match the sender/recipient names eBay returns?
     *
     * @param array<int,string> $own
     * @param array<int,string> $seen lowercase usernames seen in the sample
     * @return string OK | no match | no data
     */
    private function usernameCheck(array $own, array $seen, bool $verbose): string
    {
        $matched = array_values(array_intersect($seen, array_map('strtolower', $own)));

        $this->say($verbose, 'newLine');

        if (!$seen) {
            $this->say($verbose, 'warn', 'Username check: no conversations to compare against, so this is still unconfirmed.');
            return 'no data';
        }

        if ($matched) {
            $this->say($verbose, 'info', 'Username check: OK, eBay returned the store\'s own username(s): ' . implode(', ', $matched));
            return 'OK';
        }

        $this->say($verbose, 'warn', 'Username check: none of the usernames on record (' . ($own ? implode(', ', $own) : 'none') . ') appear in the sample.');
        $this->say($verbose, 'line', '  Usernames seen: ' . implode(', ', $seen));
        $this->say($verbose, 'line', '  If the store\'s own name is among them, external_account_id(s) holds something else (e.g. a numeric id) and the driver\'s buyer/seller detection needs adjusting.');

        return 'no match';
    }

    /**
     * Print only in single-store (verbose) mode.
     */
    private function say(bool $verbose, string $method, string $message = ''): void
    {
        if (!$verbose) {
            return;
        }

        $method === 'newLine' ? $this->newLine() : $this->{$method}($message);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function save(string $action, SalesChannel $channel, array $data): string
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . now()->format('H-i-s') . "_{$action}_channel{$channel->id}.json";

        file_put_contents($file, json_encode([
            'action' => $action,
            'sales_channel_id' => $channel->id,
            'logged_at' => now()->toIso8601String(),
            'response' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $file;
    }
}
