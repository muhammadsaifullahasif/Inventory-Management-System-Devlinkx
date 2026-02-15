<?php

namespace App\Services\Ebay;

/**
 * Centralized XML request construction for eBay Trading API.
 *
 * Single source of truth for all XML request templates.
 * All methods are static — no state, just XML generation.
 *
 * Usage:
 *   $xml = EbayXmlBuilder::getSellerList($endTimeFrom, $endTimeTo, 1, 100);
 *   $response = $client->call($channel, 'GetSellerList', $xml);
 */
class EbayXmlBuilder
{
    // =========================================
    // LISTING QUERIES
    // =========================================

    /**
     * Build GetSellerList request XML (for fetching active listings).
     */
    public static function getSellerList(
        string $endTimeFrom,
        string $endTimeTo,
        int $page = 1,
        int $perPage = 100,
        string $granularity = 'Fine'
    ): string {
        return '<?xml version="1.0" encoding="utf-8"?>
            <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <DetailLevel>ReturnAll</DetailLevel>
                <EndTimeFrom>' . $endTimeFrom . '</EndTimeFrom>
                <EndTimeTo>' . $endTimeTo . '</EndTimeTo>
                <IncludeWatchCount>true</IncludeWatchCount>
                <Pagination>
                    <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                    <PageNumber>' . $page . '</PageNumber>
                </Pagination>
                <GranularityLevel>' . $granularity . '</GranularityLevel>
            </GetSellerListRequest>';
    }

    /**
     * Build GetSellerList request XML filtered by SKU.
     */
    public static function getSellerListBySku(
        string $sku,
        string $endTimeFrom,
        string $endTimeTo
    ): string {
        $escapedSku = self::escape($sku);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <SKUArray>
        <SKU>{$escapedSku}</SKU>
    </SKUArray>
    <EndTimeFrom>{$endTimeFrom}</EndTimeFrom>
    <EndTimeTo>{$endTimeTo}</EndTimeTo>
    <IncludeVariations>true</IncludeVariations>
    <Pagination>
        <EntriesPerPage>10</EntriesPerPage>
        <PageNumber>1</PageNumber>
    </Pagination>
    <DetailLevel>ReturnAll</DetailLevel>
</GetSellerListRequest>
XML;
    }

