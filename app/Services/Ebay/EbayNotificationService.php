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

    /**
     * Return-related notification events (eBay Money Back Guarantee / INR)
     * Note: ReturnCreated, ReturnShipped, etc. are Commerce Notification API events,
     * not Platform Notification events. Use EBP* events for dispute tracking.
     */
    public const RETURN_EVENTS = [
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
    ];

    /**
     * Cancellation events (valid Platform Notification events)
     */
    public const CANCEL_REFUND_EVENTS = [
        'BuyerCancelRequested',
    ];

    /**
     * Complete order management events (valid Platform Notification events only)
     * Note: Return and refund status updates come via Commerce Notification API webhooks,
     * not Platform Notifications. This subscribes to what's available via Trading API.
     */
    public const COMPLETE_ORDER_EVENTS = [
        'FixedPriceTransaction',
        'AuctionCheckoutComplete',
        'ItemSold',
        'ItemMarkedShipped',
        'ItemMarkedPaid',
        'ItemReadyForPickup',
        'BuyerCancelRequested',
        'CheckoutBuyerRequestsTotal',
        'PaymentReminder',
        // eBay Money Back Guarantee / Dispute events
        'EBPClosedCase',
        'EBPEscalatedCase',
        'EBPMyResponseDue',
        'EBPOtherPartyResponseDue',
        'INRBuyerRespondedToDispute',
        'OrderInquiryReminderForEscalation',
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
     * Subscribe to return-related events only.
     */
    public function subscribeToReturnEvents(SalesChannel $channel): array
    {
        return $this->subscribeToPlatformNotifications($channel, self::RETURN_EVENTS);
    }

    /**
     * Subscribe to cancellation and refund events only.
     */
    public function subscribeToCancelRefundEvents(SalesChannel $channel): array
    {
        return $this->subscribeToPlatformNotifications($channel, self::CANCEL_REFUND_EVENTS);
    }

    /**
     * Subscribe to complete order management events (orders + returns + cancellations).
     */
    public function subscribeToCompleteOrderEvents(SalesChannel $channel): array
    {
        return $this->subscribeToPlatformNotifications($channel, self::COMPLETE_ORDER_EVENTS);
    }

    /**
     * Get current notification preferences (both User and Application level).
     */
    public function getNotificationPreferences(SalesChannel $channel): array
    {
        // Get User-level preferences (which events are enabled)
        $userXml = EbayXmlBuilder::getNotificationPreferences();
        $userResponse = $this->client->call($channel, 'GetNotificationPreferences', $userXml);
        $this->client->checkForErrors($userResponse);

        // Get Application-level preferences (webhook URL)
        $appXml = EbayXmlBuilder::getNotificationPreferencesApplication();
        $appResponse = $this->client->call($channel, 'GetNotificationPreferences', $appXml);
        $this->client->checkForErrors($appResponse);

        $preferences = [
            'application_delivery_preferences' => [],
            'user_delivery_preferences' => [],
            'enabled_events' => [],
        ];

        // Extract Application-level preferences (webhook URL, enabled status)
        if (isset($appResponse['ApplicationDeliveryPreferences'])) {
            $adp = $appResponse['ApplicationDeliveryPreferences'];
            $preferences['application_delivery_preferences'] = [
                'application_url' => $adp['ApplicationURL'] ?? '',
                'application_enable' => $adp['ApplicationEnable'] ?? '',
                'alert_enable' => $adp['AlertEnable'] ?? '',
                'device_type' => $adp['DeviceType'] ?? '',
            ];
        }

        // Extract User-level preferences (enabled events)
        $notificationEnables = EbayService::normalizeList(
            $userResponse['UserDeliveryPreferenceArray']['NotificationEnable'] ?? []
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

        return [
            'success' => true,
            'message' => 'Platform notifications disabled',
        ];
    }

    /**
     * Get the webhook URL for notifications.
     * eBay keeps a single ApplicationURL per developer app, so every store on
     * the shared keyset points at the same endpoint; handleEbayWebhook() routes
     * each notification to its channel by RecipientUserID.
     */
    public function getWebhookUrl(SalesChannel $channel): string
    {
        return rtrim(config('app.url'), '/') . '/api/ebay/webhook';
    }

    /**
     * Verify webhook challenge from eBay (Commerce Notification API).
     */
    public function verifyChallengeCode(string $challengeCode, string $verificationToken, string $endpoint): string
    {
        $hashInput = $challengeCode . $verificationToken . $endpoint;
        return hash('sha256', $hashInput);
    }

    /**
     * Max age (minutes) of a Platform Notification's Timestamp before it is
     * treated as a replay. eBay recommends 10 minutes.
     */
    public const SIGNATURE_MAX_AGE_MINUTES = 10;

    /**
     * Verify the NotificationSignature SOAP header of a Platform Notification.
     *
     * eBay signs each notification as base64(md5(Timestamp . DevID . AppID . CertID)),
     * where Timestamp is the <Timestamp> of the notification body. Anyone can POST
     * to the webhook, so nothing in the payload may be trusted until this passes.
     *
     * @param \SimpleXMLElement $envelope     Parsed (namespace-stripped) SOAP envelope
     * @param \SimpleXMLElement $notification The notification element inside <Body>
     * @param SalesChannel|null $channel      Channel whose app keys signed it (null = global config keys)
     */
    public function verifyPlatformNotificationSignature(
        \SimpleXMLElement $envelope,
        \SimpleXMLElement $notification,
        ?SalesChannel $channel
    ): bool {
        if ($envelope->getName() !== 'Envelope') {
            return false;
        }

        $signature = trim((string) ($envelope->Header->RequesterCredentials->NotificationSignature ?? ''));
        $timestamp = trim((string) ($notification->Timestamp ?? ''));

        if ($signature === '' || $timestamp === '') {
            return false;
        }

        $devId = $channel ? $channel->ebayDevId() : config('services.ebay.dev_id');
        $appId = $channel ? $channel->ebayClientId() : config('services.ebay.client_id');
        $certId = $channel ? $channel->ebayClientSecret() : config('services.ebay.client_secret');

        if (empty($devId) || empty($appId) || empty($certId)) {
            Log::channel('ebay')->error('eBay notification signature cannot be verified: DevID/AppID/CertID not configured (set EBAY_DEV_ID)', [
                'sales_channel_id' => $channel?->id,
            ]);
            return false;
        }

        $expected = base64_encode(md5($timestamp . $devId . $appId . $certId, true));

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        try {
            $sentAt = \Illuminate\Support\Carbon::parse($timestamp);
        } catch (Exception $e) {
            return false;
        }

        return abs(now()->diffInMinutes($sentAt, false)) <= self::SIGNATURE_MAX_AGE_MINUTES;
    }
}
