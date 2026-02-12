<?php

namespace App\Services\Ebay;

use App\Models\SalesChannel;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Business operations for eBay listings, inventory, and orders.
 *
 * Uses EbayApiClient for API transport and EbayXmlBuilder for XML construction.
 * All methods return PHP arrays (never XML).
 *
 * Usage:
 *   $service = app(EbayService::class);
 *   $channel = app(EbayApiClient::class)->ensureValidToken($salesChannel);
 *   $listings = $service->getActiveListings($channel);
 */
class EbayService
{
    public function __construct(
        private EbayApiClient $client,
    ) {}

    // =========================================
    // LISTINGS - QUERIES
    // =========================================

    /**
     * Get active listings (paginated).
     */
    public function getActiveListings(SalesChannel $channel, int $page = 1, int $perPage = 100): array
    {
        $endTimeFrom = gmdate('Y-m-d\TH:i:s\Z');
        $endTimeTo = gmdate('Y-m-d\TH:i:s\Z', strtotime('+120 days'));

        $xml = EbayXmlBuilder::getSellerList($endTimeFrom, $endTimeTo, $page, $perPage);
        $response = $this->client->call($channel, 'GetSellerList', $xml);
        $this->client->checkForErrors($response);

        $result = [
            'success' => true,
            'items' => [],
            'pagination' => [
                'totalEntries' => (int) ($response['PaginationResult']['TotalNumberOfEntries'] ?? 0),
                'totalPages' => (int) ($response['PaginationResult']['TotalNumberOfPages'] ?? 0),
                'pageNumber' => (int) ($response['PageNumber'] ?? 1),
            ],
        ];

        $items = self::normalizeList($response['ItemArray']['Item'] ?? []);
        foreach ($items as $item) {
            $result['items'][] = $this->parseItem($item, true);
        }

        $result['total_items'] = count($result['items']);
        return $result;
    }

