<?php

namespace App\Services\Ebay;

use App\Models\SalesChannel;
use App\Services\Messaging\Exceptions\MessagingException;
use App\Services\Messaging\Exceptions\MissingScopeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin REST client for the eBay Commerce Message API.
 * https://developer.ebay.com/api-docs/commerce/message/overview.html
 *
 * Returns decoded JSON arrays and throws MessagingException on failure
 * (MissingScopeException when the store has not granted the messaging scope).
 *
 * NOTE: endpoint paths and query/body names follow eBay's published docs but have not
 * yet been checked against live responses. Verify them during the Phase 0 capture,
 * in particular the send_message / update_conversation paths and the page size limit.
 */
class EbayMessageApiClient
{
    public const BASE_URL = 'https://api.ebay.com/commerce/message/v1';
    public const SCOPE = 'https://api.ebay.com/oauth/api_scope/commerce.message';

    public const TYPE_FROM_MEMBERS = 'FROM_MEMBERS';
    public const TYPE_FROM_EBAY = 'FROM_EBAY';

    private const TIMEOUT = 60;
    private const RETRY_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(
        private EbayApiClient $tokens,
    ) {}

    /**
     * Whether the store's configured OAuth scopes include messaging.
     * (Refresh tokens only carry scopes listed in user_scopes.)
     */
    public function hasMessageScope(SalesChannel $channel): bool
    {
        return str_contains((string) $channel->user_scopes, 'commerce.message');
    }

    /**
     * List conversations of one type.
     *
     * @param array{conversation_status?:string, reference_id?:string, reference_type?:string,
     *              other_party_username?:string, start_time?:string, end_time?:string} $filters
     * @return array<string,mixed> Decoded body (conversations[], total, next, ...)
     */
    public function getConversations(
        SalesChannel $channel,
        string $conversationType,
        array $filters = [],
        int $limit = 50,
        int $offset = 0,
    ): array {
        $query = array_merge(
            array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            [
                'conversation_type' => $conversationType,
                'limit' => $limit,
                'offset' => $offset,
            ],
        );

        return $this->request($channel, 'GET', '/conversation', ['query' => $query]);
    }

    /**
     * Messages of one conversation.
     *
     * @return array<string,mixed> Decoded body (conversationId, messages[], ...)
     */
    public function getConversation(
        SalesChannel $channel,
        string $conversationId,
        string $conversationType,
        int $limit = 50,
        int $offset = 0,
    ): array {
        return $this->request($channel, 'GET', '/conversation/' . rawurlencode($conversationId), [
            'query' => [
                'conversation_type' => $conversationType,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Send a message, either in an existing conversation (conversationId)
     * or to a user (otherPartyUsername).
     *
     * @param array<string,mixed> $payload messageText + conversationId|otherPartyUsername, optional reference, messageMedia
     * @return array<string,mixed>
     */
    public function sendMessage(SalesChannel $channel, array $payload): array
    {
        return $this->request($channel, 'POST', '/send_message', ['json' => $payload]);
    }

    /**
     * Change read state / status of a conversation.
     *
     * @param array<string,mixed> $payload conversationId, conversationType, and read|conversationStatus
     * @return array<string,mixed>
     */
    public function updateConversation(SalesChannel $channel, array $payload): array
    {
        return $this->request($channel, 'POST', '/update_conversation', ['json' => $payload]);
    }

    /**
     * @param array{query?:array<string,mixed>, json?:array<string,mixed>} $options
     * @return array<string,mixed>
     */
    private function request(SalesChannel $channel, string $method, string $path, array $options = []): array
    {
        if (!$this->hasMessageScope($channel)) {
            throw new MissingScopeException(
                "Store '{$channel->name}' has no eBay messaging permission. Add the commerce.message scope and re-authorize the store.",
                ['sales_channel_id' => $channel->id],
            );
        }

        $channel = $this->tokens->ensureValidToken($channel);

        $pending = Http::timeout(self::TIMEOUT)
            ->connectTimeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $channel->access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
            ])
            // Back off on rate limiting / transient eBay errors; hand back the last response if it still fails.
            ->retry(3, 1000, function ($exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException
                    && in_array($exception->response->status(), self::RETRY_STATUSES, true);
            }, throw: false);

        try {
            $response = match ($method) {
                'GET' => $pending->get(self::BASE_URL . $path, $options['query'] ?? []),
                default => $pending->send($method, self::BASE_URL . $path, array_intersect_key($options, ['json' => 1, 'query' => 1])),
            };
        } catch (ConnectionException $e) {
            throw new MessagingException('Could not reach eBay: ' . $e->getMessage(), ['sales_channel_id' => $channel->id], 0, $e);
        }

        $data = $response->json() ?? [];

        if ($response->failed()) {
            $errorId = $data['errors'][0]['errorId'] ?? null;
            $message = $data['errors'][0]['message'] ?? ('eBay message API returned HTTP ' . $response->status());
            $context = [
                'sales_channel_id' => $channel->id,
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'error_id' => $errorId,
            ];

            Log::channel('ebay')->error('eBay Message API call failed', $context + ['response' => $data]);

            // 403 / errorId 1100 = "Insufficient permissions": the token lacks the messaging scope.
            if ($response->status() === 403 || (int) $errorId === 1100) {
                throw new MissingScopeException(
                    "eBay rejected the request for '{$channel->name}': {$message}. The store may need to be re-authorized with the commerce.message scope.",
                    $context,
                );
            }

            throw new MessagingException($message, $context);
        }

        return is_array($data) ? $data : [];
    }
}
