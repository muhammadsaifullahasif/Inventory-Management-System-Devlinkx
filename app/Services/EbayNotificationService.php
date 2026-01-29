<?php

namespace App\Services;

use Exception;
use App\Models\SalesChannel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayNotificationService
{
    private const EBAY_TRADING_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const EBAY_NOTIFICATION_API_URL = 'https://api.ebay.com/commerce/notification/v1';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

    /**
     * All available eBay Platform Notification events organized by category
     */
    public const ALL_NOTIFICATION_EVENTS = [
        // Order & Transaction Events
        'FixedPriceTransaction',        // When buyer purchases fixed-price item
        'AuctionCheckoutComplete',      // When checkout is complete
        'ItemSold',                     // When listing ends in sale
        'ItemMarkedShipped',            // When seller marks item shipped
        'ItemMarkedPaid',               // When payment is confirmed
        'ItemReadyForPickup',           // When order is ready for pickup
        'BuyerCancelRequested',         // When buyer requests cancellation
        'CheckoutBuyerRequestsTotal',   // When buyer requests total before paying
        'PaymentReminder',              // When payment is still due

        // Auction Events
        'EndOfAuction',                 // When auction listing ends
        'BidPlaced',                    // When buyer places a bid
        'BidReceived',                  // When seller receives a bid
        'OutBid',                       // When buyer is outbid
        'ItemWon',                      // When buyer wins an auction
        'ItemLost',                     // When buyer doesn't win an auction
        'BidItemEndingSoon',            // When auction is ending soon
        'SecondChanceOffer',            // When buyer receives second chance offer

        // Best Offer Events
        'BestOffer',                    // When buyer makes a Best Offer
        'BestOfferPlaced',              // When Best Offer is submitted
        'BestOfferDeclined',            // When seller rejects Best Offer
        'CounterOfferReceived',         // When seller makes counter offer

        // Listing Events
        'ItemListed',                   // When item is listed
        'ItemRevised',                  // When listing is revised
        'ItemRevisedAddCharity',        // When charity is added to listing
        'ItemExtended',                 // When listing duration is extended
        'ItemClosed',                   // When listing ends
        'ItemUnsold',                   // When auction ends without winner
        'ItemSuspended',                // When listing is suspended
        'ItemOutOfStock',               // When fixed-price item goes out of stock

        // Watch List Events
        'ItemAddedToWatchList',         // When buyer adds item to watch list
        'ItemRemovedFromWatchList',     // When buyer removes item from watch list
        'WatchedItemEndingSoon',        // When watched item is ending soon
        'ShoppingCartItemEndingSoon',   // When cart item is ending soon

        // Feedback Events
        'Feedback',                     // When feedback is left
        'FeedbackLeft',                 // When user leaves feedback
        'FeedbackReceived',             // When user receives feedback
        'FeedbackStarChanged',          // When feedback star level changes

        // Message Events (Note: Header events conflict with full message events, so only using full versions)
        'AskSellerQuestion',            // When buyer asks a question
        'MyMessageseBayMessage',        // When eBay sends a message (includes full content)
        'MyMessagesM2MMessage',         // Member-to-member messages (includes full content)
        'MyMessagesHighPriorityMessage', // High priority messages (includes full content)
        'M2MMessageStatusChange',       // When message status changes

        // Return Events
        'ReturnCreated',                // When return is created
        'ReturnShipped',                // When return item is shipped
        'ReturnDelivered',              // When return item is delivered
        'ReturnClosed',                 // When return is closed
        'ReturnEscalated',              // When return escalates to case
        'ReturnRefundOverdue',          // When refund is overdue
        'ReturnSellerInfoOverdue',      // When seller info is overdue
        'ReturnWaitingForSellerInfo',   // When waiting for seller info

        // eBay Money Back Guarantee Events
        'EBPClosedCase',                // When case is closed
        'EBPEscalatedCase',             // When case is escalated
        'EBPMyResponseDue',             // When response is due
        'EBPOtherPartyResponseDue',     // When other party must respond
        'EBPAppealedCase',              // When case is appealed
        'EBPClosedAppeal',              // When appeal is closed
        'EBPOnHoldCase',                // When case is on hold
        'EBPMyPaymentDue',              // When payment is due
        'EBPPaymentDone',               // When payment is processed

        // Item Not Received Events
        'INRBuyerRespondedToDispute',   // When buyer responds to INR
        'OrderInquiryReminderForEscalation', // Reminder for escalation

        // Account Events
        'TokenRevocation',              // When auth token is revoked
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
     * Subscribe to Platform Notifications (Trading API)
     */
    public function subscribeToPlatformNotifications(SalesChannel $salesChannel, array $events = null): array
    {
        $events = $events ?? self::ALL_NOTIFICATION_EVENTS;
        $webhookUrl = $this->getWebhookUrl($salesChannel);

        // Build UserDeliveryPreferenceArray
        $notificationEnableXml = '';
        foreach ($events as $event) {
            $notificationEnableXml .= '
                <NotificationEnable>
                    <EventType>' . $event . '</EventType>
                    <EventEnable>Enable</EventEnable>
                </NotificationEnable>';
        }

        $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
            <SetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ApplicationDeliveryPreferences>
                    <AlertEnable>Enable</AlertEnable>
                    <ApplicationEnable>Enable</ApplicationEnable>
                    <ApplicationURL>' . htmlspecialchars($webhookUrl) . '</ApplicationURL>
                    <DeviceType>Platform</DeviceType>
                </ApplicationDeliveryPreferences>
                <UserDeliveryPreferenceArray>' . $notificationEnableXml . '
                </UserDeliveryPreferenceArray>
            </SetNotificationPreferencesRequest>';

        try {
            $response = $this->callTradingApi($salesChannel, 'SetNotificationPreferences', $xmlRequest);
            $xml = simplexml_load_string($response);

            if ((string) $xml->Ack === 'Failure') {
                $errorMsg = (string) ($xml->Errors->LongMessage ?? $xml->Errors->ShortMessage ?? 'Unknown error');
                throw new Exception("eBay SetNotificationPreferences failed: {$errorMsg}");
            }

            // Update sales channel
            $salesChannel->update([
                'platform_notifications_enabled' => true,
                'platform_notification_events' => $events,
                'webhook_url' => $webhookUrl,
            ]);

            Log::channel('ebay')->info('eBay Platform Notifications subscribed', [
                'sales_channel_id' => $salesChannel->id,
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
        } catch (Exception $e) {
            Log::channel('ebay')->error('eBay Platform Notifications subscription failed', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Subscribe to all available notification events
     */
    public function subscribeToAllEvents(SalesChannel $salesChannel): array
    {
        return $this->subscribeToPlatformNotifications($salesChannel, self::ALL_NOTIFICATION_EVENTS);
    }

    /**
     * Subscribe to order-related events only
     */
    public function subscribeToOrderEvents(SalesChannel $salesChannel): array
    {
        return $this->subscribeToPlatformNotifications($salesChannel, self::ORDER_EVENTS);
    }

    /**
     * Get current notification preferences
     */
    public function getNotificationPreferences(SalesChannel $salesChannel): array
    {
        $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
            <GetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <PreferenceLevel>User</PreferenceLevel>
            </GetNotificationPreferencesRequest>';

        try {
            $response = $this->callTradingApi($salesChannel, 'GetNotificationPreferences', $xmlRequest);
            $xml = simplexml_load_string($response);

            if ((string) $xml->Ack === 'Failure') {
                $errorMsg = (string) ($xml->Errors->LongMessage ?? $xml->Errors->ShortMessage ?? 'Unknown error');
                throw new Exception("eBay GetNotificationPreferences failed: {$errorMsg}");
            }

            $preferences = [
                'application_delivery_preferences' => [],
                'user_delivery_preferences' => [],
                'enabled_events' => [],
            ];

            if (isset($xml->ApplicationDeliveryPreferences)) {
                $adp = $xml->ApplicationDeliveryPreferences;
                $preferences['application_delivery_preferences'] = [
                    'application_url' => (string) ($adp->ApplicationURL ?? ''),
                    'application_enable' => (string) ($adp->ApplicationEnable ?? ''),
                    'alert_enable' => (string) ($adp->AlertEnable ?? ''),
                    'device_type' => (string) ($adp->DeviceType ?? ''),
                ];
            }

            if (isset($xml->UserDeliveryPreferenceArray->NotificationEnable)) {
                foreach ($xml->UserDeliveryPreferenceArray->NotificationEnable as $pref) {
                    $eventType = (string) $pref->EventType;
                    $eventEnable = (string) $pref->EventEnable;

                    $preferences['user_delivery_preferences'][] = [
                        'event_type' => $eventType,
                        'event_enable' => $eventEnable,
                    ];

                    if ($eventEnable === 'Enable') {
                        $preferences['enabled_events'][] = $eventType;
                    }
                }
            }

            return [
                'success' => true,
                'preferences' => $preferences,
            ];
        } catch (Exception $e) {
            Log::channel('ebay')->error('eBay GetNotificationPreferences failed', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Disable Platform Notifications
     */
    public function disablePlatformNotifications(SalesChannel $salesChannel): array
    {
        $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
            <SetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ApplicationDeliveryPreferences>
                    <ApplicationEnable>Disable</ApplicationEnable>
                </ApplicationDeliveryPreferences>
            </SetNotificationPreferencesRequest>';

        try {
            $response = $this->callTradingApi($salesChannel, 'SetNotificationPreferences', $xmlRequest);
            $xml = simplexml_load_string($response);

            if ((string) $xml->Ack === 'Failure') {
                $errorMsg = (string) ($xml->Errors->LongMessage ?? $xml->Errors->ShortMessage ?? 'Unknown error');
                throw new Exception("eBay disable notifications failed: {$errorMsg}");
            }

            $salesChannel->update([
                'platform_notifications_enabled' => false,
            ]);

            Log::channel('ebay')->info('eBay Platform Notifications disabled', [
                'sales_channel_id' => $salesChannel->id,
            ]);

            return [
                'success' => true,
                'message' => 'Platform notifications disabled',
            ];
        } catch (Exception $e) {
            Log::channel('ebay')->error('eBay disable notifications failed', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the webhook URL for a sales channel
     */
    public function getWebhookUrl(SalesChannel $salesChannel): string
    {
        return rtrim(config('app.url'), '/') . '/api/ebay/webhook/' . $salesChannel->id;
    }

    /**
     * Verify webhook challenge from eBay (Commerce Notification API)
     */
    public function verifyChallengeCode(string $challengeCode, string $verificationToken, string $endpoint): string
    {
        $hashInput = $challengeCode . $verificationToken . $endpoint;
        return hash('sha256', $hashInput);
    }

    /**
     * Call eBay Trading API
     */
    private function callTradingApi(SalesChannel $salesChannel, string $callName, string $xmlRequest): string
    {
        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->withHeaders([
                'X-EBAY-API-SITEID' => self::API_SITE_ID,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => self::API_COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME' => $callName,
                'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                'Content-Type' => 'text/xml',
            ])
            ->withBody($xmlRequest, 'text/xml')
            ->post(self::EBAY_TRADING_API_URL);

        if ($response->failed()) {
            Log::channel('ebay')->error("eBay {$callName} Failed", [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
                'response' => $response->body(),
            ]);
            throw new Exception("eBay {$callName} failed: " . $response->body());
        }

        return $response->body();
    }
}
