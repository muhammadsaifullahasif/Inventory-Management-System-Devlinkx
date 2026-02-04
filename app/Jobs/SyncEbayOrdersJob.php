<?php

namespace App\Jobs;

use Exception;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncEbayOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0';

    public $tries = 3;
    public $timeout = 600; // 10 minutes

    protected string $salesChannelId;
    protected int $daysBack;

    public function __construct(string $salesChannelId, int $daysBack = 30)
    {
        $this->salesChannelId = $salesChannelId;
        $this->daysBack = $daysBack;
    }

    public function handle(): void
    {
        Log::info('Starting eBay order sync job', [
            'sales_channel_id' => $this->salesChannelId,
            'days_back' => $this->daysBack,
        ]);

        try {
            $salesChannel = $this->getSalesChannelWithValidToken();

            $createTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$this->daysBack} days"));
            $createTimeTo = gmdate('Y-m-d\TH:i:s\Z');

            $allOrders = [];
            $page = 1;
            $perPage = 100;

            // Fetch all orders with pagination
            do {
                $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                    <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                        <WarningLevel>High</WarningLevel>
                        <DetailLevel>ReturnAll</DetailLevel>
                        <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
                        <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
                        <OrderRole>Seller</OrderRole>
                        <OrderStatus>All</OrderStatus>
                        <Pagination>
                            <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                            <PageNumber>' . $page . '</PageNumber>
                        </Pagination>
                    </GetOrdersRequest>';

                $response = $this->callTradingApi($salesChannel, 'GetOrders', $xmlRequest);
                $result = $this->parseOrdersResponse($response);

                $allOrders = array_merge($allOrders, $result['orders']);
                $totalPages = $result['pagination']['totalPages'];

                Log::info('Fetched eBay orders page', [
                    'page' => $page,
                    'total_pages' => $totalPages,
                    'orders_on_page' => count($result['orders']),
                ]);

                $page++;
            } while ($page <= $totalPages);

            // Process orders
            $syncedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;

            foreach ($allOrders as $ebayOrder) {
                try {
                    $result = $this->processEbayOrder($ebayOrder);
                    if ($result === 'created') {
                        $syncedCount++;
                    } elseif ($result === 'updated') {
                        $updatedCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process eBay order in job', [
                        'order_id' => $ebayOrder['order_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('eBay order sync job completed', [
                'total_orders' => count($allOrders),
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);

        } catch (Exception $e) {
            Log::error('eBay order sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function getSalesChannelWithValidToken(): SalesChannel
    {
        $salesChannel = SalesChannel::findOrFail($this->salesChannelId);

        if ($this->isAccessTokenExpired($salesChannel)) {
            $salesChannel = $this->refreshAccessToken($salesChannel);
        }

        return $salesChannel;
    }

    private function isAccessTokenExpired(SalesChannel $salesChannel): bool
    {
        if (empty($salesChannel->access_token) || empty($salesChannel->access_token_expires_at)) {
            return true;
        }
        return now()->addMinutes(5)->greaterThanOrEqualTo($salesChannel->access_token_expires_at);
    }

    private function refreshAccessToken(SalesChannel $salesChannel): SalesChannel
    {
        if (empty($salesChannel->refresh_token)) {
            throw new Exception('No refresh token available.');
        }

        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
            ])
            ->asForm()
            ->post(self::EBAY_TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $salesChannel->refresh_token,
                'scope' => $salesChannel->user_scopes,
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to refresh token: ' . $response->body());
        }

        $tokenData = $response->json();
        $salesChannel->access_token = $tokenData['access_token'];
        $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

        if (isset($tokenData['refresh_token'])) {
            $salesChannel->refresh_token = $tokenData['refresh_token'];
        }

        $salesChannel->save();
        return $salesChannel;
    }

    private function callTradingApi(SalesChannel $salesChannel, string $callName, string $xmlRequest): string
    {
        $response = Http::timeout(300)
            ->connectTimeout(60)
            ->withHeaders([
                'X-EBAY-API-SITEID' => self::API_SITE_ID,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => self::API_COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME' => $callName,
                'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                'Content-Type' => 'text/xml',
            ])
            ->withBody($xmlRequest, 'text/xml')
            ->post(self::EBAY_API_URL);

        if ($response->failed()) {
            throw new Exception("eBay {$callName} failed: " . $response->body());
        }

        return $response->body();
    }

    private function parseOrdersResponse(string $xmlResponse): array
    {
        $xml = simplexml_load_string($xmlResponse);

        if ($xml === false || (string) $xml->Ack === 'Failure') {
            $error = (string) ($xml->Errors->ShortMessage ?? 'Unknown error');
            throw new Exception("eBay API Error: {$error}");
        }

        $result = [
            'orders' => [],
            'pagination' => [
                'totalEntries' => (int) ($xml->PaginationResult->TotalNumberOfEntries ?? 0),
                'totalPages' => (int) ($xml->PaginationResult->TotalNumberOfPages ?? 0),
                'pageNumber' => (int) ($xml->PageNumber ?? 1),
            ],
        ];

        if (isset($xml->OrderArray->Order)) {
            foreach ($xml->OrderArray->Order as $order) {
                $result['orders'][] = $this->parseOrder($order);
            }
        }

        return $result;
    }

    private function parseOrder($order): array
    {
        $buyer = [
            'username' => (string) ($order->BuyerUserID ?? ''),
            'email' => (string) ($order->TransactionArray->Transaction[0]->Buyer->Email ?? ''),
        ];

        $shippingAddress = [];
        if (isset($order->ShippingAddress)) {
            $addr = $order->ShippingAddress;
            $shippingAddress = [
                'name' => (string) ($addr->Name ?? ''),
                'street1' => (string) ($addr->Street1 ?? ''),
                'street2' => (string) ($addr->Street2 ?? ''),
                'city' => (string) ($addr->CityName ?? ''),
                'state' => (string) ($addr->StateOrProvince ?? ''),
                'postal_code' => (string) ($addr->PostalCode ?? ''),
                'country' => (string) ($addr->Country ?? ''),
                'phone' => (string) ($addr->Phone ?? ''),
            ];
        }

        $lineItems = [];
        if (isset($order->TransactionArray->Transaction)) {
            foreach ($order->TransactionArray->Transaction as $transaction) {
                $item = $transaction->Item;
                $lineItems[] = [
                    'item_id' => (string) ($item->ItemID ?? ''),
                    'transaction_id' => (string) ($transaction->TransactionID ?? ''),
                    'line_item_id' => (string) ($transaction->OrderLineItemID ?? ''),
                    'sku' => (string) ($item->SKU ?? $transaction->Variation->SKU ?? ''),
                    'title' => (string) ($item->Title ?? ''),
                    'quantity' => (int) ($transaction->QuantityPurchased ?? 1),
                    'unit_price' => (float) ($transaction->TransactionPrice ?? 0),
                    'variation_attributes' => $this->parseVariationAttributes($transaction),
                ];
            }
        }

        return [
            'order_id' => (string) ($order->OrderID ?? ''),
            'order_status' => (string) ($order->OrderStatus ?? ''),
            'payment_status' => (string) ($order->CheckoutStatus->eBayPaymentStatus ?? ''),
            'buyer' => $buyer,
            'shipping_address' => $shippingAddress,
            'line_items' => $lineItems,
            'subtotal' => (float) ($order->Subtotal ?? 0),
            'shipping_cost' => (float) ($order->ShippingServiceSelected->ShippingServiceCost ?? 0),
            'total' => (float) ($order->Total ?? 0),
            'currency' => (string) ($order->Total['currencyID'] ?? 'USD'),
            'created_time' => (string) ($order->CreatedTime ?? ''),
            'paid_time' => (string) ($order->PaidTime ?? ''),
            'shipped_time' => (string) ($order->ShippedTime ?? ''),
            'raw_data' => json_decode(json_encode($order), true),
        ];
    }

    private function parseVariationAttributes($transaction): ?array
    {
        if (!isset($transaction->Variation->VariationSpecifics->NameValueList)) {
            return null;
        }

        $attributes = [];
        foreach ($transaction->Variation->VariationSpecifics->NameValueList as $spec) {
            $attributes[(string) $spec->Name] = (string) ($spec->Value ?? '');
        }

        return $attributes;
    }

    private function processEbayOrder(array $ebayOrder): string
    {
        $existingOrder = Order::where('ebay_order_id', $ebayOrder['order_id'])->first();

        if ($existingOrder) {
            $updateData = [
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'order_status' => $this->mapEbayOrderStatus($ebayOrder['order_status'], $ebayOrder),
                'payment_status' => $this->mapEbayPaymentStatus($ebayOrder['payment_status']),
                'fulfillment_status' => $this->mapEbayFulfillmentStatus($ebayOrder),
            ];

            // Update shipped_at if the order has been shipped
            if (!empty($ebayOrder['shipped_time']) && empty($existingOrder->shipped_at)) {
                $updateData['shipped_at'] = new \DateTime($ebayOrder['shipped_time']);
            }

            // Update tracking info if available
            if (!empty($ebayOrder['tracking_number']) && empty($existingOrder->tracking_number)) {
                $updateData['tracking_number'] = $ebayOrder['tracking_number'];
            }
            if (!empty($ebayOrder['shipping_carrier']) && empty($existingOrder->shipping_carrier)) {
                $updateData['shipping_carrier'] = $ebayOrder['shipping_carrier'];
            }

            $existingOrder->update($updateData);

            // Update inventory for items that weren't updated yet
            if ($this->mapEbayPaymentStatus($ebayOrder['payment_status']) === 'paid') {
                foreach ($existingOrder->items as $item) {
                    if (!$item->inventory_updated) {
                        $item->updateInventory();
                    }
                }
            }

            return 'updated';
        }

        DB::beginTransaction();
        try {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'sales_channel_id' => $this->salesChannelId,
                'ebay_order_id' => $ebayOrder['order_id'],
                'buyer_username' => $ebayOrder['buyer']['username'],
                'buyer_email' => $ebayOrder['buyer']['email'],
                'buyer_name' => $ebayOrder['shipping_address']['name'] ?? null,
                'buyer_phone' => $ebayOrder['shipping_address']['phone'] ?? null,
                'shipping_name' => $ebayOrder['shipping_address']['name'] ?? null,
                'shipping_address_line1' => $ebayOrder['shipping_address']['street1'] ?? null,
                'shipping_address_line2' => $ebayOrder['shipping_address']['street2'] ?? null,
                'shipping_city' => $ebayOrder['shipping_address']['city'] ?? null,
                'shipping_state' => $ebayOrder['shipping_address']['state'] ?? null,
                'shipping_postal_code' => $ebayOrder['shipping_address']['postal_code'] ?? null,
                'shipping_country' => $ebayOrder['shipping_address']['country'] ?? null,
                'subtotal' => $ebayOrder['subtotal'],
                'shipping_cost' => $ebayOrder['shipping_cost'],
                'total' => $ebayOrder['total'],
                'currency' => $ebayOrder['currency'],
                'order_status' => $this->mapEbayOrderStatus($ebayOrder['order_status'], $ebayOrder),
                'payment_status' => $this->mapEbayPaymentStatus($ebayOrder['payment_status']),
                'fulfillment_status' => $this->mapEbayFulfillmentStatus($ebayOrder),
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'ebay_raw_data' => $ebayOrder['raw_data'],
                'order_date' => !empty($ebayOrder['created_time']) ? new \DateTime($ebayOrder['created_time']) : now(),
                'paid_at' => !empty($ebayOrder['paid_time']) ? new \DateTime($ebayOrder['paid_time']) : null,
                'shipped_at' => !empty($ebayOrder['shipped_time']) ? new \DateTime($ebayOrder['shipped_time']) : null,
                'tracking_number' => $ebayOrder['tracking_number'] ?? null,
                'shipping_carrier' => $ebayOrder['shipping_carrier'] ?? null,
            ]);

            foreach ($ebayOrder['line_items'] as $lineItem) {
                $product = Product::where('sku', $lineItem['item_id'])->first();

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'ebay_item_id' => $lineItem['item_id'],
                    'ebay_transaction_id' => $lineItem['transaction_id'],
                    'ebay_line_item_id' => $lineItem['line_item_id'],
                    'sku' => $lineItem['sku'] ?: $lineItem['item_id'],
                    'title' => $lineItem['title'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'total_price' => $lineItem['unit_price'] * $lineItem['quantity'],
                    'currency' => $ebayOrder['currency'],
                    'variation_attributes' => $lineItem['variation_attributes'],
                ]);

                if ($this->mapEbayPaymentStatus($ebayOrder['payment_status']) === 'paid') {
                    $orderItem->updateInventory();
                }
            }

            DB::commit();
            return 'created';

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Map eBay order status to local status
     *
     * eBay Status meanings:
     * - Active: Order is awaiting payment or fulfillment
     * - Completed: Order has been fulfilled (shipped), NOT delivered
     * - Cancelled/Inactive: Order was cancelled
     *
     * Note: eBay does not provide a "Delivered" status in the Trading API.
     * When eBay marks an order as "Completed" it means it has been shipped/fulfilled.
     * Delivery status would need to be determined through tracking info.
     */
    private function mapEbayOrderStatus(string $ebayStatus, array $ebayOrder = []): string
    {
        // Check for cancellation status first
        $cancelStatus = strtolower($ebayOrder['cancel_status'] ?? '');
        if (!empty($cancelStatus) && $cancelStatus !== 'none') {
            if (in_array($cancelStatus, ['cancelled', 'cancelcomplete', 'cancelcompleted'])) {
                return 'cancelled';
            }
            if (in_array($cancelStatus, ['cancelrequested', 'cancelrequest', 'cancelpending'])) {
                return 'cancellation_requested';
            }
        }

        // Check if shipped_time is set - this indicates the order has been shipped
        if (!empty($ebayOrder['shipped_time'])) {
            return 'shipped';
        }

        // Map based on eBay order status
        // Note: "Completed" in eBay means shipped/fulfilled, NOT delivered
        return match (strtolower($ebayStatus)) {
            'active' => 'processing',
            'completed' => 'shipped',  // Completed = shipped/fulfilled, not delivered
            'cancelled' => 'cancelled',
            'inactive' => 'cancelled',
            'shipped' => 'shipped',
            default => 'pending',
        };
    }

    private function mapEbayPaymentStatus(string $ebayStatus): string
    {
        return match (strtolower($ebayStatus)) {
            'nopaymentfailure', 'paymentcomplete', 'paid' => 'paid',
            'paymentpending', 'pending' => 'pending',
            'refunded' => 'refunded',
            'paymentfailed', 'failed' => 'failed',
            default => 'pending',
        };
    }

    private function mapEbayFulfillmentStatus(array $ebayOrder): string
    {
        // Check if shipped
        if (!empty($ebayOrder['shipped_time'])) {
            return 'fulfilled';
        }

        // Check for pickup status
        $pickupStatus = strtolower($ebayOrder['pickup_status'] ?? '');
        if ($pickupStatus === 'readyforpickup') {
            return 'ready_for_pickup';
        }
        if ($pickupStatus === 'pickedup') {
            return 'fulfilled';
        }

        return 'unfulfilled';
    }

    public function failed(Exception $exception): void
    {
        Log::error('eBay order sync job failed completely', [
            'sales_channel_id' => $this->salesChannelId,
            'error' => $exception->getMessage(),
        ]);
    }
}
