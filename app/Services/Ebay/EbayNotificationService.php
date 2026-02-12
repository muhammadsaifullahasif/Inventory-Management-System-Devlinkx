<?php

namespace App\Services\Ebay;

use Exception;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Log;

/**
 * eBay Platform Notification subscription management.
 *
 * Uses EbayApiClient for API transport and EbayXmlBuilder for XML construction.
 *
 * Usage:
 *   $service = app(EbayNotificationService::class);
 *   $channel = app(EbayApiClient::class)->ensureValidToken($salesChannel);
 *   $service->subscribeToPlatformNotifications($channel);
 */
class EbayNotificationService
{
    /**
     * All available eBay Platform Notification events organized by category
     */
    public const ALL_NOTIFICATION_EVENTS = [
        // Order & Transaction Events
        'FixedPriceTransaction',
        'AuctionCheckoutComplete',
        'ItemSold',
        'ItemMarkedShipped',
        'ItemMarkedPaid',
        'ItemReadyForPickup',
        'BuyerCancelRequested',
        'CheckoutBuyerRequestsTotal',
        'PaymentReminder',

        // Auction Events
        'EndOfAuction',
        'BidPlaced',
        'BidReceived',
        'OutBid',
        'ItemWon',
        'ItemLost',
        'BidItemEndingSoon',
        'SecondChanceOffer',

        // Best Offer Events
        'BestOffer',
        'BestOfferPlaced',
        'BestOfferDeclined',
        'CounterOfferReceived',

        // Listing Events
        'ItemListed',
        'ItemRevised',
        'ItemRevisedAddCharity',
        'ItemExtended',
        'ItemClosed',
        'ItemUnsold',
        'ItemSuspended',
        'ItemOutOfStock',

        // Watch List Events
        'ItemAddedToWatchList',
        'ItemRemovedFromWatchList',
        'WatchedItemEndingSoon',
        'ShoppingCartItemEndingSoon',

        // Feedback Events
        'Feedback',
        'FeedbackLeft',
        'FeedbackReceived',
        'FeedbackStarChanged',

        // Message Events
        'AskSellerQuestion',
        'MyMessagesM2MMessage',
        'M2MMessageStatusChange',

        // Return Events
        'ReturnCreated',
        'ReturnShipped',
        'ReturnDelivered',
        'ReturnClosed',
        'ReturnEscalated',
        'ReturnRefundOverdue',
        'ReturnSellerInfoOverdue',
        'ReturnWaitingForSellerInfo',

        // eBay Money Back Guarantee Events
        'EBPClosedCase',
        'EBPEscalatedCase',
        'EBPMyResponseDue',
        'EBPOtherPartyResponseDue',
        'EBPAppealedCase',
        'EBPClosedAppeal',
        'EBPOnHoldCase',
        'EBPMyPaymentDue',
        'EBPPaymentDone',

        // Item Not Received Events
        'INRBuyerRespondedToDispute',
        'OrderInquiryReminderForEscalation',

        // Account Events
        'TokenRevocation',
    ];

    /**
     * Recommended events for order management
     */
    public const ORDER_EVENTS = [
        'FixedPriceTransaction',
        'AuctionCheckoutComplete',
        'ItemSold',
        'ItemMarkedShipped',
        'ItemMarkedPaid',
        'ItemReadyForPickup',
        'BuyerCancelRequested',
        'CheckoutBuyerRequestsTotal',
        'PaymentReminder',
    ];

    public function __construct(
        private EbayApiClient $client,
    ) {}

