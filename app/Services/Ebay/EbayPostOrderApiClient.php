<?php

namespace App\Services\Ebay;

use Exception;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay Post-Order API Client for returns, cancellations, and inquiries.
 *
 * The Post-Order API is a RESTful API (unlike the Trading API which is XML-based).
 * It handles:
 * - Return requests (GET, respond to, close)
 * - Cancellation requests (GET, approve, reject)
 * - Inquiry/INR cases
 * - Issue refunds
 *
 * API Documentation: https://developer.ebay.com/api-docs/sell/fulfillment/overview.html
 *
 * Usage:
 *   $client = app(EbayPostOrderApiClient::class);
 *   $channel = app(EbayApiClient::class)->ensureValidToken($salesChannel);
 *   $returns = $client->getReturns($channel);
 */
class EbayPostOrderApiClient
{
    // Post-Order API base URLs
    private const POST_ORDER_API_URL = 'https://api.ebay.com/post-order/v2';
    private const FULFILLMENT_API_URL = 'https://api.ebay.com/sell/fulfillment/v1';

    // Request timeout in seconds
    private const REQUEST_TIMEOUT = 60;

    public function __construct(
        private EbayApiClient $tradingApiClient,
    ) {}

    // =========================================
    // CANCELLATIONS
    // =========================================

    /**
     * Get cancellation requests for a channel.
     * Uses the Sell Fulfillment API to search for orders with cancellation requests.
     */
    public function getCancellations(SalesChannel $channel, int $limit = 50, int $offset = 0): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->get(self::FULFILLMENT_API_URL . '/order', [
                    'filter' => 'orderfulfillmentstatus:{NOT_STARTED|IN_PROGRESS}',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to get cancellations', [
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to fetch cancellations',
                    'cancellations' => [],
                ];
            }

            // Filter orders with cancellation requests
            $cancellations = [];
            foreach ($data['orders'] ?? [] as $order) {
                if (!empty($order['cancelStatus']['cancelState']) &&
                    $order['cancelStatus']['cancelState'] !== 'NONE_REQUESTED') {
                    $cancellations[] = $this->parseCancellation($order);
                }
            }

