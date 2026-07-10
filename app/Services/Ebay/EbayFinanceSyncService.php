<?php

namespace App\Services\Ebay;

use App\Models\EbayFinanceTransaction;
use App\Models\Order;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
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
 * - transactionType=SHIPPING_LABEL: usually DEBIT (label cost), but CREDIT
 *   occurs for label voids/refunds. Has top-level `orderId`.
 * - transactionType=NON_SALE_CHARGE with feeType=AD_FEE: Promoted Listings
 *   charge, usually DEBIT but CREDIT occurs for fee reversals. No top-level
 *   `orderId` — order is linked via `references[]` where
 *   referenceType=ORDER_ID.
 * - transactionType=NON_SALE_CHARGE with other feeTypes (e.g.
 *   FINAL_VALUE_FEE_FIXED_PER_ORDER): a marketplace fee billed outside the
 *   SALE transaction. Also linked via `references[]`.
 * - Every DEBIT/CREDIT bucket above is sign-aware: CREDIT reverses a prior
 *   charge and must subtract from the cost total, not add to it.
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

        if ($transactionType === 'NON_SALE_CHARGE') {
            if (($transaction['feeType'] ?? null) === 'AD_FEE') {
                return 'ad_fee';
            }

            // e.g. FINAL_VALUE_FEE_FIXED_PER_ORDER billed outside the SALE
            // transaction — still a marketplace fee, not a generic "other".
            return 'marketplace_fee_adjustment';
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

            // Cost convention: DEBIT = seller charged (positive cost), CREDIT =
            // fee reversed/refunded (negative cost). Both directions occur for
            // SHIPPING_LABEL and NON_SALE_CHARGE (e.g. ad fee reversals, label
            // voids) so the sign must not be dropped.
            $cost = -$signedAmount;

            switch ($transaction->fee_category) {
                case 'sale':
                    $transactionFee += (float) ($transaction->total_fee_amount ?? 0);
                    break;
                case 'shipping_label':
                    $shippingLabelCost += $cost;
                    break;
                case 'ad_fee':
                    $adFee += $cost;
                    break;
                case 'marketplace_fee_adjustment':
                    $transactionFee += $cost;
                    break;
                default:
                    // Positive = net cost to seller (refunds, disputes); negative = net credit.
                    $otherFees += $cost;
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

    /**
     * Known eBay fee-type labels for the itemized earnings breakdown.
     * Anything not listed here still displays via humanizeFeeType() rather
     * than being dropped — eBay's fee-type list is broader than what's
     * been observed in this account's data so far.
     */
    protected const FEE_TYPE_LABELS = [
        'FINAL_VALUE_FEE' => 'Final Value Fee (variable)',
        'FINAL_VALUE_FEE_FIXED_PER_ORDER' => 'Final Value Fee (fixed)',
        'HIGH_ITEM_NOT_AS_DESCRIBED_FEE' => 'Very High "Item Not As Described" Fee',
        'INTERNATIONAL_FEE' => 'International Fee',
        'BELOW_STANDARD_FEE' => 'Below Standard Performance Fee',
        'BELOW_STANDARD_SHIPPING_FEE' => 'Below Standard Performance Fee (Shipping)',
        'DEPOSIT_PROCESSING_FEE' => 'Deposit Processing Fee',
        'REGULATORY_OPERATING_FEE' => 'Regulatory Operating Fee',
        'CHARITY_DONATION' => 'Charity Donation',
        'PAYMENT_DISPUTE_FEE' => 'Payment Dispute Fee',
        'OTHER_FEES' => 'Other Fees',
    ];

    /**
     * Build the itemized per-order earnings breakdown (mirrors eBay's own
     * Order Details earnings page) from already-synced EbayFinanceTransaction
     * rows. Pure read — no API calls, no writes. Item price/subtotal/
     * shipping/tax/discount are NOT included here since they already live
     * on `orders`/`order_items` from the Trading API sync.
     *
     * Terminology matches eBay's Seller Hub, confirmed against a live order
     * (09-14822-51712, 2026-07-10): "Expenses" is the combined total of
     * marketplace fees + shipping labels + other charges (8.78 + 7.09 =
     * 15.87), NOT shipping/ad-fee alone. "Order earnings" is what eBay
     * actually pays out (Gross − Expenses − Refunds). "Your cost" and "Net
     * order earning" (= Order earnings − Your cost) are this app's own
     * addition — eBay has no concept of product cost.
     *
     * @return array{
     *   ebay_collected_tax: float,
     *   gross_amount: float,
     *   marketplace_fees: array<string, array{label: string, amount: float}>,
     *   shipping_labels: array{debit: float, credit: float, net: float},
     *   other_charges: array<string, array{label: string, debit: float, credit: float, net: float}>,
     *   expenses_total: float,
     *   refunds: float,
     *   refund_fee_credit: float,
     *   adjustments: float,
     *   order_earnings: float,
     *   your_cost: float,
     *   net_order_earning: float,
     * }
     */
    public function buildEarningsBreakdown(Order $order): array
    {
        $transactions = EbayFinanceTransaction::where('order_id', $order->id)->get();

        $ebayCollectedTax = 0.0;
        $grossAmount = 0.0;
        $marketplaceFees = []; // feeType => amount, net of any refund reversal
        $shippingLabelsDebit = 0.0;
        $shippingLabelsCredit = 0.0;
        $otherCharges = []; // feeType => ['debit' => .., 'credit' => ..], for NON_SALE_CHARGE items (ad fee, charity, dispute fee, other)
        $refunds = 0.0;
        $refundFeeCredit = 0.0; // FVF eBay credits back to the seller on a refund — buyer's total refund is $refunds + $refundFeeCredit
        $adjustments = 0.0; // CREDIT / DISPUTE / anything unclassified, signed

        foreach ($transactions as $transaction) {
            $payload = $transaction->raw_payload;
            $amount = (float) $transaction->amount;
            $isCredit = $transaction->booking_entry === 'CREDIT';

            switch ($transaction->transaction_type) {
                case 'SALE':
                    $ebayCollectedTax += (float) ($payload['ebayCollectedTaxAmount']['value'] ?? 0);
                    $grossAmount += (float) ($payload['totalFeeBasisAmount']['value'] ?? 0);
                    $this->accumulateMarketplaceFees($marketplaceFees, $payload, 1);
                    break;

                case 'REFUND':
                    $refunds += $amount;
                    // amount is only what leaves the seller's payout. eBay also
                    // credits back the FVF portion of the buyer's refund
                    // (totalFeeAmount) — track it separately so it's visible,
                    // in addition to netting it out of marketplace_fees below.
                    $refundFeeCredit += (float) ($transaction->total_fee_amount ?? 0);
                    $this->accumulateMarketplaceFees($marketplaceFees, $payload, -1);
                    break;

                case 'SHIPPING_LABEL':
                    // Usually DEBIT (label cost), but CREDIT occurs for label
                    // voids/refunds — track both legs, not just the net.
                    $isCredit ? $shippingLabelsCredit += $amount : $shippingLabelsDebit += $amount;
                    break;

                case 'NON_SALE_CHARGE':
                    $feeType = $payload['feeType'] ?? 'OTHER_FEES';
                    $otherCharges[$feeType] ??= ['debit' => 0.0, 'credit' => 0.0];
                    // Usually DEBIT (fee charge), but CREDIT occurs for
                    // reversals (e.g. ad fee refunded) — track both legs.
                    $isCredit ? $otherCharges[$feeType]['credit'] += $amount : $otherCharges[$feeType]['debit'] += $amount;
                    break;

                default:
                    // CREDIT, DISPUTE, and anything not yet observed.
                    $adjustments += $isCredit ? $amount : -$amount;
            }
        }

        $shippingLabelsNet = $shippingLabelsDebit - $shippingLabelsCredit;
        $otherChargesNet = array_sum(array_map(fn (array $b) => $b['debit'] - $b['credit'], $otherCharges));

        $expensesTotal = array_sum($marketplaceFees) + $shippingLabelsNet + $otherChargesNet;
        $orderEarnings = $grossAmount - $expensesTotal - $refunds + $adjustments;
        $yourCost = (float) $order->items()->sum(DB::raw('cost_at_sale * quantity'));

        return [
            'ebay_collected_tax' => $ebayCollectedTax,
            'gross_amount' => $grossAmount,
            'marketplace_fees' => $this->labelBucket($marketplaceFees),
            'shipping_labels' => [
                'debit' => $shippingLabelsDebit,
                'credit' => $shippingLabelsCredit,
                'net' => $shippingLabelsNet,
            ],
            'other_charges' => $this->labelBucketWithDetail($otherCharges),
            'expenses_total' => $expensesTotal,
            'refunds' => $refunds,
            'refund_fee_credit' => $refundFeeCredit,
            'adjustments' => $adjustments,
            'order_earnings' => $orderEarnings,
            'your_cost' => $yourCost,
            'net_order_earning' => $orderEarnings - $yourCost,
        ];
    }

    protected function accumulateMarketplaceFees(array &$bucket, array $payload, int $sign): void
    {
        foreach ($payload['orderLineItems'] ?? [] as $lineItem) {
            foreach ($lineItem['marketplaceFees'] ?? [] as $fee) {
                $feeType = $fee['feeType'] ?? 'OTHER_FEES';
                $bucket[$feeType] = ($bucket[$feeType] ?? 0) + $sign * (float) ($fee['amount']['value'] ?? 0);
            }
        }
    }

    /**
     * @param array<string, float> $bucket
     * @return array<string, array{label: string, amount: float}>
     */
    protected function labelBucket(array $bucket): array
    {
        $labeled = [];

        foreach ($bucket as $feeType => $amount) {
            $labeled[$feeType] = [
                'label' => self::FEE_TYPE_LABELS[$feeType] ?? $this->humanizeFeeType($feeType),
                'amount' => $amount,
            ];
        }

        return $labeled;
    }

    /**
     * @param array<string, array{debit: float, credit: float}> $bucket
     * @return array<string, array{label: string, debit: float, credit: float, net: float}>
     */
    protected function labelBucketWithDetail(array $bucket): array
    {
        $labeled = [];

        foreach ($bucket as $feeType => $amounts) {
            $labeled[$feeType] = [
                'label' => self::FEE_TYPE_LABELS[$feeType] ?? $this->humanizeFeeType($feeType),
                'debit' => $amounts['debit'],
                'credit' => $amounts['credit'],
                'net' => $amounts['debit'] - $amounts['credit'],
            ];
        }

        return $labeled;
    }

    protected function humanizeFeeType(string $feeType): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $feeType)));
    }
}