    /**
     * Build GetMyeBaySelling request XML (for unsold listings).
     */
    public static function getMyeBaySelling(int $page = 1, int $perPage = 100, int $days = 60): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <UnsoldList>
                    <Include>true</Include>
                    <DurationInDays>' . $days . '</DurationInDays>
                    <Pagination>
                        <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                        <PageNumber>' . $page . '</PageNumber>
                    </Pagination>
                </UnsoldList>
                <DetailLevel>ReturnAll</DetailLevel>
            </GetMyeBaySellingRequest>';
    }

    /**
     * Build GetItem request XML (for full item details).
     */
    public static function getItem(string $itemId): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <DetailLevel>ReturnAll</DetailLevel>
                <ItemID>' . $itemId . '</ItemID>
                <IncludeItemSpecifics>true</IncludeItemSpecifics>
                <IncludeWatchCount>true</IncludeWatchCount>
            </GetItemRequest>';
    }

    // =========================================
    // LISTING MANAGEMENT (CRUD)
    // =========================================

    /**
     * Build AddFixedPriceItem request XML (create new listing).
     */
    public static function addFixedPriceItem(array $data): string
    {
        $title = self::escape($data['title'] ?? '');
        $description = $data['description'] ?? $data['title'] ?? '';
        $sku = self::escape($data['sku'] ?? '');
        $price = number_format((float) ($data['price'] ?? 0), 2, '.', '');
        $quantity = (int) ($data['quantity'] ?? 1);
        $categoryId = self::escape($data['category_id'] ?? '');
        $conditionId = (int) ($data['condition_id'] ?? 1000);
        $listingDuration = $data['listing_duration'] ?? 'GTC';
        $currency = $data['currency'] ?? 'USD';
        $country = $data['country'] ?? 'US';
        $location = self::escape($data['location'] ?? 'United States');
        $postalCode = self::escape($data['postal_code'] ?? '');
        $returnsAccepted = $data['returns_accepted'] ?? 'ReturnsAccepted';
        $returnWithin = $data['return_within'] ?? 'Days_30';
        $refundOption = $data['refund_option'] ?? 'MoneyBackOrReplacement';
        $shippingCostPaidBy = $data['shipping_cost_paid_by'] ?? 'Buyer';

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <Title>{$title}</Title>
        <Description><![CDATA[{$description}]]></Description>
        <SKU>{$sku}</SKU>
        <PrimaryCategory>
            <CategoryID>{$categoryId}</CategoryID>
        </PrimaryCategory>
        <StartPrice currencyID="{$currency}">{$price}</StartPrice>
        <ConditionID>{$conditionId}</ConditionID>
        <Country>{$country}</Country>
        <Currency>{$currency}</Currency>
        <DispatchTimeMax>3</DispatchTimeMax>
        <ListingDuration>{$listingDuration}</ListingDuration>
        <ListingType>FixedPriceItem</ListingType>
        <Location>{$location}</Location>
        <PostalCode>{$postalCode}</PostalCode>
        <Quantity>{$quantity}</Quantity>
        <ReturnPolicy>
            <ReturnsAcceptedOption>{$returnsAccepted}</ReturnsAcceptedOption>
            <ReturnsWithinOption>{$returnWithin}</ReturnsWithinOption>
            <RefundOption>{$refundOption}</RefundOption>
            <ShippingCostPaidByOption>{$shippingCostPaidBy}</ShippingCostPaidByOption>
        </ReturnPolicy>
        <ShippingDetails>
            <ShippingType>Flat</ShippingType>
            <ShippingServiceOptions>
                <FreeShipping>true</FreeShipping>
                <ShippingService>USPSParcel</ShippingService>
                <ShippingServicePriority>1</ShippingServicePriority>
            </ShippingServiceOptions>
        </ShippingDetails>
XML;

        if (!empty($data['picture_urls'])) {
            $xml .= "\n        <PictureDetails>";
            foreach ((array) $data['picture_urls'] as $url) {
                $xml .= "\n            <PictureURL>" . self::escape($url) . "</PictureURL>";
            }
            $xml .= "\n        </PictureDetails>";
        }

        if (!empty($data['item_specifics'])) {
            $xml .= "\n        <ItemSpecifics>";
            foreach ($data['item_specifics'] as $name => $value) {
                $xml .= "\n            <NameValueList>"
                    . "\n                <Name>" . self::escape($name) . "</Name>"
                    . "\n                <Value>" . self::escape($value) . "</Value>"
                    . "\n            </NameValueList>";
            }
            $xml .= "\n        </ItemSpecifics>";
        }

        $xml .= "\n    </Item>\n</AddFixedPriceItemRequest>";

        return $xml;
    }

    /**
     * Build ReviseFixedPriceItem request XML (update listing via FixedPrice API).
     */
    public static function reviseFixedPriceItem(string $itemId, array $data): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <ItemID>{$itemId}</ItemID>