            return [
                'success' => true,
                'total' => count($cancellations),
                'cancellations' => $cancellations,
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Get cancellations exception', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'cancellations' => [],
            ];
        }
    }

    /**
     * Approve a cancellation request.
     */
    public function approveCancellation(SalesChannel $channel, string $orderId): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/cancellation/{$orderId}/approve");

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to approve cancellation', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to approve cancellation',
                ];
            }

            Log::channel('ebay')->info('Cancellation approved', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => true,
                'message' => 'Cancellation approved successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Approve cancellation exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reject a cancellation request.
     */
    public function rejectCancellation(SalesChannel $channel, string $orderId, string $reason = ''): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [];
            if (!empty($reason)) {
                $body['shipmentDate'] = gmdate('Y-m-d\TH:i:s\Z');
                $body['trackingNumber'] = '';
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/cancellation/{$orderId}/reject", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to reject cancellation', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to reject cancellation',
                ];
            }

            Log::channel('ebay')->info('Cancellation rejected', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => true,
                'message' => 'Cancellation rejected successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Reject cancellation exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a seller-initiated cancellation.
     */
    public function createCancellation(
        SalesChannel $channel,
        string $legacyOrderId,
        string $reason = 'OUT_OF_STOCK',
        ?string $buyerNote = null
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'legacyOrderId' => $legacyOrderId,
                'cancelReason' => $reason,
            ];

            if ($buyerNote) {
                $body['buyerPaidDate'] = gmdate('Y-m-d\TH:i:s\Z');
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . '/cancellation', $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to create cancellation', [
                    'order_id' => $legacyOrderId,
                    'reason' => $reason,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to create cancellation',
                ];
            }

            Log::channel('ebay')->info('Cancellation created', [
                'order_id' => $legacyOrderId,
                'cancellation_id' => $data['cancellationId'] ?? '',
            ]);

            return [
                'success' => true,
                'cancellation_id' => $data['cancellationId'] ?? '',
                'message' => 'Cancellation request created successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Create cancellation exception', [
                'order_id' => $legacyOrderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // RETURNS
    // =========================================

    /**
     * Get return requests for a channel.
     */
    public function getReturns(
        SalesChannel $channel,
        int $limit = 50,
        int $offset = 0,
        ?string $returnState = null
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            if ($returnState) {
                $params['return_state'] = $returnState;
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->get(self::POST_ORDER_API_URL . '/return/search', $params);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to get returns', [
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to fetch returns',
                    'returns' => [],
                ];
            }

            $returns = [];
            foreach ($data['members'] ?? [] as $returnCase) {
                $returns[] = $this->parseReturn($returnCase);
            }

            return [
                'success' => true,
                'total' => $data['total'] ?? count($returns),
                'returns' => $returns,
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Get returns exception', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'returns' => [],
            ];
        }
    }

    /**
     * Get a single return request details.
     */
    public function getReturn(SalesChannel $channel, string $returnId): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->get(self::POST_ORDER_API_URL . "/return/{$returnId}");

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to get return details', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to fetch return details',
                ];
            }

            return [
                'success' => true,
                'return' => $this->parseReturn($data),
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Get return exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Approve a return request (accept the return).
     */
    public function approveReturn(SalesChannel $channel, string $returnId, ?string $comments = null): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [];
            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/decide", array_merge($body, [
                    'decision' => 'ACCEPT',
                ]));

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to approve return', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to approve return',
                ];
            }

            Log::channel('ebay')->info('Return approved', [
                'return_id' => $returnId,
            ]);

            return [
                'success' => true,
                'message' => 'Return approved successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Approve return exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Decline a return request.
     */
    public function declineReturn(SalesChannel $channel, string $returnId, string $reason, ?string $comments = null): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'decision' => 'DECLINE',
                'reason' => $reason,
            ];

            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/decide", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to decline return', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to decline return',
                ];
            }

            Log::channel('ebay')->info('Return declined', [
                'return_id' => $returnId,
            ]);

            return [
                'success' => true,
                'message' => 'Return declined successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Decline return exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Provide a return shipping label to the buyer.
     */
    public function provideReturnShippingLabel(
        SalesChannel $channel,
        string $returnId,
        string $trackingNumber,
        string $shippingCarrier,
        ?string $labelUrl = null
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'trackingNumber' => $trackingNumber,
                'shippingCarrier' => $shippingCarrier,
            ];

            if ($labelUrl) {
                $body['returnLabelImageUrl'] = $labelUrl;
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/provide_shipping_label", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to provide return shipping label', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to provide shipping label',
                ];
            }

            Log::channel('ebay')->info('Return shipping label provided', [
                'return_id' => $returnId,
                'tracking_number' => $trackingNumber,
            ]);

            return [
                'success' => true,
                'message' => 'Shipping label provided successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Provide return shipping label exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark a return item as received.
     */
    public function markReturnReceived(SalesChannel $channel, string $returnId, ?string $comments = null): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [];
            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/mark_as_received", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to mark return as received', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to mark return as received',
                ];
            }

            Log::channel('ebay')->info('Return marked as received', [
                'return_id' => $returnId,
            ]);

            return [
                'success' => true,
                'message' => 'Return marked as received successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Mark return received exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Close a return case.
     */
    public function closeReturn(SalesChannel $channel, string $returnId, string $closeReason, ?string $comments = null): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'closeReason' => $closeReason,
            ];

            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/close", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to close return', [
                    'return_id' => $returnId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to close return',
                ];
            }

            Log::channel('ebay')->info('Return closed', [
                'return_id' => $returnId,
                'close_reason' => $closeReason,
            ]);

            return [
                'success' => true,
                'message' => 'Return closed successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Close return exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // REFUNDS
    // =========================================

    /**
     * Issue a refund for an order.
     * Uses the Fulfillment API for refunds.
     *
     * @param SalesChannel $channel
     * @param string $orderId The eBay order ID
     * @param float $amount Total refund amount
     * @param string $reasonForRefund Refund reason code
     * @param string|null $comment Optional comment for the refund
     * @param string $currency Currency code (default: USD)
     */
    public function issueRefund(
        SalesChannel $channel,
        string $orderId,
        float $amount,
        string $reasonForRefund = 'BUYER_CANCEL',
        ?string $comment = null,
        string $currency = 'USD'
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'reasonForRefund' => $reasonForRefund,
                'refundItems' => [
                    [
                        'refundAmount' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency' => $currency,
                        ],
                    ],
                ],
            ];

            if ($comment) {
                $body['comment'] = $comment;
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::FULFILLMENT_API_URL . "/order/{$orderId}/issue_refund", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to issue refund', [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to issue refund',
                ];
            }

            Log::channel('ebay')->info('Refund issued', [
                'order_id' => $orderId,
                'amount' => $amount,
                'refund_id' => $data['refundId'] ?? '',
            ]);

            return [
                'success' => true,
                'refund_id' => $data['refundId'] ?? '',
                'refund_status' => $data['refundStatus'] ?? '',
                'message' => 'Refund issued successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Issue refund exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Issue a partial refund for specific line items.
     * Uses the Fulfillment API for line-item level refunds.
     *
     * @param SalesChannel $channel
     * @param string $orderId The eBay order ID
     * @param array $lineItems Array of line items to refund:
     *   [
     *     ['line_item_id' => 'xxx', 'amount' => 10.00, 'quantity' => 1],
     *     ...
     *   ]
     * @param string $reasonForRefund Refund reason code
     * @param string|null $comment Optional comment
     * @param string $currency Currency code
     */
    public function issuePartialRefund(
        SalesChannel $channel,
        string $orderId,
        array $lineItems,
        string $reasonForRefund = 'BUYER_CANCEL',
        ?string $comment = null,
        string $currency = 'USD'
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $refundItems = [];
            $totalAmount = 0;

            foreach ($lineItems as $item) {
                $refundItem = [
                    'refundAmount' => [
                        'value' => number_format($item['amount'], 2, '.', ''),
                        'currency' => $currency,
                    ],
                ];

                // Add line item ID if specified
                if (!empty($item['line_item_id'])) {
                    $refundItem['lineItemId'] = $item['line_item_id'];
                }

                // Add quantity if specified (for partial quantity refunds)
                if (!empty($item['quantity'])) {
                    $refundItem['quantity'] = (int) $item['quantity'];
                }

                $refundItems[] = $refundItem;
                $totalAmount += $item['amount'];
            }

            $body = [
                'reasonForRefund' => $reasonForRefund,
                'refundItems' => $refundItems,
            ];

            if ($comment) {
                $body['comment'] = $comment;
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::FULFILLMENT_API_URL . "/order/{$orderId}/issue_refund", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to issue partial refund', [
                    'order_id' => $orderId,
                    'line_items' => $lineItems,
                    'total_amount' => $totalAmount,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to issue partial refund',
                ];
            }

            Log::channel('ebay')->info('Partial refund issued', [
                'order_id' => $orderId,
                'line_items_count' => count($lineItems),
                'total_amount' => $totalAmount,
                'refund_id' => $data['refundId'] ?? '',
            ]);

            return [
                'success' => true,
                'refund_id' => $data['refundId'] ?? '',
                'refund_status' => $data['refundStatus'] ?? '',
                'total_refunded' => $totalAmount,
                'message' => 'Partial refund issued successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Issue partial refund exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Issue a refund for a return.
     */
    public function issueReturnRefund(
        SalesChannel $channel,
        string $returnId,
        float $amount,
        ?string $comments = null
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'refundDetail' => [
                    'itemizedRefundDetail' => [
                        [
                            'refundAmount' => [
                                'value' => number_format($amount, 2, '.', ''),
                                'currency' => 'USD',
                            ],
                            'refundFeeType' => 'PURCHASE_PRICE',
                        ],
                    ],
                ],
            ];

            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/return/{$returnId}/issue_refund", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to issue return refund', [
                    'return_id' => $returnId,
                    'amount' => $amount,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to issue refund',
                ];
            }

            Log::channel('ebay')->info('Return refund issued', [
                'return_id' => $returnId,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'message' => 'Return refund issued successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Issue return refund exception', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // INQUIRIES / INR (Item Not Received)
    // =========================================

    /**
     * Get inquiry cases (Item Not Received, etc.).
     */
    public function getInquiries(SalesChannel $channel, int $limit = 50, int $offset = 0): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->get(self::POST_ORDER_API_URL . '/inquiry/search', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to get inquiries', [
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to fetch inquiries',
                    'inquiries' => [],
                ];
            }

            $inquiries = [];
            foreach ($data['members'] ?? [] as $inquiry) {
                $inquiries[] = $this->parseInquiry($inquiry);
            }

            return [
                'success' => true,
                'total' => $data['total'] ?? count($inquiries),
                'inquiries' => $inquiries,
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Get inquiries exception', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'inquiries' => [],
            ];
        }
    }

    /**
     * Provide shipment info to resolve an inquiry.
     */
    public function provideInquiryShipmentInfo(
        SalesChannel $channel,
        string $inquiryId,
        string $trackingNumber,
        string $shippingCarrier,
        string $shippedDate
    ): array {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [
                'shippingCarrierName' => $shippingCarrier,
                'trackingNumber' => $trackingNumber,
                'shippedDate' => $shippedDate,
            ];

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/inquiry/{$inquiryId}/provide_shipment_info", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to provide inquiry shipment info', [
                    'inquiry_id' => $inquiryId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to provide shipment info',
                ];
            }

            Log::channel('ebay')->info('Inquiry shipment info provided', [
                'inquiry_id' => $inquiryId,
                'tracking_number' => $trackingNumber,
            ]);

            return [
                'success' => true,
                'message' => 'Shipment info provided successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Provide inquiry shipment info exception', [
                'inquiry_id' => $inquiryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Issue a refund for an inquiry (INR resolution).
     */
    public function issueInquiryRefund(SalesChannel $channel, string $inquiryId, ?string $comments = null): array
    {
        $channel = $this->tradingApiClient->ensureValidToken($channel);

        try {
            $body = [];
            if ($comments) {
                $body['comments'] = ['content' => $comments];
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeaders($this->getRestApiHeaders($channel))
                ->post(self::POST_ORDER_API_URL . "/inquiry/{$inquiryId}/issue_refund", $body);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('ebay')->error('Failed to issue inquiry refund', [
                    'inquiry_id' => $inquiryId,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => $data['errors'][0]['message'] ?? 'Failed to issue refund',
                ];
            }

            Log::channel('ebay')->info('Inquiry refund issued', [
                'inquiry_id' => $inquiryId,
            ]);

            return [
                'success' => true,
                'message' => 'Inquiry refund issued successfully',
            ];

        } catch (Exception $e) {
            Log::channel('ebay')->error('Issue inquiry refund exception', [
                'inquiry_id' => $inquiryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Get REST API headers for eBay requests.
     */
    private function getRestApiHeaders(SalesChannel $channel): array
    {
        return [
            'Authorization' => 'Bearer ' . $channel->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
        ];
    }

    /**
     * Parse a cancellation from API response.
     */
    private function parseCancellation(array $order): array
    {
        $cancelStatus = $order['cancelStatus'] ?? [];

        return [
            'order_id' => $order['orderId'] ?? '',
            'legacy_order_id' => $order['legacyOrderId'] ?? '',
            'cancel_state' => $cancelStatus['cancelState'] ?? '',
            'cancel_requests' => array_map(function ($req) {
                return [
                    'cancel_request_id' => $req['cancelRequestId'] ?? '',
                    'cancel_reason' => $req['cancelReason'] ?? '',
                    'cancel_request_state' => $req['cancelRequestState'] ?? '',
                    'requested_date' => $req['requestedDate'] ?? '',
                    'cancel_initiated_by' => $req['cancelInitiator'] ?? '',
                ];
            }, $cancelStatus['cancelRequests'] ?? []),
            'buyer_username' => $order['buyer']['username'] ?? '',
            'order_total' => $order['pricingSummary']['total']['value'] ?? 0,
            'currency' => $order['pricingSummary']['total']['currency'] ?? 'USD',
            'created_date' => $order['creationDate'] ?? '',
        ];
    }

    /**
     * Parse a return case from API response.
     */
    private function parseReturn(array $returnCase): array
    {
        return [
            'return_id' => $returnCase['returnId'] ?? '',
            'order_id' => $returnCase['orderId'] ?? '',
            'legacy_order_id' => $returnCase['legacyOrderId'] ?? '',
            'state' => $returnCase['state'] ?? $returnCase['currentType'] ?? '',
            'status' => $returnCase['status'] ?? '',
            'return_reason' => $returnCase['returnReason'] ?? $returnCase['detail']['reason']['reasonType'] ?? '',
            'return_reason_description' => $returnCase['detail']['reason']['description'] ?? '',
            'buyer_comments' => $returnCase['detail']['buyerComments'] ?? '',
            'creation_date' => $returnCase['creationDate'] ?? '',
            'close_date' => $returnCase['closeDate'] ?? '',
            'item' => [
                'item_id' => $returnCase['detail']['itemDetail']['itemId'] ?? '',
                'title' => $returnCase['detail']['itemDetail']['itemTitle'] ?? '',
                'return_quantity' => $returnCase['detail']['itemDetail']['returnQuantity'] ?? 1,
            ],
            'seller_response_due_date' => $returnCase['sellerResponseDue']['respondByDate'] ?? '',
            'refund_amount' => $returnCase['refundAmount']['value'] ?? null,
            'refund_status' => $returnCase['refundStatus'] ?? '',
            'shipping_cost_paid_by' => $returnCase['returnShippingCostPayer'] ?? '',
        ];
    }

    /**
     * Parse an inquiry from API response.
     */
    private function parseInquiry(array $inquiry): array
    {
        return [
            'inquiry_id' => $inquiry['inquiryId'] ?? '',
            'order_id' => $inquiry['orderId'] ?? '',
            'legacy_order_id' => $inquiry['legacyOrderId'] ?? '',
            'state' => $inquiry['state'] ?? '',
            'status' => $inquiry['status'] ?? '',
            'inquiry_type' => $inquiry['type'] ?? '',
            'creation_date' => $inquiry['creationDate'] ?? '',
            'escalation_date' => $inquiry['escalationDate'] ?? '',
            'close_date' => $inquiry['closeDate'] ?? '',
            'item' => [
                'item_id' => $inquiry['itemId'] ?? '',
                'title' => $inquiry['itemTitle'] ?? '',
            ],
            'buyer_username' => $inquiry['buyer']['username'] ?? '',
            'seller_response_due_date' => $inquiry['sellerMakeItRightByDate'] ?? '',
        ];
    }
}
