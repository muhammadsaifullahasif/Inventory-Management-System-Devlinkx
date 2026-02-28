<?php

namespace App\Services\Ebay;

use Exception;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ShippingService;

/**
 * eBay order processing, status mapping, and notification handlers.
 *
 * Single source of truth for:
 * - Order status mapping (eBay → local)
 * - Payment status mapping (eBay → local)
 * - Fulfillment status mapping (eBay → local)
 * - Order creation/update from notifications and sync
 * - All notification event handlers
 *
 * Works with both:
 * - Array data (from EbayApiClient::call() via sync jobs)
 * - SimpleXMLElement data (from webhook notifications, converted via EbayApiClient)
 *
 * Usage:
 *   $orderService = app(EbayOrderService::class);
 *   $order = $orderService->processNotification($data, $channel, 'ItemMarkedShipped', $ts);
 */
class EbayOrderService
{
    public function __construct(
        private EbayApiClient $client,
    ) {}

    // =========================================
    // STATUS MAPPING
    // =========================================

    /**
     * Map eBay order status to local order status.
     *
     * eBay Status meanings:
     * - Active: Order is awaiting payment or checkout completion
     * - Completed: Checkout/payment is complete (does NOT mean shipped!)
     * - Cancelled/Inactive: Order was cancelled
     *
     * IMPORTANT: eBay "Completed" = payment done, NOT shipped.
     * Only mark as shipped when ShippedTime is present.
     * eBay Trading API has NO "Delivered" status — only via delivery notifications.
     *
     * Status flow:
     *   Not paid        → pending
     *   Paid, not shipped → processing
     *   Shipped          → shipped
     */
    public function mapOrderStatus(string $ebayStatus, array $ebayOrder = []): string
    {
        // Check for cancellation first
        $cancelStatus = strtolower($ebayOrder['cancel_status'] ?? '');
        if (!empty($cancelStatus) && !in_array($cancelStatus, ['none', 'notapplicable'])) {
            if (in_array($cancelStatus, ['cancelled', 'cancelcomplete', 'cancelcompleted'])) {
                return 'cancelled';
            }
            if (in_array($cancelStatus, ['cancelrequested', 'cancelrequest', 'cancelpending'])) {
                return 'cancellation_requested';
            }
        }

        // Only mark as shipped when we have actual shipping evidence
        if (!empty($ebayOrder['shipped_time'])) {
            return 'shipped';
        }

        // Map based on eBay order status
        return match (strtolower($ebayStatus)) {
            // Completed = checkout/payment done, NOT shipped
            'completed', 'complete' => 'processing',
            // Active = may or may not be paid yet
            'active' => $this->isPaymentConfirmed($ebayOrder) ? 'processing' : 'pending',
            'cancelled', 'inactive' => 'cancelled',
            default => $this->isPaymentConfirmed($ebayOrder) ? 'processing' : 'pending',
        };
    }

    /**
     * Map eBay payment status to local payment status.
     *
     * CRITICAL: 'NoPaymentFailure' does NOT mean paid!
     * It means "no payment failure occurred". Use reliable indicators only.
     *
     * Reliable payment indicators:
     * 1. PaidTime is set
     * 2. CheckoutStatus = Complete
     * 3. CompleteStatus = Complete
     * 4. eBayPaymentStatus = PaymentComplete
     */
    public function mapPaymentStatus(string $ebayStatus, array $ebayOrder = []): string
    {
        // Check refund status
        if (!empty($ebayOrder['refund_status']) && strtolower($ebayOrder['refund_status']) === 'successful') {
            return 'refunded';
        }

        // Use reliable payment indicators from order data
        if (!empty($ebayOrder)) {
            if (!empty($ebayOrder['paid_time'])) {
                return 'paid';
            }

            $checkoutStatus = strtolower($ebayOrder['checkout_status'] ?? '');
            if ($checkoutStatus === 'complete') {
                return 'paid';
            }
        }

        return match (strtolower($ebayStatus)) {
            'paymentcomplete', 'paid' => 'paid',
            'refunded' => 'refunded',
            'paymentfailed', 'failed' => 'failed',
            // NoPaymentFailure = no failure, NOT paid. Default to unpaid.
            default => 'unpaid',
        };
    }

    /**
     * Map eBay order data to local fulfillment status.
     */
    public function mapFulfillmentStatus(array $ebayOrder): string
    {
        if (!empty($ebayOrder['shipped_time'])) {
            return 'fulfilled';
        }

        $pickupStatus = strtolower($ebayOrder['pickup_status'] ?? '');
        if ($pickupStatus === 'readyforpickup') {
            return 'ready_for_pickup';
        }
        if ($pickupStatus === 'pickedup') {
            return 'fulfilled';
        }

        return 'unfulfilled';
    }

    /**
     * Map eBay order status from notification transaction data (SimpleXMLElement → array).
     * Used for notification handlers where data comes as XML converted to array.
     */
    public function mapOrderStatusFromTransaction(array $transaction): string
    {
        $cancelStatus = strtolower($transaction['ContainingOrder']['CancelStatus'] ?? '');
        if (!empty($cancelStatus) && !in_array($cancelStatus, ['none', 'notapplicable'])) {
            if (in_array($cancelStatus, ['cancelled', 'cancelcomplete', 'cancelcompleted'])) {
                return 'cancelled';
            }
            if (in_array($cancelStatus, ['cancelrequested', 'cancelrequest', 'cancelpending'])) {
                return 'cancellation_requested';
            }
        }

        // Only mark as shipped when we have actual shipping evidence
        $shippedTime = $transaction['ShippedTime'] ?? '';
        if (!empty($shippedTime)) {
            return 'shipped';
        }

        $ebayOrderStatus = strtolower($transaction['ContainingOrder']['OrderStatus'] ?? '');
        if (in_array($ebayOrderStatus, ['cancelled', 'inactive'])) {
            return 'cancelled';
        }

        // Check if payment is confirmed (Completed = paid, NOT shipped)
        $completeStatus = strtolower($transaction['Status']['CompleteStatus'] ?? '');
        $checkoutStatus = strtolower($transaction['Status']['CheckoutStatus'] ?? '');
        $paidTime = $transaction['PaidTime'] ?? '';
        $paymentStatus = strtolower($transaction['Status']['eBayPaymentStatus'] ?? '');

        $isPaid = !empty($paidTime)
            || $paymentStatus === 'paymentcomplete'
            || $checkoutStatus === 'checkoutcomplete'
            || $completeStatus === 'complete'
            || in_array($ebayOrderStatus, ['completed', 'complete']);

        return $isPaid ? 'processing' : 'pending';
    }

