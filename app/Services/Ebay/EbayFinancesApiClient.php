<?php

namespace App\Services\Ebay;

use Exception;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level HTTP client for eBay Sell Finances API (REST).
 *
 * Separate from EbayApiClient (Trading API/XML) because it's a different
 * base URL, auth header style (bearer token vs IAF header), and pagination
 * model (offset/limit vs page number).
 *
 * Usage:
 *   $client = app(EbayFinancesApiClient::class);
 *   $channel = app(EbayApiClient::class)->ensureValidToken($salesChannel);
 *   $page = $client->getTransactions($channel, $from, $to, $offset, $limit);
 */
class EbayFinancesApiClient
{
    public const EBAY_FINANCES_API_URL = 'https://apiz.ebay.com/sell/finances/v1';

    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_LIMIT = 200;

    /**
     * Fetch one page of transactions in a date range.
     *
     * $dateFrom / $dateTo must be ISO 8601 UTC (e.g. 2026-07-01T00:00:00.000Z).
     */
    public function getTransactions(SalesChannel $salesChannel, string $dateFrom, string $dateTo, int $offset = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $response = Http::timeout(self::DEFAULT_TIMEOUT)
            ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
            ->withToken($salesChannel->access_token)
            ->get(self::EBAY_FINANCES_API_URL . '/transaction', [
                'filter' => "transactionDate:[{$dateFrom}..{$dateTo}]",
                'limit' => $limit,
                'offset' => $offset,
            ]);

        if ($response->failed()) {
            Log::channel('ebay-finance-sync')->error('eBay GetTransactions Failed', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
                'offset' => $offset,
                'body' => $response->body(),
            ]);
            throw new Exception('eBay GetTransactions failed: ' . $response->body());
        }

        Log::channel('ebay-finance-sync')->debug('eBay GetTransactions Response', [
            'sales_channel_id' => $salesChannel->id,
            'offset' => $offset,
            'limit' => $limit,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Fetch all transactions in a date range, following offset/limit pagination.
     *
     * @return array<int, array>
     */
    public function getAllTransactions(SalesChannel $salesChannel, string $dateFrom, string $dateTo): array
    {
        $all = [];
        $offset = 0;
        $limit = self::DEFAULT_LIMIT;

        do {
            $page = $this->getTransactions($salesChannel, $dateFrom, $dateTo, $offset, $limit);
            $transactions = $page['transactions'] ?? [];
            array_push($all, ...$transactions);

            $total = $page['total'] ?? count($transactions);
            $offset += $limit;
        } while ($offset < $total);

        return $all;
    }
}