XML;

        if (isset($data['title'])) {
            $xml .= "\n        <Title>" . self::escape($data['title']) . "</Title>";
        }

        if (isset($data['description'])) {
            $xml .= "\n        <Description><![CDATA[{$data['description']}]]></Description>";
        }

        if (isset($data['price'])) {
            $currency = $data['currency'] ?? 'USD';
            $price = number_format((float) $data['price'], 2, '.', '');
            $xml .= "\n        <StartPrice currencyID=\"{$currency}\">{$price}</StartPrice>";
        }

        if (isset($data['quantity'])) {
            $xml .= "\n        <Quantity>{$data['quantity']}</Quantity>";
        }

        if (isset($data['sku'])) {
            $xml .= "\n        <SKU>" . self::escape($data['sku']) . "</SKU>";
        }

        if (!empty($data['picture_urls'])) {
            $xml .= "\n        <PictureDetails>";
            foreach ((array) $data['picture_urls'] as $url) {
                $xml .= "\n            <PictureURL>" . self::escape($url) . "</PictureURL>";
            }
            $xml .= "\n        </PictureDetails>";
        }

        $xml .= "\n    </Item>\n</ReviseFixedPriceItemRequest>";

        return $xml;
    }

    /**
     * Build ReviseItem request XML (update listing via ReviseItem API - supports more fields).
     */
    public static function reviseItem(string $itemId, array $fields): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <Item>
                    <ItemID>' . $itemId . '</ItemID>';

        if (isset($fields['title'])) {
            $xml .= "\n                    <Title>" . self::escape($fields['title']) . "</Title>";
        }

        if (isset($fields['description'])) {
            $xml .= "\n                    <Description><![CDATA[{$fields['description']}]]></Description>";
        }

        if (isset($fields['price'])) {
            $price = number_format((float) $fields['price'], 2, '.', '');
            $currency = $fields['currency'] ?? 'USD';
            $xml .= "\n                    <StartPrice currencyID=\"{$currency}\">{$price}</StartPrice>";
        }

        if (isset($fields['quantity'])) {
            $xml .= "\n                    <Quantity>" . (int) $fields['quantity'] . "</Quantity>";
        }

        if (isset($fields['sku'])) {
            $xml .= "\n                    <SKU>" . self::escape($fields['sku']) . "</SKU>";
        }

        if (isset($fields['condition_id'])) {
            $xml .= "\n                    <ConditionID>" . (int) $fields['condition_id'] . "</ConditionID>";
        }

        // Shipping package details (weight and dimensions)
        if (isset($fields['weight']) || isset($fields['length']) || isset($fields['width']) || isset($fields['height'])) {
            $xml .= "\n                    <ShippingPackageDetails>";

            if (isset($fields['weight']) && $fields['weight'] > 0) {
                $weight = (float) $fields['weight'];
                $weightMajor = floor($weight);
                $weightMinor = round(($weight - $weightMajor) * 16); // Convert decimal lbs to ounces
                $weightUnit = $fields['weight_unit'] ?? 'lbs';
                $xml .= "\n                        <WeightMajor unit=\"{$weightUnit}\">{$weightMajor}</WeightMajor>";
                $xml .= "\n                        <WeightMinor unit=\"oz\">{$weightMinor}</WeightMinor>";
            }

            if (isset($fields['length']) && $fields['length'] > 0) {
                $dimensionUnit = $fields['dimension_unit'] ?? 'inches';
                $xml .= "\n                        <PackageLength unit=\"{$dimensionUnit}\">" . (float) $fields['length'] . "</PackageLength>";
            }

            if (isset($fields['width']) && $fields['width'] > 0) {
                $dimensionUnit = $fields['dimension_unit'] ?? 'inches';
                $xml .= "\n                        <PackageWidth unit=\"{$dimensionUnit}\">" . (float) $fields['width'] . "</PackageWidth>";
            }

            if (isset($fields['height']) && $fields['height'] > 0) {
                $dimensionUnit = $fields['dimension_unit'] ?? 'inches';
                $xml .= "\n                        <PackageDepth unit=\"{$dimensionUnit}\">" . (float) $fields['height'] . "</PackageDepth>";
            }

            $xml .= "\n                    </ShippingPackageDetails>";
        }

        $xml .= "\n                </Item>\n            </ReviseItemRequest>";

        return $xml;
    }

    /**
     * Build EndFixedPriceItem request XML (end/deactivate listing).
     */
    public static function endFixedPriceItem(string $itemId, string $reason = 'NotAvailable'): string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<EndFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ItemID>{$itemId}</ItemID>
    <EndingReason>{$reason}</EndingReason>
</EndFixedPriceItemRequest>
XML;
    }

    /**
     * Build RelistFixedPriceItem request XML (relist an ended item).
     */
    public static function relistFixedPriceItem(string $itemId, array $data = []): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Item>
        <ItemID>{$itemId}</ItemID>
XML;

        if (isset($data['title'])) {
            $xml .= "\n        <Title>" . self::escape($data['title']) . "</Title>";
        }

        if (isset($data['price'])) {
            $currency = $data['currency'] ?? 'USD';
            $price = number_format((float) $data['price'], 2, '.', '');
            $xml .= "\n        <StartPrice currencyID=\"{$currency}\">{$price}</StartPrice>";
        }

        if (isset($data['quantity'])) {
            $xml .= "\n        <Quantity>{$data['quantity']}</Quantity>";
        }

        $xml .= "\n    </Item>\n</RelistFixedPriceItemRequest>";

        return $xml;
    }

    // =========================================
    // INVENTORY
    // =========================================

    /**
     * Build ReviseInventoryStatus request XML (efficient quantity/price update).
     */
    public static function reviseInventoryStatus(string $itemId, int $quantity, ?float $price = null): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <InventoryStatus>
        <ItemID>{$itemId}</ItemID>
        <Quantity>{$quantity}</Quantity>
