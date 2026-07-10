<?php

namespace App\Services\Ebay;

use App\Models\EbayFinanceTransaction;
use App\Models\Order;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Log;

/**
 * Syncs eBay Sell Finances API transactions (fees, shipping labels, ad
 * charges, sale settlements) and rolls them up into per-order summary
 * columns on the `orders` table.
 *
 * Fee classification (confirmed against a live GetTransactions response
 * for order 23-14857-05422 on 2026-07-10):
 * - transactionType=SALE: revenue transaction, `amount` is already net of
 *   eBay's marketplace fees, `totalFeeAmount` is those fees combined
 *   (final value fee + related per-order fees). Has top-level `orderId`.
 * - transactionType=SHIPPING_LABEL: DEBIT, has top-level `orderId`.
 * - transactionType=NON_SALE_CHARGE with feeType=AD_FEE: Promoted Listings
 *   charge, DEBIT. No top-level `orderId` — order is linked via
 *   `references[]` where referenceType=ORDER_ID.
 * - Anything else falls into an 'other' bucket rather than being dropped,
 *   since eBay's transaction type list is broader than what's been
 *   observed so far (refunds, disputes, transfers, ...).
 */
class EbayFinanceSyncService
{
    public function __construct(
        protected EbayApiClient $apiClient,
        protected EbayFinancesApiClient $financesClient,
    ) {
    }

    /**
     * Sync one channel's transactions in a date range and recompute summary
     * columns for every order touched. Returns counts for reporting.
     */
    public function syncChannel(SalesChannel $salesChannel, string $dateFrom, string $dateTo): array
    {
        $salesChannel = $this->apiClient->ensureValidToken($salesChannel);

        $transactions = $this->financesClient->getAllTransactions($salesChannel, $dateFrom, $dateTo);

        $created = 0;
        $updated = 0;
        $touchedOrderIds = [];

        foreach ($transactions as $transaction) {
            [$row, $wasRecentlyCreated] = $this->upsertTransaction($salesChannel, $transaction);

            $wasRecentlyCreated ? $created++ : $updated++;

            if ($row->order_id) {
                $touchedOrderIds[$row->order_id] = true;
            }
        }

        foreach (array_keys($touchedOrderIds) as $orderId) {
            $this->recomputeOrderSummary($orderId);
        }

        return [
            'fetched' => count($transactions),
            'created' => $created,
            'updated' => $updated,
            'orders_updated' => count($touchedOrderIds),
        ];
    }

    protected function upsertTransaction(SalesChannel $salesChannel, array $transaction): array
    {
        $transactionId = $transaction['transactionId'] ?? '';
        $type = $transaction['transactionType'] ?? '';
        $category = $this->classify($type, $transaction);
        $ebayOrderId = $transaction['orderId'] ?? $this->extractOrderIdFromReferences($transaction);

        $order = $ebayOrderId
            ? Order::where('sales_channel_id', $salesChannel->id)->where('ebay_order_id', $ebayOrderId)->first()
            : null;

        if ($ebayOrderId && !$order) {
            Log::warning('eBay finance transaction has no matching order', [
                'sales_channel_id' => $salesChannel->id,
                'ebay_transaction_id' => $transactionId,
                'ebay_order_id' => $ebayOrderId,
            ]);
        }

        $existing = EbayFinanceTransaction::where('ebay_transaction_id', $transactionId)->first();

        $row = EbayFinanceTransaction::updateOrCreate(
            ['ebay_transaction_id' => $transactionId],
            [
                'sales_channel_id' => $salesChannel->id,
                'order_id' => $order?->id,
                'ebay_order_id' => $ebayOrderId,
                'transaction_type' => $type,
                'fee_category' => $category,
                'booking_entry' => $transaction['bookingEntry'] ?? null,
                'amount' => $transaction['amount']['value'] ?? 0,
                'total_fee_amount' => $transaction['totalFeeAmount']['value'] ?? null,
                'currency' => $transaction['amount']['currency'] ?? 'USD',
                'payout_id' => $transaction['payoutId'] ?? null,
                'transaction_date' => $transaction['transactionDate'] ?? now(),
                'raw_payload' => $transaction,
            ]
        );

        return [$row, $existing === null];
    }

    protected function classify(string $transactionType, array $transaction): string
    {
        if ($transactionType === 'SALE') {
            return 'sale';
        }

        if ($transactionType === 'SHIPPING_LABEL') {
            return 'shipping_label';
        }

        if ($transactionType === 'NON_SALE_CHARGE' && ($transaction['feeType'] ?? null) === 'AD_FEE') {
            return 'ad_fee';
        }

        return 'other';
    }

    protected function extractOrderIdFromReferences(array $transaction): ?string
    {
        foreach ($transaction['references'] ?? [] as $reference) {
            if (($reference['referenceType'] ?? null) === 'ORDER_ID') {
                return $reference['referenceId'] ?? null;
            }
        }

        return null;
    }

    /**
     * Net earnings is computed as the signed sum of every transaction for
     * the order (CREDIT = +amount, DEBIT = -amount) rather than
     * sale-minus-fee-buckets, because not every transaction type is a
     * "fee" — e.g. CREDIT (goodwill credit) adds to the seller's balance
     * and must not be subtracted like SHIPPING_LABEL/ad fee/refund rows.
     */
    protected function recomputeOrderSummary(int $orderId): void
    {
        $order = Order::find($orderId);

        if (!$order) {
            return;
        }

        $transactions = EbayFinanceTransaction::where('order_id', $orderId)
            ->get(['fee_category', 'booking_entry', 'amount', 'total_fee_amount']);

        $netEarnings = 0.0;
        $transactionFee = 0.0;
        $shippingLabelCost = 0.0;
        $adFee = 0.0;
        $otherFees = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            $signedAmount = $transaction->booking_entry === 'CREDIT' ? $amount : -$amount;
            $netEarnings += $signedAmount;

            switch ($transaction->fee_category) {
                case 'sale':
                    $transactionFee += (float) ($transaction->total_fee_amount ?? 0);
                    break;
                case 'shipping_label':
                    $shippingLabelCost += $amount;
                    break;
                case 'ad_fee':
                    $adFee += $amount;
                    break;
                default:
                    // Positive = net cost to seller (refunds, disputes); negative = net credit.
                    $otherFees += -$signedAmount;
            }
        }

        $order->update([
            'ebay_transaction_fee' => $transactionFee,
            'ebay_shipping_label_cost' => $shippingLabelCost,
            'ebay_ad_fee' => $adFee,
            'ebay_other_fees' => $otherFees,
            'ebay_net_earnings' => $netEarnings,
            'ebay_financials_synced_at' => now(),
        ]);
    }
}