    /**
     * Subscribe to Platform Notifications (Trading API).
     */
    public function subscribeToPlatformNotifications(SalesChannel $channel, ?array $events = null): array
    {
        $events = $events ?? self::ALL_NOTIFICATION_EVENTS;
        $webhookUrl = $this->getWebhookUrl($channel);

        $xml = EbayXmlBuilder::setNotificationPreferences($webhookUrl, $events);
        $response = $this->client->call($channel, 'SetNotificationPreferences', $xml);
        $this->client->checkForErrors($response);

        $channel->update([
            'platform_notifications_enabled' => true,
            'platform_notification_events' => $events,
            'webhook_url' => $webhookUrl,
        ]);

        Log::channel('ebay')->info('eBay Platform Notifications subscribed', [
            'sales_channel_id' => $channel->id,
            'events_count' => count($events),
            'events' => $events,
            'webhook_url' => $webhookUrl,
        ]);

        return [
            'success' => true,
            'message' => 'Successfully subscribed to ' . count($events) . ' notification events',
            'events' => $events,
            'webhook_url' => $webhookUrl,
        ];
    }

    /**
     * Subscribe to all available notification events.
     */
    public function subscribeToAllEvents(SalesChannel $channel): array
    {
        return $this->subscribeToPlatformNotifications($channel, self::ALL_NOTIFICATION_EVENTS);
    }

    /**
     * Subscribe to order-related events only.
     */
    public function subscribeToOrderEvents(SalesChannel $channel): array
    {
        return $this->subscribeToPlatformNotifications($channel, self::ORDER_EVENTS);
    }

    /**
     * Get current notification preferences.
     */
    public function getNotificationPreferences(SalesChannel $channel): array
    {
        $xml = EbayXmlBuilder::getNotificationPreferences();
        $response = $this->client->call($channel, 'GetNotificationPreferences', $xml);
        $this->client->checkForErrors($response);

        $preferences = [
            'application_delivery_preferences' => [],
            'user_delivery_preferences' => [],
            'enabled_events' => [],
        ];

        if (isset($response['ApplicationDeliveryPreferences'])) {
            $adp = $response['ApplicationDeliveryPreferences'];
            $preferences['application_delivery_preferences'] = [
                'application_url' => $adp['ApplicationURL'] ?? '',
                'application_enable' => $adp['ApplicationEnable'] ?? '',
                'alert_enable' => $adp['AlertEnable'] ?? '',
                'device_type' => $adp['DeviceType'] ?? '',
            ];
        }

        $notificationEnables = EbayService::normalizeList(
            $response['UserDeliveryPreferenceArray']['NotificationEnable'] ?? []
        );

        foreach ($notificationEnables as $pref) {
            $eventType = $pref['EventType'] ?? '';
            $eventEnable = $pref['EventEnable'] ?? '';

            $preferences['user_delivery_preferences'][] = [
                'event_type' => $eventType,
                'event_enable' => $eventEnable,
            ];

            if ($eventEnable === 'Enable') {
                $preferences['enabled_events'][] = $eventType;
            }
        }

        return [
            'success' => true,
            'preferences' => $preferences,
        ];
    }

    /**
     * Disable Platform Notifications.
     */
    public function disablePlatformNotifications(SalesChannel $channel): array
    {
        $xml = EbayXmlBuilder::disableNotifications();
        $response = $this->client->call($channel, 'SetNotificationPreferences', $xml);
        $this->client->checkForErrors($response);

        $channel->update([
            'platform_notifications_enabled' => false,
        ]);

        Log::channel('ebay')->info('eBay Platform Notifications disabled', [
            'sales_channel_id' => $channel->id,
        ]);

        return [
            'success' => true,
            'message' => 'Platform notifications disabled',
        ];
    }

    /**
     * Get the webhook URL for a sales channel.
     */
    public function getWebhookUrl(SalesChannel $channel): string
    {
        return rtrim(config('app.url'), '/') . '/api/ebay/webhook/' . $channel->id;
    }

    /**
     * Verify webhook challenge from eBay (Commerce Notification API).
     */
    public function verifyChallengeCode(string $challengeCode, string $verificationToken, string $endpoint): string
    {
        $hashInput = $challengeCode . $verificationToken . $endpoint;
        return hash('sha256', $hashInput);
    }
}