    /**
     * Get ALL active listings (auto-pagination).
     */
    public function getAllActiveListings(SalesChannel $channel): array
    {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->getActiveListings($channel, $page, 200);
            $allItems = array_merge($allItems, $response['items']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Get unsold listings (paginated).
     */
    public function getUnsoldListings(SalesChannel $channel, int $page = 1, int $perPage = 100, int $days = 60): array
    {
        $xml = EbayXmlBuilder::getMyeBaySelling($page, $perPage, $days);
        $response = $this->client->call($channel, 'GetMyeBaySelling', $xml);
        $this->client->checkForErrors($response);

        $result = [
            'success' => true,
            'items' => [],
            'pagination' => [
                'totalEntries' => 0,
                'totalPages' => 0,
                'pageNumber' => 1,
            ],
        ];

        if (isset($response['UnsoldList'])) {
            $list = $response['UnsoldList'];

            if (isset($list['PaginationResult'])) {
                $result['pagination']['totalEntries'] = (int) ($list['PaginationResult']['TotalNumberOfEntries'] ?? 0);
                $result['pagination']['totalPages'] = (int) ($list['PaginationResult']['TotalNumberOfPages'] ?? 0);
            }

            $items = self::normalizeList($list['ItemArray']['Item'] ?? []);
            foreach ($items as $item) {
                $result['items'][] = $this->parseItem($item, false);
            }
        }

        $result['total_items'] = count($result['items']);
        return $result;
    }

    /**
     * Get ALL unsold listings (auto-pagination).
     */
    public function getAllUnsoldListings(SalesChannel $channel, int $days = 60): array
    {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->getUnsoldListings($channel, $page, 200, $days);
            $allItems = array_merge($allItems, $response['items']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages && $totalPages > 0);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }

    /**
     * Get single item details.
     */
    public function getItemDetails(SalesChannel $channel, string $itemId): array
    {
        $xml = EbayXmlBuilder::getItem($itemId);
        $response = $this->client->call($channel, 'GetItem', $xml);
        $this->client->checkForErrors($response);

        return [
            'success' => true,
            'item' => $this->parseItem($response['Item'], true),
        ];
    }

    /**
     * Find a listing by SKU.
     */
    public function findListingBySku(SalesChannel $channel, string $sku): ?array
    {
        $endTimeFrom = now()->subYear()->toIso8601String();
        $endTimeTo = now()->addYear()->toIso8601String();

        $xml = EbayXmlBuilder::getSellerListBySku($sku, $endTimeFrom, $endTimeTo);

        try {
            $response = $this->client->call($channel, 'GetSellerList', $xml);

            if (($response['Ack'] ?? '') === 'Failure') {
                return null;
            }

            $items = self::normalizeList($response['ItemArray']['Item'] ?? []);
            foreach ($items as $item) {
                $itemSku = $item['SKU'] ?? '';
                if (strtoupper($itemSku) === strtoupper($sku)) {
                    return [
                        'ItemID' => $item['ItemID'] ?? '',
                        'Title' => $item['Title'] ?? '',
                        'SKU' => $itemSku,
                        'ListingStatus' => $item['SellingStatus']['ListingStatus'] ?? '',
                        'CurrentPrice' => self::priceValue($item['SellingStatus']['CurrentPrice'] ?? null),
                        'Quantity' => (int) ($item['Quantity'] ?? 0),
                        'QuantityAvailable' => (int) ($item['QuantityAvailable'] ?? 0),
                    ];
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to find listing by SKU', ['sku' => $sku, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // =========================================
    // LISTINGS - CRUD
    // =========================================

    /**
     * Add a fixed price item (create new listing).
     */
    public function addFixedPriceItem(SalesChannel $channel, array $data): array
    {
        $xml = EbayXmlBuilder::addFixedPriceItem($data);
        $response = $this->client->call($channel, 'AddFixedPriceItem', $xml);
        return $this->parseMutationResponse($response);
    }

    /**
     * Revise a fixed price item (update existing listing).
     */
    public function reviseFixedPriceItem(SalesChannel $channel, string $itemId, array $data): array
    {
        $xml = EbayXmlBuilder::reviseFixedPriceItem($itemId, $data);
        $response = $this->client->call($channel, 'ReviseFixedPriceItem', $xml);
        return $this->parseMutationResponse($response);
    }

    /**
     * Revise item via ReviseItem API (supports more fields).
     */
    public function reviseItem(SalesChannel $channel, string $itemId, array $fields): array
    {
        $xml = EbayXmlBuilder::reviseItem($itemId, $fields);
        $response = $this->client->call($channel, 'ReviseItem', $xml);
        return $this->parseMutationResponse($response);
    }

    /**
     * End a fixed price item (end/deactivate listing).
     */
    public function endFixedPriceItem(SalesChannel $channel, string $itemId, string $reason = 'NotAvailable'): array
    {
        $xml = EbayXmlBuilder::endFixedPriceItem($itemId, $reason);
        $response = $this->client->call($channel, 'EndFixedPriceItem', $xml);

        $errorsWarnings = $this->client->extractErrorsAndWarnings($response);

        return [
            'success' => in_array($response['Ack'] ?? '', ['Success', 'Warning']),
            'end_time' => $response['EndTime'] ?? '',
            'errors' => $errorsWarnings['errors'],
        ];
    }

    /**
     * Relist a fixed price item (reactivate ended listing).
     */
    public function relistFixedPriceItem(SalesChannel $channel, string $itemId, array $data = []): array
    {
        $xml = EbayXmlBuilder::relistFixedPriceItem($itemId, $data);
        $response = $this->client->call($channel, 'RelistFixedPriceItem', $xml);
        return $this->parseMutationResponse($response);
    }

    // =========================================
    // INVENTORY
    // =========================================

    /**
     * Revise inventory status (quantity and/or price only).
     * More efficient than ReviseFixedPriceItem for inventory updates.
     */
    public function reviseInventoryStatus(SalesChannel $channel, string $itemId, int $quantity, ?float $price = null): array
    {
        $xml = EbayXmlBuilder::reviseInventoryStatus($itemId, $quantity, $price);
        $response = $this->client->call($channel, 'ReviseInventoryStatus', $xml);

        $errorsWarnings = $this->client->extractErrorsAndWarnings($response);
        $status = $response['InventoryStatus'] ?? [];

        return [
            'success' => in_array($response['Ack'] ?? '', ['Success', 'Warning']),
            'item_id' => $status['ItemID'] ?? '',
            'sku' => $status['SKU'] ?? '',
            'quantity' => isset($status['Quantity']) ? (int) $status['Quantity'] : null,
            'start_price' => isset($status['StartPrice']) ? self::priceValue($status['StartPrice']) : null,
            'warnings' => $errorsWarnings['warnings'],
            'errors' => $errorsWarnings['errors'],
        ];
    }

    // =========================================
    // ORDERS
    // =========================================

    /**
     * Get orders (paginated).
     */
    public function getOrders(SalesChannel $channel, string $createTimeFrom, string $createTimeTo, int $page = 1, int $perPage = 100): array
    {
        $xml = EbayXmlBuilder::getOrders($createTimeFrom, $createTimeTo, $page, $perPage);
        $response = $this->client->call($channel, 'GetOrders', $xml);
        $this->client->checkForErrors($response);

        $result = [
            'success' => true,
            'orders' => [],
            'pagination' => [
                'totalEntries' => (int) ($response['PaginationResult']['TotalNumberOfEntries'] ?? 0),
                'totalPages' => (int) ($response['PaginationResult']['TotalNumberOfPages'] ?? 0),
                'pageNumber' => (int) ($response['PageNumber'] ?? 1),
            ],
        ];

        $orders = self::normalizeList($response['OrderArray']['Order'] ?? []);
        foreach ($orders as $order) {
            $result['orders'][] = $this->parseOrder($order);
        }

        return $result;
    }

    /**
     * Get ALL orders (auto-pagination).
     */
    public function getAllOrders(SalesChannel $channel, string $createTimeFrom, string $createTimeTo): array
    {
        $allOrders = [];
        $page = 1;

        do {
            $response = $this->getOrders($channel, $createTimeFrom, $createTimeTo, $page, 100);
            $allOrders = array_merge($allOrders, $response['orders']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_orders' => count($allOrders),
            'orders' => $allOrders,
        ];
    }

    /**
     * Complete a sale with shipment tracking.
     */
    public function completeSale(
        SalesChannel $channel,
        string $itemId,
        string $transactionId,
        string $shippingCarrier,
        string $trackingNumber,
        bool $shipped = true
    ): array {
        $xml = EbayXmlBuilder::completeSale($itemId, $transactionId, $shippingCarrier, $trackingNumber, $shipped);
        $response = $this->client->call($channel, 'CompleteSale', $xml);

        $errorsWarnings = $this->client->extractErrorsAndWarnings($response);

        return [
            'success' => in_array($response['Ack'] ?? '', ['Success', 'Warning']),
            'warnings' => $errorsWarnings['warnings'],
            'errors' => $errorsWarnings['errors'],
        ];
    }

    /**
     * Complete a sale by OrderID (for multi-item orders).
     */
    public function completeSaleByOrderId(
        SalesChannel $channel,
        string $orderId,
        string $shippingCarrier,
        string $trackingNumber,
        bool $shipped = true
    ): array {
        $xml = EbayXmlBuilder::completeSaleByOrderId($orderId, $shippingCarrier, $trackingNumber, $shipped);
        $response = $this->client->call($channel, 'CompleteSale', $xml);

        $errorsWarnings = $this->client->extractErrorsAndWarnings($response);

        return [
            'success' => in_array($response['Ack'] ?? '', ['Success', 'Warning']),
            'warnings' => $errorsWarnings['warnings'],
            'errors' => $errorsWarnings['errors'],
        ];
    }

    // =========================================
    // PARSING - ITEMS
    // =========================================

    /**
     * Parse a single item from the API response array.
     * Includes sale price detection, dimensions, and full detail parsing.
     */
    public function parseItem(array $item, bool $includeFullDetails = false): array
    {
        $currentPrice = self::priceValue(
            $item['SellingStatus']['CurrentPrice'] ?? $item['BuyItNowPrice'] ?? $item['StartPrice'] ?? null
        );
        $currency = self::priceCurrency($item['SellingStatus']['CurrentPrice'] ?? null);
        $startPrice = self::priceValue($item['StartPrice'] ?? null);

        // Sale price detection
        $regularPrice = $currentPrice;
        $salePrice = null;
        $isOnSale = false;

        // Method 1: DiscountPriceInfo (most reliable)
        if (isset($item['DiscountPriceInfo'])) {
            $dpi = $item['DiscountPriceInfo'];
            if (isset($dpi['OriginalRetailPrice'])) {
                $regularPrice = self::priceValue($dpi['OriginalRetailPrice']);
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
            if (isset($dpi['MinimumAdvertisedPrice'])) {
                $regularPrice = self::priceValue($dpi['MinimumAdvertisedPrice']);
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
            $pricingTreatment = $dpi['PricingTreatment'] ?? '';
            if (in_array($pricingTreatment, ['STP', 'MAP'])) {
                $isOnSale = true;
            }
        }

        // Method 2: ListingDetails promotional sale
        if (!$isOnSale && isset($item['ListingDetails']['StartPrice'])) {
            $listingStartPrice = self::priceValue($item['ListingDetails']['StartPrice']);
            if ($listingStartPrice > $currentPrice) {
                $regularPrice = $listingStartPrice;
                $salePrice = $currentPrice;
                $isOnSale = true;
            }
        }

        // Method 3: PromotionalSaleDetails
        if (!$isOnSale && isset($item['SellingStatus']['PromotionalSaleDetails']['OriginalPrice'])) {
            $regularPrice = self::priceValue($item['SellingStatus']['PromotionalSaleDetails']['OriginalPrice']);
            $salePrice = $currentPrice;
            $isOnSale = true;
        }

        // Method 4: StartPrice > CurrentPrice
        if (!$isOnSale && $startPrice > 0 && $startPrice > $currentPrice) {
            $regularPrice = $startPrice;
            $salePrice = $currentPrice;
            $isOnSale = true;
        }

        $parsed = [
            'item_id' => $item['ItemID'] ?? '',
            'title' => $item['Title'] ?? '',
            'sku' => $item['SKU'] ?? '',
            'price' => [
                'value' => $currentPrice,
                'currency' => $currency,
            ],
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $currency,
            ],
            'sale_price' => $salePrice !== null ? [
                'value' => $salePrice,
                'currency' => $currency,
            ] : null,
            'is_on_sale' => $isOnSale,
            'start_price' => $startPrice,
            'buy_it_now_price' => self::priceValue($item['BuyItNowPrice'] ?? null),
            'reserve_price' => self::priceValue($item['ReservePrice'] ?? null),
            'quantity' => (int) ($item['Quantity'] ?? 0),
            'quantity_available' => (int) ($item['QuantityAvailable'] ?? 0),
            'quantity_sold' => (int) ($item['SellingStatus']['QuantitySold'] ?? 0),
            'condition' => $item['ConditionDisplayName'] ?? '',
            'condition_id' => $item['ConditionID'] ?? '',
            'category' => [
                'id' => $item['PrimaryCategory']['CategoryID'] ?? '',
                'name' => $item['PrimaryCategory']['CategoryName'] ?? '',
            ],
            'listing_type' => $item['ListingType'] ?? '',
            'listing_status' => $item['SellingStatus']['ListingStatus'] ?? '',
            'listing_url' => $item['ListingDetails']['ViewItemURL'] ?? '',
            'start_time' => $item['ListingDetails']['StartTime'] ?? '',
            'end_time' => $item['ListingDetails']['EndTime'] ?? '',
            'images' => $this->parseImages($item),
            'dimensions' => $this->parseDimensions($item),
        ];

        if ($includeFullDetails) {
            $parsed['description'] = $item['Description'] ?? '';
            $parsed['location'] = $item['Location'] ?? '';
            $parsed['country'] = $item['Country'] ?? '';
            $parsed['watch_count'] = (int) ($item['WatchCount'] ?? 0);
            $parsed['item_specifics'] = $this->parseItemSpecifics($item);
            $parsed['variations'] = $this->parseVariations($item);
            $parsed['shipping_details'] = $this->parseShippingDetails($item);
            $parsed['return_policy'] = $this->parseReturnPolicy($item);
        }

        return $parsed;
    }

    /**
     * Parse images from item array.
     */
    private function parseImages(array $item): array
    {
        if (!isset($item['PictureDetails']['PictureURL'])) {
            return [];
        }

        $urls = $item['PictureDetails']['PictureURL'];

        return is_string($urls) ? [$urls] : (is_array($urls) ? $urls : []);
    }

    /**
     * Parse item dimensions from ShippingPackageDetails.
     */
    private function parseDimensions(array $item): array
    {
        $dimensions = [
            'weight' => null,
            'weight_unit' => null,
            'length' => null,
            'width' => null,
            'height' => null,
            'dimension_unit' => null,
        ];

        // Check ShippingPackageDetails
        if (isset($item['ShippingPackageDetails'])) {
            $pkg = $item['ShippingPackageDetails'];

            if (isset($pkg['WeightMajor'])) {
                $weightMajor = self::priceValue($pkg['WeightMajor']);
                $weightMinor = self::priceValue($pkg['WeightMinor'] ?? null);
                $dimensions['weight'] = $weightMajor + ($weightMinor / 16);
                $dimensions['weight_unit'] = is_array($pkg['WeightMajor']) ? ($pkg['WeightMajor']['@unit'] ?? 'lbs') : 'lbs';
            }

            if (isset($pkg['PackageDepth'])) {
                $dimensions['length'] = self::priceValue($pkg['PackageDepth']);
                $dimensions['dimension_unit'] = is_array($pkg['PackageDepth']) ? ($pkg['PackageDepth']['@unit'] ?? 'inches') : 'inches';
            }
            if (isset($pkg['PackageWidth'])) {
                $dimensions['width'] = self::priceValue($pkg['PackageWidth']);
            }
            if (isset($pkg['PackageLength'])) {
                $dimensions['height'] = self::priceValue($pkg['PackageLength']);
            }
        }

        // Fallback to CalculatedShippingRate
        if (isset($item['ShippingDetails']['CalculatedShippingRate'])) {
            $calc = $item['ShippingDetails']['CalculatedShippingRate'];

            if ($dimensions['weight'] === null && isset($calc['WeightMajor'])) {
                $weightMajor = self::priceValue($calc['WeightMajor']);
                $weightMinor = self::priceValue($calc['WeightMinor'] ?? null);
                $dimensions['weight'] = $weightMajor + ($weightMinor / 16);
                $dimensions['weight_unit'] = is_array($calc['WeightMajor']) ? ($calc['WeightMajor']['@unit'] ?? 'lbs') : 'lbs';
            }

            if ($dimensions['length'] === null && isset($calc['PackageDepth'])) {
                $dimensions['length'] = self::priceValue($calc['PackageDepth']);
                $dimensions['dimension_unit'] = is_array($calc['PackageDepth']) ? ($calc['PackageDepth']['@unit'] ?? 'inches') : 'inches';
            }
            if ($dimensions['width'] === null && isset($calc['PackageWidth'])) {
                $dimensions['width'] = self::priceValue($calc['PackageWidth']);
            }
            if ($dimensions['height'] === null && isset($calc['PackageLength'])) {
                $dimensions['height'] = self::priceValue($calc['PackageLength']);
            }
        }

        return $dimensions;
    }

    /**
     * Parse item specifics.
     */
    private function parseItemSpecifics(array $item): array
    {
        $specifics = [];
        $nameValueLists = self::normalizeList($item['ItemSpecifics']['NameValueList'] ?? []);

        foreach ($nameValueLists as $spec) {
            $name = $spec['Name'] ?? '';
            $value = $spec['Value'] ?? '';
            $specifics[$name] = is_array($value) ? (count($value) === 1 ? $value[0] : $value) : $value;
        }

        return $specifics;
    }

    /**
     * Parse variations.
     */
    private function parseVariations(array $item): array
    {
        $variations = [];
        $varList = self::normalizeList($item['Variations']['Variation'] ?? []);

        foreach ($varList as $var) {
            $variation = [
                'sku' => $var['SKU'] ?? '',
                'quantity' => (int) ($var['Quantity'] ?? 0),
                'quantity_sold' => (int) ($var['SellingStatus']['QuantitySold'] ?? 0),
                'price' => self::priceValue($var['StartPrice'] ?? null),
                'specifics' => [],
            ];

            $specsList = self::normalizeList($var['VariationSpecifics']['NameValueList'] ?? []);
            foreach ($specsList as $spec) {
                $variation['specifics'][$spec['Name'] ?? ''] = $spec['Value'] ?? '';
            }

            $variations[] = $variation;
        }

        return $variations;
    }

    /**
     * Parse shipping details.
     */
    private function parseShippingDetails(array $item): array
    {
        if (!isset($item['ShippingDetails'])) {
            return [];
        }

        $sd = $item['ShippingDetails'];

        $details = [
            'shipping_type' => $sd['ShippingType'] ?? '',
            'global_shipping' => ($sd['GlobalShipping'] ?? 'false') === 'true',
            'services' => [],
        ];

        $services = self::normalizeList($sd['ShippingServiceOptions'] ?? []);
        foreach ($services as $svc) {
            $details['services'][] = [
                'service' => $svc['ShippingService'] ?? '',
                'cost' => self::priceValue($svc['ShippingServiceCost'] ?? null),
                'free_shipping' => ($svc['FreeShipping'] ?? 'false') === 'true',
            ];
        }

        return $details;
    }

    /**
     * Parse return policy.
     */
    private function parseReturnPolicy(array $item): array
    {
        if (!isset($item['ReturnPolicy'])) {
            return [];
        }

        $rp = $item['ReturnPolicy'];

        return [
            'returns_accepted' => $rp['ReturnsAcceptedOption'] ?? '',
            'returns_within' => $rp['ReturnsWithinOption'] ?? '',
            'refund' => $rp['RefundOption'] ?? '',
            'shipping_cost_paid_by' => $rp['ShippingCostPaidByOption'] ?? '',
        ];
    }

    // =========================================
    // PARSING - ORDERS
    // =========================================

    /**
     * Parse a single order from the API response array.
     */
    public function parseOrder(array $order): array
    {
        $transactions = self::normalizeList($order['TransactionArray']['Transaction'] ?? []);

        $buyer = [
            'username' => $order['BuyerUserID'] ?? '',
            'email' => $transactions[0]['Buyer']['Email'] ?? '',
        ];

        $shippingAddress = [];
        if (isset($order['ShippingAddress'])) {
            $addr = $order['ShippingAddress'];
            $shippingAddress = [
                'name' => $addr['Name'] ?? '',
                'street1' => $addr['Street1'] ?? '',
                'street2' => $addr['Street2'] ?? '',
                'city' => $addr['CityName'] ?? '',
                'state' => $addr['StateOrProvince'] ?? '',
                'postal_code' => $addr['PostalCode'] ?? '',
                'country' => $addr['Country'] ?? '',
                'phone' => $addr['Phone'] ?? '',
            ];
        }

        $lineItems = [];
        foreach ($transactions as $tx) {
            $txItem = $tx['Item'] ?? [];
            $lineItems[] = [
                'item_id' => $txItem['ItemID'] ?? '',
                'transaction_id' => $tx['TransactionID'] ?? '',
                'line_item_id' => $tx['OrderLineItemID'] ?? '',
                'sku' => $txItem['SKU'] ?? $tx['Variation']['SKU'] ?? '',
                'title' => $txItem['Title'] ?? '',
                'quantity' => (int) ($tx['QuantityPurchased'] ?? 1),
                'unit_price' => self::priceValue($tx['TransactionPrice'] ?? null),
                'variation_attributes' => $this->parseVariationAttributes($tx),
            ];
        }

        // Handle single or multiple tracking entries
        $trackingDetails = $order['ShippingDetails']['ShipmentTrackingDetails'] ?? [];
        if (isset($trackingDetails[0])) {
            $trackingDetails = $trackingDetails[0]; // Use first tracking entry
        }

        return [
            'order_id' => $order['OrderID'] ?? '',
            'order_status' => $order['OrderStatus'] ?? '',
            'payment_status' => $order['CheckoutStatus']['eBayPaymentStatus'] ?? '',
            'checkout_status' => $order['CheckoutStatus']['Status'] ?? '',
            'cancel_status' => $order['CancelStatus'] ?? '',
            'buyer' => $buyer,
            'shipping_address' => $shippingAddress,
            'line_items' => $lineItems,
            'subtotal' => self::priceValue($order['Subtotal'] ?? null),
            'shipping_cost' => self::priceValue($order['ShippingServiceSelected']['ShippingServiceCost'] ?? null),
            'total' => self::priceValue($order['Total'] ?? null),
            'currency' => self::priceCurrency($order['Total'] ?? null),
            'created_time' => $order['CreatedTime'] ?? '',
            'paid_time' => $order['PaidTime'] ?? '',
            'shipped_time' => $order['ShippedTime'] ?? '',
            'tracking_number' => $trackingDetails['ShipmentTrackingNumber'] ?? '',
            'shipping_carrier' => $trackingDetails['ShippingCarrierUsed'] ?? '',
            'pickup_status' => $order['PickupDetails']['PickupStatus'] ?? '',
            'raw_data' => $order,
        ];
    }

    /**
     * Parse variation attributes from a transaction.
     */
    private function parseVariationAttributes(array $transaction): ?array
    {
        if (!isset($transaction['Variation']['VariationSpecifics']['NameValueList'])) {
            return null;
        }

        $attributes = [];
        $specsList = self::normalizeList($transaction['Variation']['VariationSpecifics']['NameValueList']);
        foreach ($specsList as $spec) {
            $attributes[$spec['Name'] ?? ''] = $spec['Value'] ?? '';
        }

        return $attributes;
    }

    // =========================================
    // PARSING - MUTATION RESPONSES
    // =========================================

    /**
     * Parse an Add/Revise/Relist item response.
     */
    private function parseMutationResponse(array $response): array
    {
        $errorsWarnings = $this->client->extractErrorsAndWarnings($response);

        $result = [
            'success' => in_array($response['Ack'] ?? '', ['Success', 'Warning']),
            'item_id' => $response['ItemID'] ?? '',
            'start_time' => $response['StartTime'] ?? '',
            'end_time' => $response['EndTime'] ?? '',
            'fees' => [],
            'warnings' => $errorsWarnings['warnings'],
            'errors' => $errorsWarnings['errors'],
        ];

        $fees = self::normalizeList($response['Fees']['Fee'] ?? []);
        foreach ($fees as $fee) {
            $feeName = $fee['Name'] ?? '';
            $feeValue = self::priceValue($fee['Fee'] ?? null);
            if ($feeName) {
                $result['fees'][$feeName] = $feeValue;
            }
        }

        if ($result['success'] && !empty($result['item_id'])) {
            $result['listing_url'] = "https://www.ebay.com/itm/{$result['item_id']}";
        }

        return $result;
    }

    // =========================================
    // UTILITY HELPERS
    // =========================================

    /**
     * Normalize a potentially-single item to always be a list.
     * Handles: null/empty → [], string → [string], {assoc} → [{assoc}], [list] → [list]
     */
    public static function normalizeList(mixed $value): array
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return [];
        }
        if (is_string($value)) {
            return [$value];
        }
        if (is_array($value) && !isset($value[0])) {
            return [$value];
        }
        return $value;
    }

    /**
     * Extract numeric value from a price/measurement node.
     * Handles both string '188.57' and array ['@currencyID' => 'USD', '@value' => '188.57'].
     */
    public static function priceValue(mixed $node): float
    {
        if ($node === null) {
            return 0.0;
        }
        if (is_array($node)) {
            return (float) ($node['@value'] ?? 0);
        }
        return (float) $node;
    }

    /**
     * Extract currency from a price node.
     */
    public static function priceCurrency(mixed $node): string
    {
        if (is_array($node)) {
            return $node['@currencyID'] ?? 'USD';
        }
        return 'USD';
    }
}