    /**
     * Map eBay payment status from notification transaction data.
     */
    public function mapPaymentStatusFromTransaction(array $transaction): string
    {
        $paymentStatus = strtolower($transaction['Status']['eBayPaymentStatus'] ?? '');
        $paidTime = $transaction['PaidTime'] ?? '';
        $checkoutStatus = strtolower($transaction['Status']['CheckoutStatus'] ?? '');
        $completeStatus = strtolower($transaction['Status']['CompleteStatus'] ?? '');

        // Check refund
        $refundStatus = $transaction['MonetaryDetails']['Refunds']['Refund']['RefundStatus'] ?? '';
        if (!empty($refundStatus) && strtolower($refundStatus) === 'successful') {
            return 'refunded';
        }

        if (!empty($paidTime)) {
            return 'paid';
        }
        if (in_array($paymentStatus, ['paymentcomplete', 'paid'])) {
            return 'paid';
        }
        if ($checkoutStatus === 'checkoutcomplete') {
            return 'paid';
        }
        if ($completeStatus === 'complete') {
            return 'paid';
        }
        if (in_array($paymentStatus, ['paymentfailed', 'failed'])) {
            return 'failed';
        }

        return 'unpaid';
    }

    /**
     * Map fulfillment status from notification transaction data.
     */
    public function mapFulfillmentStatusFromTransaction(array $transaction): string
    {
        $shippedTime = $transaction['ShippedTime'] ?? '';
        if (!empty($shippedTime)) {
            return 'fulfilled';
        }

        $pickupStatus = strtolower($transaction['PickupDetails']['PickupStatus'] ?? '');
        if ($pickupStatus === 'readyforpickup') {
            return 'ready_for_pickup';
        }
        if ($pickupStatus === 'pickedup') {
            return 'fulfilled';
        }

        $quantityPurchased = (int) ($transaction['QuantityPurchased'] ?? 1);
        $quantityShipped = (int) ($transaction['ShippingDetails']['QuantityShipped'] ?? 0);

        if ($quantityShipped > 0 && $quantityShipped < $quantityPurchased) {
            return 'partially_fulfilled';
        }
        if ($quantityShipped >= $quantityPurchased && $quantityShipped > 0) {
            return 'fulfilled';
        }

        return 'unfulfilled';
    }

    // =========================================
    // ORDER PROCESSING (from sync jobs)
    // =========================================