XML;

        if ($price !== null) {
            $formattedPrice = number_format($price, 2, '.', '');
            $xml .= "\n        <StartPrice>{$formattedPrice}</StartPrice>";
        }

        $xml .= "\n    </InventoryStatus>\n</ReviseInventoryStatusRequest>";

        return $xml;
    }

    // =========================================
    // ORDERS
    // =========================================

    /**
     * Build GetOrders request XML (fetch orders with date range and pagination).
     */
    public static function getOrders(
        string $createTimeFrom,
        string $createTimeTo,
        int $page = 1,
        int $perPage = 100,
        string $orderRole = 'Seller',
        string $orderStatus = 'All'
    ): string {
        return '<?xml version="1.0" encoding="utf-8"?>
            <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <DetailLevel>ReturnAll</DetailLevel>
                <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
                <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
                <OrderRole>' . $orderRole . '</OrderRole>
                <OrderStatus>' . $orderStatus . '</OrderStatus>
                <Pagination>
                    <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                    <PageNumber>' . $page . '</PageNumber>
                </Pagination>
            </GetOrdersRequest>';
    }

    /**
     * Build CompleteSale request XML (mark order shipped by ItemID + TransactionID).
     */
    public static function completeSale(
        string $itemId,
        string $transactionId,
        string $shippingCarrier,
        string $trackingNumber,
        bool $shipped = true
    ): string {
        $shippedValue = $shipped ? 'true' : 'false';

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<CompleteSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ItemID>{$itemId}</ItemID>
    <TransactionID>{$transactionId}</TransactionID>
    <Shipped>{$shippedValue}</Shipped>
    <Shipment>
        <ShipmentTrackingDetails>
            <ShipmentTrackingNumber>{$trackingNumber}</ShipmentTrackingNumber>
            <ShippingCarrierUsed>{$shippingCarrier}</ShippingCarrierUsed>
        </ShipmentTrackingDetails>
    </Shipment>
</CompleteSaleRequest>
XML;
    }

    /**
     * Build CompleteSale request XML (mark order shipped by OrderID).
     */
    public static function completeSaleByOrderId(
        string $orderId,
        string $shippingCarrier,
        string $trackingNumber,
        bool $shipped = true
    ): string {
        $shippedValue = $shipped ? 'true' : 'false';

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<CompleteSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <OrderID>{$orderId}</OrderID>
    <Shipped>{$shippedValue}</Shipped>
    <Shipment>
        <ShipmentTrackingDetails>
            <ShipmentTrackingNumber>{$trackingNumber}</ShipmentTrackingNumber>
            <ShippingCarrierUsed>{$shippingCarrier}</ShippingCarrierUsed>
        </ShipmentTrackingDetails>
    </Shipment>
</CompleteSaleRequest>
XML;
    }

    // =========================================
    // NOTIFICATIONS
    // =========================================

    /**
     * Build SetNotificationPreferences request XML (subscribe to events).
     */
    public static function setNotificationPreferences(string $webhookUrl, array $events): string
    {
        $notificationEnableXml = '';
        foreach ($events as $event) {
            $notificationEnableXml .= '
                <NotificationEnable>
                    <EventType>' . $event . '</EventType>
                    <EventEnable>Enable</EventEnable>
                </NotificationEnable>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>
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
    }

    /**
     * Build GetNotificationPreferences request XML.
     */
    public static function getNotificationPreferences(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <GetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <PreferenceLevel>User</PreferenceLevel>
            </GetNotificationPreferencesRequest>';
    }

    /**
     * Build SetNotificationPreferences disable request XML.
     */
    public static function disableNotifications(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <SetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ApplicationDeliveryPreferences>
                    <ApplicationEnable>Disable</ApplicationEnable>
                </ApplicationDeliveryPreferences>
            </SetNotificationPreferencesRequest>';
    }

    // =========================================
    // UTILITY
    // =========================================

    /**
     * Escape special characters for safe XML embedding.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