    /**
     * Process an eBay order from sync (parsed array from EbayService::parseOrder).
     * Creates or updates the local order record.
     */
    public function processOrder(array $ebayOrder, int $salesChannelId): string
    {
        $existingOrder = Order::where('ebay_order_id', $ebayOrder['order_id'])->first();

        if ($existingOrder) {
            $updateData = [
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'order_status' => $this->mapOrderStatus($ebayOrder['order_status'], $ebayOrder),
                'payment_status' => $this->mapPaymentStatus($ebayOrder['payment_status'], $ebayOrder),
                'fulfillment_status' => $this->mapFulfillmentStatus($ebayOrder),
            ];

            if (!empty($ebayOrder['shipped_time']) && empty($existingOrder->shipped_at)) {
                $updateData['shipped_at'] = new \DateTime($ebayOrder['shipped_time']);
            }
            if (!empty($ebayOrder['tracking_number']) && empty($existingOrder->tracking_number)) {
                $updateData['tracking_number'] = $ebayOrder['tracking_number'];
            }
            if (!empty($ebayOrder['shipping_carrier']) && empty($existingOrder->shipping_carrier)) {
                $updateData['shipping_carrier'] = $ebayOrder['shipping_carrier'];
            }

            // Update shipment deadline if not already set
            if (!empty($ebayOrder['shipment_deadline']) && empty($existingOrder->shipment_deadline)) {
                $updateData['shipment_deadline'] = new \DateTime($ebayOrder['shipment_deadline']);
            }
            if (!empty($ebayOrder['handling_time_days']) && empty($existingOrder->handling_time_days)) {
                $updateData['handling_time_days'] = $ebayOrder['handling_time_days'];
            }

            $existingOrder->update($updateData);

            // Update inventory for items that weren't updated yet
            if ($this->mapPaymentStatus($ebayOrder['payment_status'], $ebayOrder) === 'paid') {
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
                'sales_channel_id' => $salesChannelId,
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
                'order_status' => $this->mapOrderStatus($ebayOrder['order_status'], $ebayOrder),
                'payment_status' => $this->mapPaymentStatus($ebayOrder['payment_status'], $ebayOrder),
                'fulfillment_status' => $this->mapFulfillmentStatus($ebayOrder),
                'ebay_order_status' => $ebayOrder['order_status'],
                'ebay_payment_status' => $ebayOrder['payment_status'],
                'ebay_raw_data' => $ebayOrder['raw_data'],
                'order_date' => !empty($ebayOrder['created_time']) ? new \DateTime($ebayOrder['created_time']) : now(),
                'paid_at' => !empty($ebayOrder['paid_time']) ? new \DateTime($ebayOrder['paid_time']) : null,
                'shipped_at' => !empty($ebayOrder['shipped_time']) ? new \DateTime($ebayOrder['shipped_time']) : null,
                'tracking_number' => $ebayOrder['tracking_number'] ?? null,
                'shipping_carrier' => $ebayOrder['shipping_carrier'] ?? null,
                'shipment_deadline' => !empty($ebayOrder['shipment_deadline']) ? new \DateTime($ebayOrder['shipment_deadline']) : null,
                'handling_time_days' => $ebayOrder['handling_time_days'] ?? null,
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

                if ($this->mapPaymentStatus($ebayOrder['payment_status'], $ebayOrder) === 'paid') {
                    $orderItem->updateInventory();
                }
            }

            DB::commit();

            // Validate shipping address if a carrier has address validation enabled
            if ($order->shipping_address_line1) {
                try {
                    (new ShippingService())->validateOrderAddress($order);
                } catch (\Throwable $e) {
                    Log::warning('EbayOrderService: address validation failed after order create', [
                        'order_id' => $order->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            return 'created';

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // =========================================
    // NOTIFICATION PROCESSING
    // =========================================

    /**
     * Check if notification type is order-related.
     */
    public static function isOrderNotification(string $notificationType): bool
    {
        return in_array($notificationType, [
            // Order/Transaction events (Platform Notifications)
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse',
            'ItemMarkedShipped',
            'ItemMarkedAsDispatched',
            'ItemShipped',
            'ItemMarkedPaid',
            'ItemMarkedAsPaid',
            'ItemDelivered',
            'ItemMarkedAsDelivered',
            'ItemReadyForPickup',
            'ItemPickedUp',
            'BuyerCancelRequested',
            'OrderCancelled',
            'CheckoutBuyerRequestsTotal',
            'PaymentReminder',
            // eBay Money Back Guarantee events (Platform Notifications)
            'EBPClosedCase',
            'EBPEscalatedCase',
            'EBPMyResponseDue',
            'EBPOtherPartyResponseDue',
            'EBPAppealedCase',
            'EBPClosedAppeal',
            'EBPOnHoldCase',
            'EBPMyPaymentDue',
            'EBPPaymentDone',
            'INRBuyerRespondedToDispute',
            'OrderInquiryReminderForEscalation',
            // Commerce Notification API events (JSON webhooks)
            'CancelRequestApproved',
            'CancelRequestRejected',
            'RefundInitiated',
            'RefundCompleted',
            'ReturnCreated',
            'ReturnShipped',
            'ReturnDelivered',
            'ReturnClosed',
            'ReturnEscalated',
            'ReturnRefundOverdue',
            'ReturnSellerInfoOverdue',
            'ReturnWaitingForSellerInfo',
        ]);
    }

    /**
     * Check if notification is for a new order.
     */
    public static function isNewOrderNotification(string $notificationType): bool
    {
        return in_array($notificationType, [
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse',
        ]);
    }

    /**
     * Process eBay order notification based on event type.
     * Accepts pre-parsed array data (XML already converted via EbayApiClient).
     */
    public function processNotification(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        return match ($notificationType) {
            'FixedPriceTransaction',
            'AuctionCheckoutComplete',
            'ItemSold',
            'GetItemTransactionsResponse' => $this->saveFromNotification($data, $channel, $notificationType, $timestamp),

            'ItemMarkedShipped',
            'ItemMarkedAsDispatched',
            'ItemShipped' => $this->handleItemMarkedShipped($data, $channel, $notificationType, $timestamp),

            'ItemMarkedPaid',
            'ItemMarkedAsPaid' => $this->handleItemMarkedPaid($data, $channel, $notificationType, $timestamp),

            'ItemDelivered',
            'ItemMarkedAsDelivered' => $this->handleItemDelivered($data, $channel, $notificationType, $timestamp),

            'ItemReadyForPickup' => $this->handleItemReadyForPickup($data, $channel, $notificationType, $timestamp),
            'ItemPickedUp' => $this->handleItemPickedUp($data, $channel, $notificationType, $timestamp),

            'BuyerCancelRequested',
            'OrderCancelled' => $this->handleBuyerCancelRequested($data, $channel, $notificationType, $timestamp),

            'CancelRequestApproved' => $this->handleCancelRequestApproved($data, $channel, $notificationType, $timestamp),
            'CancelRequestRejected' => $this->handleCancelRequestRejected($data, $channel, $notificationType, $timestamp),

            'RefundInitiated',
            'RefundCompleted' => $this->handleRefund($data, $channel, $notificationType, $timestamp),

            // Return notifications
            'ReturnCreated' => $this->handleReturnCreated($data, $channel, $notificationType, $timestamp),
            'ReturnShipped' => $this->handleReturnShipped($data, $channel, $notificationType, $timestamp),
            'ReturnDelivered' => $this->handleReturnDelivered($data, $channel, $notificationType, $timestamp),
            'ReturnClosed' => $this->handleReturnClosed($data, $channel, $notificationType, $timestamp),
            'ReturnEscalated' => $this->handleReturnEscalated($data, $channel, $notificationType, $timestamp),
            'ReturnRefundOverdue',
            'ReturnSellerInfoOverdue',
            'ReturnWaitingForSellerInfo' => $this->handleReturnActionRequired($data, $channel, $notificationType, $timestamp),

            'CheckoutBuyerRequestsTotal' => $this->handleCheckoutBuyerRequestsTotal($data, $channel, $notificationType, $timestamp),
            'PaymentReminder' => $this->handlePaymentReminder($data, $channel, $notificationType, $timestamp),

            // eBay Money Back Guarantee / INR events
            'EBPClosedCase',
            'EBPEscalatedCase',
            'EBPMyResponseDue',
            'EBPOtherPartyResponseDue',
            'EBPAppealedCase',
            'EBPClosedAppeal',
            'EBPOnHoldCase',
            'EBPMyPaymentDue',
            'EBPPaymentDone',
            'INRBuyerRespondedToDispute',
            'OrderInquiryReminderForEscalation' => $this->handleDisputeNotification($data, $channel, $notificationType, $timestamp),

            default => null,
        };
    }

    // =========================================
    // NOTIFICATION HANDLERS
    // =========================================

    /**
     * Save order from eBay notification data (array format).
     */
    public function saveFromNotification(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $channel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            Log::channel('ebay')->warning('No order ID found in notification', [
                'notification_type' => $notificationType,
                'sales_channel_id' => $channel->id,
            ]);
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();

        // Get buyer information
        $buyer = $transaction['Buyer'] ?? [];
        $shippingAddress = $buyer['BuyerInfo']['ShippingAddress'] ?? [];

        // Calculate totals
        $transactionPrice = EbayService::priceValue($transaction['TransactionPrice'] ?? null);
        $quantity = (int) ($transaction['QuantityPurchased'] ?? 1);
        $subtotal = $transactionPrice * $quantity;
        $shippingCost = EbayService::priceValue(
            $transaction['ActualShippingCost'] ?? $transaction['ShippingServiceSelected']['ShippingServiceCost'] ?? null
        );
        $tax = EbayService::priceValue($transaction['eBayCollectAndRemitTaxes']['TotalTaxAmount'] ?? null);
        $total = $subtotal + $shippingCost + $tax;
        $currency = EbayService::priceCurrency($transaction['TransactionPrice'] ?? $transaction['AmountPaid'] ?? null);

        // Get item data
        $item = $response['Item'] ?? [];

        // Calculate shipment deadline from DispatchTimeMax (handling time)
        $handlingTimeDays = null;
        $shipmentDeadline = null;

        // Try to get DispatchTimeMax from item
        if (isset($item['DispatchTimeMax'])) {
            $handlingTimeDays = (int) $item['DispatchTimeMax'];
        } elseif (isset($transaction['Item']['DispatchTimeMax'])) {
            $handlingTimeDays = (int) $transaction['Item']['DispatchTimeMax'];
        } elseif (isset($transaction['ShippingDetails']['ShippingTimeMax'])) {
            $handlingTimeDays = (int) $transaction['ShippingDetails']['ShippingTimeMax'];
        }

        // Calculate deadline: from paid time (or created time) + handling days (as business days)
        if ($handlingTimeDays !== null) {
            $paidTime = $transaction['PaidTime'] ?? '';
            $createdDate = $transaction['CreatedDate'] ?? '';
            $baseTime = !empty($paidTime) ? $paidTime : $createdDate;

            if (!empty($baseTime)) {
                try {
                    $shipmentDeadline = Carbon::parse($baseTime)->addWeekdays($handlingTimeDays);
                } catch (\Exception $e) {
                    // Ignore parse errors
                }
            }
        }

        $orderData = [
            'sales_channel_id' => $channel->id,
            'ebay_order_id' => $ebayOrderId,
            'ebay_extended_order_id' => $transaction['ContainingOrder']['ExtendedOrderID'] ?? $transaction['ExtendedOrderID'] ?? $ebayOrderId,

            'buyer_username' => $buyer['UserID'] ?? '',
            'buyer_email' => $buyer['Email'] ?? '',
            'buyer_name' => $shippingAddress['Name'] ?? '',
            'buyer_first_name' => $buyer['UserFirstName'] ?? '',
            'buyer_last_name' => $buyer['UserLastName'] ?? '',
            'buyer_phone' => $shippingAddress['Phone'] ?? '',

            'shipping_name' => $shippingAddress['Name'] ?? '',
            'shipping_address_line1' => $shippingAddress['Street1'] ?? '',
            'shipping_address_line2' => $shippingAddress['Street2'] ?? '',
            'shipping_city' => $shippingAddress['CityName'] ?? '',
            'shipping_state' => $shippingAddress['StateOrProvince'] ?? '',
            'shipping_postal_code' => $shippingAddress['PostalCode'] ?? '',
            'shipping_country' => $shippingAddress['Country'] ?? '',
            'shipping_country_name' => $shippingAddress['CountryName'] ?? '',

            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,

            'order_status' => $this->mapOrderStatusFromTransaction($transaction),
            'payment_status' => $this->mapPaymentStatusFromTransaction($transaction),
            'fulfillment_status' => $this->mapFulfillmentStatusFromTransaction($transaction),
            'ebay_order_status' => $transaction['ContainingOrder']['OrderStatus'] ?? 'Completed',
            'ebay_payment_status' => $transaction['Status']['eBayPaymentStatus'] ?? '',
            'cancel_status' => $transaction['ContainingOrder']['CancelStatus'] ?? '',

            'buyer_checkout_message' => $transaction['BuyerCheckoutMessage'] ?? '',

            'notification_type' => $notificationType,
            'notification_received_at' => $timestamp,

            'order_date' => $this->parseEbayDate($transaction['CreatedDate'] ?? ''),
            'paid_at' => $this->parseEbayDate($transaction['PaidTime'] ?? ''),
            'shipped_at' => $this->parseEbayDate($transaction['ShippedTime'] ?? ''),

            'tracking_number' => $transaction['ShippingDetails']['ShipmentTrackingDetails']['ShipmentTrackingNumber'] ?? '',
            'shipping_carrier' => $transaction['ShippingDetails']['ShipmentTrackingDetails']['ShippingCarrierUsed'] ?? '',

            'shipment_deadline' => $shipmentDeadline,
            'handling_time_days' => $handlingTimeDays,

            'ebay_raw_data' => $data,
        ];

        try {
            DB::beginTransaction();

            if ($order) {
                $order->update($orderData);
                Log::channel('ebay')->info('Order updated from notification', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $ebayOrderId,
                ]);
            } else {
                $orderData['order_number'] = Order::generateOrderNumber();
                $order = Order::create($orderData);
                Log::channel('ebay')->info('Order created from notification', [
                    'order_id' => $order->id,
                    'ebay_order_id' => $ebayOrderId,
                ]);
            }

            $isNewOrder = !isset($orderData['id']);
            $this->saveOrderItemFromNotification($order, $transaction, $item);
            $this->saveOrderMetaFromNotification($order, $transaction, $response);

            DB::commit();

            // Validate shipping address for new orders if a carrier has it enabled
            if ($isNewOrder && $order->shipping_address_line1) {
                try {
                    (new ShippingService())->validateOrderAddress($order);
                } catch (\Throwable $e) {
                    Log::warning('EbayOrderService: address validation failed after notification create', [
                        'order_id' => $order->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to save order from notification', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemMarkedShipped notification.
     */
    protected function handleItemMarkedShipped(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemMarkedShipped notification', [
                'sales_channel_id' => $channel->id,
            ]);
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ItemMarkedShipped', ['ebay_order_id' => $ebayOrderId]);
            return null;
        }

        try {
            DB::beginTransaction();

            $trackingDetails = $transaction['ShippingDetails']['ShipmentTrackingDetails'] ?? [];
            if (isset($trackingDetails[0])) {
                $trackingDetails = $trackingDetails[0];
            }

            $trackingNumber = $trackingDetails['ShipmentTrackingNumber'] ?? '';
            $shippingCarrier = $trackingDetails['ShippingCarrierUsed'] ?? '';
            $shippedTime = $this->parseEbayDate($transaction['ShippedTime'] ?? '');

            $order->update([
                'order_status' => 'shipped',
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'tracking_number' => $trackingNumber ?: $order->tracking_number,
                'shipping_carrier' => $shippingCarrier ?: $order->shipping_carrier,
                'shipped_at' => $shippedTime ?? now(),
                'ebay_order_status' => $transaction['ContainingOrder']['OrderStatus'] ?? $order->ebay_order_status,
            ]);

            if (!empty($trackingNumber)) {
                $order->setMeta('shipment_tracking', [
                    'tracking_number' => $trackingNumber,
                    'shipping_carrier' => $shippingCarrier,
                    'shipped_time' => $transaction['ShippedTime'] ?? '',
                ]);
            }

            foreach ($order->items as $item) {
                if (!$item->inventory_updated) {
                    $item->updateInventory();
                }
            }

            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemMarkedShipped',
                'timestamp' => $timestamp,
                'tracking_number' => $trackingNumber,
                'shipping_carrier' => $shippingCarrier,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as shipped', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'tracking_number' => $trackingNumber,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemMarkedShipped', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemMarkedPaid notification.
     */
    protected function handleItemMarkedPaid(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemMarkedPaid notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return $this->saveFromNotification($data, $channel, $notificationType, $timestamp);
        }

        try {
            DB::beginTransaction();

            $paidTime = $this->parseEbayDate($transaction['PaidTime'] ?? '');

            $updateData = [
                'payment_status' => 'paid',
                'paid_at' => $paidTime ?? now(),
                'ebay_payment_status' => $transaction['Status']['eBayPaymentStatus'] ?? 'NoPaymentFailure',
            ];

            // When paid but not yet shipped, move order to processing
            if (in_array($order->order_status, ['pending', 'unpaid'])) {
                $updateData['order_status'] = 'processing';
            }

            $order->update($updateData);

            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemMarkedPaid',
                'timestamp' => $timestamp,
                'paid_time' => $paidTime?->toIso8601String(),
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as paid', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemMarkedPaid', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemDelivered notification.
     */
    protected function handleItemDelivered(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            Log::channel('ebay')->warning('No transaction found in ItemDelivered notification');
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'delivered',
                'fulfillment_status' => 'fulfilled',
                'ebay_order_status' => $transaction['ContainingOrder']['OrderStatus'] ?? 'Completed',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as delivered', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('ebay')->error('Failed to process ItemDelivered', [
                'ebay_order_id' => $ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle ItemPickedUp notification.
     */
    protected function handleItemPickedUp(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'delivered',
                'fulfillment_status' => 'fulfilled',
                'ebay_order_status' => $transaction['ContainingOrder']['OrderStatus'] ?? 'Completed',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as picked up', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ItemReadyForPickup notification.
     */
    protected function handleItemReadyForPickup(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'ready_for_pickup',
                'fulfillment_status' => 'ready_for_pickup',
                'ebay_order_status' => $transaction['ContainingOrder']['OrderStatus'] ?? $order->ebay_order_status,
            ]);

            if (isset($transaction['PickupDetails'])) {
                $order->setMeta('pickup_details', $transaction['PickupDetails']);
            }

            $order->setMeta('event_log_' . time(), [
                'event' => 'ItemReadyForPickup',
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order marked as ready for pickup', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle BuyerCancelRequested notification.
     */
    protected function handleBuyerCancelRequested(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $cancelReason = $transaction['ContainingOrder']['CancelReason'] ?? $transaction['CancelReason'] ?? 'Buyer requested cancellation';
            $cancelStatus = $transaction['ContainingOrder']['CancelStatus'] ?? 'CancelRequested';

            $order->update([
                'order_status' => 'cancellation_requested',
                'cancel_status' => $cancelStatus,
            ]);

            $order->setMeta('cancellation_request', [
                'requested_at' => $timestamp,
                'reason' => $cancelReason,
                'cancel_status' => $cancelStatus,
                'requested_by' => 'buyer',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'BuyerCancelRequested',
                'timestamp' => $timestamp,
                'reason' => $cancelReason,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Order cancellation requested by buyer', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'reason' => $cancelReason,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle Refund notification.
     */
    protected function handleRefund(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $refundAmount = EbayService::priceValue($transaction['MonetaryDetails']['Refunds']['Refund']['RefundAmount'] ?? null);
            $refundStatus = $transaction['MonetaryDetails']['Refunds']['Refund']['RefundStatus'] ?? '';
            $refundTime = $this->parseEbayDate($transaction['MonetaryDetails']['Refunds']['Refund']['RefundTime'] ?? '');

            $isCompleted = $notificationType === 'RefundCompleted' || strtolower($refundStatus) === 'successful';

            if ($isCompleted) {
                $order->update([
                    'order_status' => 'refunded',
                    'payment_status' => 'refunded',
                ]);
            }

            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
                'refund_amount' => $refundAmount,
                'refund_status' => $refundStatus,
                'refund_time' => $refundTime?->toIso8601String(),
            ]);

            if ($isCompleted) {
                foreach ($order->items as $item) {
                    if ($item->inventory_updated) {
                        $item->restoreInventory();
                    }
                }
            }

            DB::commit();

            Log::channel('ebay')->info('Order refund processed', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'notification_type' => $notificationType,
                'refund_amount' => $refundAmount,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle CancelRequestApproved notification.
     */
    protected function handleCancelRequestApproved(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'cancelled',
                'cancel_status' => 'CancelComplete',
                'cancellation_closed_at' => now(),
            ]);

            // Restore inventory for cancelled orders
            foreach ($order->items as $item) {
                if ($item->inventory_updated) {
                    $item->restoreInventory();
                }
            }

            $order->setMeta('event_log_' . time(), [
                'event' => 'CancelRequestApproved',
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Cancellation approved and order cancelled', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle CancelRequestRejected notification.
     */
    protected function handleCancelRequestRejected(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            // Revert to processing status if cancellation was rejected
            $previousStatus = $order->payment_status === 'paid' ? 'processing' : 'pending';

            $order->update([
                'order_status' => $previousStatus,
                'cancel_status' => 'CancelRejected',
                'cancellation_closed_at' => now(),
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'CancelRequestRejected',
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Cancellation rejected, order reverted', [
                'order_id' => $order->id,
                'ebay_order_id' => $ebayOrderId,
                'new_status' => $previousStatus,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ReturnCreated notification.
     */
    protected function handleReturnCreated(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        // Try to find order by various identifiers
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';
        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $itemId = $returnData['ItemId'] ?? $returnData['itemId'] ?? '';

        $order = null;
        if (!empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }
        if (!$order && !empty($itemId)) {
            $order = Order::whereHas('items', function ($q) use ($itemId) {
                $q->where('ebay_item_id', $itemId);
            })->first();
        }

        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ReturnCreated', [
                'order_id' => $orderId,
                'item_id' => $itemId,
                'return_id' => $returnId,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $returnReason = $returnData['ReturnReason'] ?? $returnData['returnReason'] ?? '';

            $order->update([
                'return_status' => 'return_requested',
                'return_id' => $returnId,
                'return_reason' => $returnReason,
                'return_requested_at' => now(),
            ]);

            $order->setMeta('return_details', [
                'return_id' => $returnId,
                'reason' => $returnReason,
                'buyer_comments' => $returnData['BuyerComments'] ?? $returnData['buyerComments'] ?? '',
                'item_id' => $itemId,
                'created_at' => $timestamp,
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'ReturnCreated',
                'timestamp' => $timestamp,
                'return_id' => $returnId,
                'return_reason' => $returnReason,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Return request created', [
                'order_id' => $order->id,
                'ebay_order_id' => $order->ebay_order_id,
                'return_id' => $returnId,
                'return_reason' => $returnReason,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ReturnShipped notification (buyer shipped the return).
     */
    protected function handleReturnShipped(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';

        $order = null;
        if (!empty($returnId)) {
            $order = Order::where('return_id', $returnId)->first();
        }
        if (!$order && !empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }

        if (!$order) {
            Log::channel('ebay')->warning('Order not found for ReturnShipped', [
                'return_id' => $returnId,
                'order_id' => $orderId,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $trackingNumber = $returnData['TrackingNumber'] ?? $returnData['trackingNumber'] ?? '';
            $shippingCarrier = $returnData['ShippingCarrier'] ?? $returnData['shippingCarrier'] ?? '';

            $order->update([
                'return_status' => 'return_shipped',
            ]);

            $order->setMeta('return_shipping', [
                'tracking_number' => $trackingNumber,
                'shipping_carrier' => $shippingCarrier,
                'shipped_at' => $timestamp,
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'ReturnShipped',
                'timestamp' => $timestamp,
                'tracking_number' => $trackingNumber,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Return shipped by buyer', [
                'order_id' => $order->id,
                'return_id' => $returnId,
                'tracking_number' => $trackingNumber,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ReturnDelivered notification (return received by seller).
     */
    protected function handleReturnDelivered(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';

        $order = null;
        if (!empty($returnId)) {
            $order = Order::where('return_id', $returnId)->first();
        }
        if (!$order && !empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }

        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'return_status' => 'return_delivered',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'ReturnDelivered',
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Return delivered to seller', [
                'order_id' => $order->id,
                'return_id' => $returnId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ReturnClosed notification.
     */
    protected function handleReturnClosed(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';

        $order = null;
        if (!empty($returnId)) {
            $order = Order::where('return_id', $returnId)->first();
        }
        if (!$order && !empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }

        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $closeReason = $returnData['CloseReason'] ?? $returnData['closeReason'] ?? '';

            $order->update([
                'return_status' => 'closed',
                'return_closed_at' => now(),
            ]);

            // If return resulted in refund, update refund status
            $refundStatus = $returnData['RefundStatus'] ?? $returnData['refundStatus'] ?? '';
            if (in_array(strtolower($refundStatus), ['refunded', 'successful', 'completed'])) {
                $refundAmount = EbayService::priceValue($returnData['RefundAmount'] ?? null);
                $order->update([
                    'refund_status' => 'completed',
                    'refund_amount' => $refundAmount > 0 ? $refundAmount : $order->total,
                    'refund_completed_at' => now(),
                    'payment_status' => 'refunded',
                ]);

                // Restore inventory for refunded returns
                foreach ($order->items as $item) {
                    if ($item->inventory_updated) {
                        $item->restoreInventory();
                    }
                }
            }

            $order->setMeta('event_log_' . time(), [
                'event' => 'ReturnClosed',
                'timestamp' => $timestamp,
                'close_reason' => $closeReason,
                'refund_status' => $refundStatus,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Return closed', [
                'order_id' => $order->id,
                'return_id' => $returnId,
                'close_reason' => $closeReason,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle ReturnEscalated notification.
     */
    protected function handleReturnEscalated(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';

        $order = null;
        if (!empty($returnId)) {
            $order = Order::where('return_id', $returnId)->first();
        }
        if (!$order && !empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }

        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'return_status' => 'escalated',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => 'ReturnEscalated',
                'timestamp' => $timestamp,
                'escalation_reason' => $returnData['EscalationReason'] ?? '',
            ]);

            DB::commit();

            Log::channel('ebay')->warning('Return escalated to eBay', [
                'order_id' => $order->id,
                'return_id' => $returnId,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle return action required notifications (RefundOverdue, SellerInfoOverdue, WaitingForSellerInfo).
     */
    protected function handleReturnActionRequired(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $returnData = $response['ReturnId'] ?? $response;

        $returnId = $returnData['ReturnId'] ?? $returnData['returnId'] ?? '';
        $orderId = $returnData['OrderId'] ?? $returnData['orderId'] ?? '';

        $order = null;
        if (!empty($returnId)) {
            $order = Order::where('return_id', $returnId)->first();
        }
        if (!$order && !empty($orderId)) {
            $order = Order::where('ebay_order_id', $orderId)->first();
        }

        if (!$order) {
            return null;
        }

        try {
            DB::beginTransaction();

            $order->update([
                'return_status' => 'action_required',
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
                'action_required' => true,
            ]);

            DB::commit();

            Log::channel('ebay')->warning('Return action required', [
                'order_id' => $order->id,
                'return_id' => $returnId,
                'notification_type' => $notificationType,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle eBay Money Back Guarantee / INR dispute notifications.
     */
    protected function handleDisputeNotification(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);

        // Try to extract order/item information from dispute data
        $disputeData = $response['DisputeID'] ?? $response['Dispute'] ?? $response;
        $itemId = $disputeData['ItemId'] ?? $disputeData['Item']['ItemID'] ?? '';
        $transactionId = $disputeData['TransactionId'] ?? $disputeData['TransactionID'] ?? '';
        $disputeId = $disputeData['DisputeID'] ?? $disputeData['CaseId'] ?? '';

        $order = null;

        // Try to find order by item + transaction
        if (!empty($itemId) && !empty($transactionId)) {
            $order = Order::whereHas('items', function ($q) use ($itemId, $transactionId) {
                $q->where('ebay_item_id', $itemId)
                  ->where('ebay_transaction_id', $transactionId);
            })->first();
        }

        // Fallback: find by item ID only
        if (!$order && !empty($itemId)) {
            $order = Order::whereHas('items', function ($q) use ($itemId) {
                $q->where('ebay_item_id', $itemId);
            })->first();
        }

        if (!$order) {
            Log::channel('ebay')->warning('Order not found for dispute notification', [
                'notification_type' => $notificationType,
                'dispute_id' => $disputeId,
                'item_id' => $itemId,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Determine status based on notification type
            $disputeStatus = match ($notificationType) {
                'EBPClosedCase' => 'dispute_closed',
                'EBPEscalatedCase' => 'dispute_escalated',
                'EBPMyResponseDue' => 'dispute_response_due',
                'EBPOtherPartyResponseDue' => 'dispute_waiting',
                'EBPAppealedCase' => 'dispute_appealed',
                'EBPClosedAppeal' => 'dispute_appeal_closed',
                'EBPOnHoldCase' => 'dispute_on_hold',
                'EBPMyPaymentDue' => 'dispute_payment_due',
                'EBPPaymentDone' => 'dispute_payment_done',
                'INRBuyerRespondedToDispute' => 'inr_buyer_responded',
                'OrderInquiryReminderForEscalation' => 'inr_escalation_reminder',
                default => 'dispute_active',
            };

            // Update return_status to track dispute
            $order->update([
                'return_status' => $disputeStatus,
            ]);

            $order->setMeta('dispute_' . $disputeId, [
                'dispute_id' => $disputeId,
                'notification_type' => $notificationType,
                'status' => $disputeStatus,
                'item_id' => $itemId,
                'timestamp' => $timestamp,
            ]);

            $order->setMeta('event_log_' . time(), [
                'event' => $notificationType,
                'timestamp' => $timestamp,
                'dispute_id' => $disputeId,
                'dispute_status' => $disputeStatus,
            ]);

            DB::commit();

            Log::channel('ebay')->info('Dispute notification processed', [
                'order_id' => $order->id,
                'notification_type' => $notificationType,
                'dispute_id' => $disputeId,
                'dispute_status' => $disputeStatus,
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle CheckoutBuyerRequestsTotal notification.
     */
    protected function handleCheckoutBuyerRequestsTotal(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);

        if (!empty($ebayOrderId)) {
            $order = Order::where('ebay_order_id', $ebayOrderId)->first();
            if ($order) {
                $order->setMeta('event_log_' . time(), [
                    'event' => 'CheckoutBuyerRequestsTotal',
                    'timestamp' => $timestamp,
                ]);
                return $order;
            }
        }

        Log::channel('ebay')->info('CheckoutBuyerRequestsTotal received (no existing order)');
        return null;
    }

    /**
     * Handle PaymentReminder notification.
     */
    protected function handlePaymentReminder(array $data, SalesChannel $channel, string $notificationType, $timestamp): ?Order
    {
        $response = $this->extractResponseFromEnvelope($data);
        $transaction = $this->extractTransaction($response);

        if (!$transaction) {
            return null;
        }

        $ebayOrderId = $this->extractOrderId($transaction);
        if (empty($ebayOrderId)) {
            return null;
        }

        $order = Order::where('ebay_order_id', $ebayOrderId)->first();
        if (!$order) {
            return $this->saveFromNotification($data, $channel, $notificationType, $timestamp);
        }

        if ($order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'unpaid']);
        }

        $order->setMeta('event_log_' . time(), [
            'event' => 'PaymentReminder',
            'timestamp' => $timestamp,
        ]);

        Log::channel('ebay')->info('Payment reminder received', [
            'order_id' => $order->id,
            'ebay_order_id' => $ebayOrderId,
        ]);

        return $order;
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Extract response from SOAP envelope (array format).
     */
    protected function extractResponseFromEnvelope(array $data): array
    {
        if (isset($data['Body'])) {
            $body = $data['Body'];
            // Get first child element of Body
            foreach ($body as $key => $value) {
                if (is_array($value)) {
                    return $value;
                }
            }
        }
        return $data;
    }

    /**
     * Extract transaction from response (array format).
     */
    protected function extractTransaction(array $response): ?array
    {
        if (!isset($response['TransactionArray']['Transaction'])) {
            return null;
        }

        $transactions = $response['TransactionArray']['Transaction'];

        // If it's a list of transactions, get the first one
        if (isset($transactions[0])) {
            return $transactions[0];
        }

        // Single transaction (associative array)
        return $transactions;
    }

    /**
     * Extract order ID from transaction (array format).
     */
    protected function extractOrderId(array $transaction): string
    {
        return $transaction['ContainingOrder']['OrderID']
            ?? $transaction['ContainingOrder']['ExtendedOrderID']
            ?? $transaction['ExtendedOrderID']
            ?? '';
    }

    /**
     * Parse eBay date string to Carbon instance.
     */
    protected function parseEbayDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if payment is confirmed using reliable indicators.
     */
    private function isPaymentConfirmed(array $ebayOrder): bool
    {
        if (!empty($ebayOrder['paid_time'])) {
            return true;
        }

        $checkoutStatus = strtolower($ebayOrder['checkout_status'] ?? '');
        if ($checkoutStatus === 'complete') {
            return true;
        }

        $paymentStatus = strtolower($ebayOrder['payment_status'] ?? '');
        return in_array($paymentStatus, ['paymentcomplete', 'paid']);
    }

    /**
     * Save order item from notification transaction data (array format).
     */
    protected function saveOrderItemFromNotification(Order $order, array $transaction, array $item = []): OrderItem
    {
        $itemId = $item['ItemID'] ?? $transaction['Item']['ItemID'] ?? '';
        $transactionId = $transaction['TransactionID'] ?? '';

        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('ebay_transaction_id', $transactionId)
            ->first();

        $sku = $item['SKU'] ?? $transaction['Item']['SKU'] ?? $transaction['Variation']['SKU'] ?? '';
        $productId = null;

        if (!empty($sku)) {
            $product = Product::where('sku', $sku)->first();
            $productId = $product?->id;
        }

        $itemData = [
            'order_id' => $order->id,
            'product_id' => $productId,
            'ebay_item_id' => $itemId,
            'ebay_transaction_id' => $transactionId,
            'ebay_line_item_id' => $transaction['OrderLineItemID'] ?? '',
            'sku' => $sku,
            'title' => $item['Title'] ?? $transaction['Item']['Title'] ?? '',
            'quantity' => (int) ($transaction['QuantityPurchased'] ?? 1),
            'unit_price' => EbayService::priceValue($transaction['TransactionPrice'] ?? null),
            'total_price' => EbayService::priceValue($transaction['TransactionPrice'] ?? null) * (int) ($transaction['QuantityPurchased'] ?? 1),
            'actual_shipping_cost' => EbayService::priceValue($transaction['ActualShippingCost'] ?? null),
            'actual_handling_cost' => EbayService::priceValue($transaction['ActualHandlingCost'] ?? null),
            'final_value_fee' => EbayService::priceValue($transaction['FinalValueFee'] ?? null),
            'currency' => EbayService::priceCurrency($transaction['TransactionPrice'] ?? null),
            'listing_type' => $item['ListingType'] ?? $transaction['Item']['ListingType'] ?? '',
            'condition_id' => $item['ConditionID'] ?? $transaction['Item']['ConditionID'] ?? '',
            'condition_display_name' => $item['ConditionDisplayName'] ?? $transaction['Item']['ConditionDisplayName'] ?? '',
            'site' => $item['Site'] ?? $transaction['Item']['Site'] ?? '',
            'shipping_service' => $transaction['ShippingServiceSelected']['ShippingService'] ?? '',
            'buyer_checkout_message' => $transaction['BuyerCheckoutMessage'] ?? '',
            'item_paid_time' => $this->parseEbayDate($transaction['PaidTime'] ?? ''),
            'variation_attributes' => $this->extractVariationAttributes($transaction),
        ];

        if ($orderItem) {
            $orderItem->update($itemData);
        } else {
            $orderItem = OrderItem::create($itemData);
        }

        return $orderItem;
    }

    /**
     * Save order metadata from notification data (array format).
     */
    protected function saveOrderMetaFromNotification(Order $order, array $transaction, array $response): void
    {
        // Save seller info
        $seller = $response['Item']['Seller'] ?? $transaction['Item']['Seller'] ?? null;
        if ($seller) {
            $order->setMeta('seller_info', [
                'user_id' => $seller['UserID'] ?? '',
                'email' => $seller['Email'] ?? '',
                'feedback_score' => (int) ($seller['FeedbackScore'] ?? 0),
            ]);
        }

        // Save shipping address
        $shippingAddress = $transaction['Buyer']['BuyerInfo']['ShippingAddress'] ?? null;
        if ($shippingAddress) {
            $order->setMeta('shipping_address', [
                'name' => $shippingAddress['Name'] ?? '',
                'street1' => $shippingAddress['Street1'] ?? '',
                'street2' => $shippingAddress['Street2'] ?? '',
                'city' => $shippingAddress['CityName'] ?? '',
                'state' => $shippingAddress['StateOrProvince'] ?? '',
                'postal_code' => $shippingAddress['PostalCode'] ?? '',
                'country' => $shippingAddress['Country'] ?? '',
                'country_name' => $shippingAddress['CountryName'] ?? '',
                'phone' => $shippingAddress['Phone'] ?? '',
                'address_id' => $shippingAddress['AddressID'] ?? '',
                'address_owner' => $shippingAddress['AddressOwner'] ?? '',
                'external_address_id' => $shippingAddress['ExternalAddressID'] ?? '',
            ]);
        }

        // Save monetary, tax, shipping details as-is (already arrays)
        if (isset($transaction['MonetaryDetails'])) {
            $order->setMeta('monetary_details', $transaction['MonetaryDetails']);
        }
        if (isset($transaction['eBayCollectAndRemitTaxes'])) {
            $order->setMeta('tax_details', $transaction['eBayCollectAndRemitTaxes']);
        }
        if (isset($transaction['ShippingDetails'])) {
            $order->setMeta('shipping_details', $transaction['ShippingDetails']);
        }

        // Save shipping service selected
        if (isset($transaction['ShippingServiceSelected'])) {
            $sss = $transaction['ShippingServiceSelected'];
            $order->setMeta('shipping_service_selected', [
                'shipping_service' => $sss['ShippingService'] ?? '',
                'shipping_service_cost' => EbayService::priceValue($sss['ShippingServiceCost'] ?? null),
                'shipping_service_priority' => $sss['ShippingServicePriority'] ?? '',
                'expedited_service' => $sss['ExpeditedService'] ?? '',
                'shipping_time_min' => (int) ($sss['ShippingTimeMin'] ?? 0),
                'shipping_time_max' => (int) ($sss['ShippingTimeMax'] ?? 0),
            ]);
        }

        // Save buyer details
        if (isset($transaction['Buyer'])) {
            $order->setMeta('buyer_details', $transaction['Buyer']);
        }

        // Save containing order details
        if (isset($transaction['ContainingOrder'])) {
            $co = $transaction['ContainingOrder'];
            $order->setMeta('containing_order', [
                'order_id' => $co['OrderID'] ?? '',
                'order_status' => $co['OrderStatus'] ?? '',
                'cancel_status' => $co['CancelStatus'] ?? '',
                'checkout_status' => $co['CheckoutStatus']['Status'] ?? '',
                'payment_method' => $co['CheckoutStatus']['PaymentMethod'] ?? '',
            ]);
        }

        // Save transaction status
        if (isset($transaction['Status'])) {
            $status = $transaction['Status'];
            $order->setMeta('transaction_status', [
                'payment_hold_status' => $status['PaymentHoldStatus'] ?? '',
                'inquiry_status' => $status['InquiryStatus'] ?? '',
                'return_status' => $status['ReturnStatus'] ?? '',
                'ebay_payment_status' => $status['eBayPaymentStatus'] ?? '',
                'checkout_status' => $status['CheckoutStatus'] ?? '',
                'complete_status' => $status['CompleteStatus'] ?? '',
            ]);
        }

        // Save external payment details
        if (isset($transaction['ExternalTransaction'])) {
            $ext = $transaction['ExternalTransaction'];
            $order->setMeta('payment_details', [
                'external_transaction_id' => $ext['ExternalTransactionID'] ?? '',
                'external_transaction_status' => $ext['ExternalTransactionStatus'] ?? '',
                'payment_or_refund_amount' => EbayService::priceValue($ext['PaymentOrRefundAmount'] ?? null),
                'external_transaction_time' => $ext['ExternalTransactionTime'] ?? '',
            ]);
        }

        // Save program info, item specifics as-is
        if (isset($transaction['Program'])) {
            $order->setMeta('program_info', $transaction['Program']);
        }
        if (isset($response['Item']['ItemSpecifics'])) {
            $order->setMeta('item_specifics', $response['Item']['ItemSpecifics']);
        }
    }

    /**
     * Extract variation attributes from transaction (array format).
     */
    protected function extractVariationAttributes(array $transaction): ?array
    {
        if (!isset($transaction['Variation']['VariationSpecifics']['NameValueList'])) {
            return null;
        }

        $attributes = [];
        $nameValueList = EbayService::normalizeList($transaction['Variation']['VariationSpecifics']['NameValueList']);

        foreach ($nameValueList as $spec) {
            $attributes[$spec['Name'] ?? ''] = $spec['Value'] ?? '';
        }

        return !empty($attributes) ? $attributes : null;
    }
}
