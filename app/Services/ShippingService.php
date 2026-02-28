<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShippingService
{
    // -------------------------------------------------------------------------
    // Carrier resolution
    // -------------------------------------------------------------------------

    /**
     * Get the active carrier that has address validation enabled.
     * Only one carrier should have is_address_validation = true at a time.
     */
    public function getAddressValidationCarrier(): ?Shipping
    {
        return Shipping::where('is_address_validation', true)
            ->where('active_status', '1')
            ->where('delete_status', '0')
            ->first();
    }

    /**
     * Get the default carrier.
     */
    public function getDefaultCarrier(): ?Shipping
    {
        return Shipping::where('is_default', true)
            ->where('active_status', '1')
            ->where('delete_status', '0')
            ->first();
    }

    /**
     * Resolve the concrete service class for a carrier.
     * Returns null if the carrier type is unsupported.
     */
    public function resolveCarrierService(Shipping $carrier): mixed
    {
        return match (strtolower($carrier->type)) {
            'fedex' => new FedexService($carrier),
            default => null,
        };
    }

    // -------------------------------------------------------------------------
    // Address Validation
    // -------------------------------------------------------------------------

    /**
     * Validate the shipping address on an order using the enabled carrier.
     * Updates order.address_type and order.address_validated_at.
     *
     * Returns the address type string (BUSINESS|RESIDENTIAL|MIXED|UNKNOWN).
     */
    public function validateOrderAddress(Order $order): string
    {
        $carrier = $this->getAddressValidationCarrier();

        if (!$carrier) {
            Log::info('ShippingService: no carrier has address validation enabled — skipping', [
                'order_id' => $order->id,
            ]);
            return 'UNKNOWN';
        }

        $service = $this->resolveCarrierService($carrier);

        if (!$service) {
            Log::warning('ShippingService: unsupported carrier type for address validation', [
                'carrier_id'   => $carrier->id,
                'carrier_type' => $carrier->type,
                'order_id'     => $order->id,
            ]);
            return 'UNKNOWN';
        }

        $addressType = $service->validateAddress(
            street1:    $order->shipping_address_line1 ?? '',
            city:       $order->shipping_city          ?? '',
            state:      $order->shipping_state         ?? '',
            postalCode: $order->shipping_postal_code   ?? '',
            country:    $order->shipping_country       ?? 'US',
            street2:    $order->shipping_address_line2 ?? null,
        );

        $order->update([
            'address_type'         => $addressType,
            'address_validated_at' => now(),
        ]);

        Log::info('ShippingService: address validated', [
            'order_id'     => $order->id,
            'address_type' => $addressType,
            'carrier'      => $carrier->name,
        ]);

        return $addressType;
    }

    /**
     * Validate addresses for all orders that have never been validated.
     */
    public function validatePendingOrderAddresses(): void
    {
        Order::whereNull('address_validated_at')
            ->whereNotNull('shipping_address_line1')
            ->chunk(50, function ($orders) {
                foreach ($orders as $order) {
                    $this->validateOrderAddress($order);
                }
            });
    }

    // -------------------------------------------------------------------------
    // Carrier management helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure only one carrier has is_address_validation = true.
     * Disables validation on all others when enabling it on $carrierId.
     */
    public function setAddressValidationCarrier(int $carrierId): void
    {
        Shipping::where('id', '!=', $carrierId)->update(['is_address_validation' => false]);
        Shipping::where('id', $carrierId)->update(['is_address_validation' => true]);
    }

    /**
     * Ensure only one carrier has is_default = true.
     */
    public function setDefaultCarrier(int $carrierId): void
    {
        Shipping::where('id', '!=', $carrierId)->update(['is_default' => false]);
        Shipping::where('id', $carrierId)->update(['is_default' => true]);
    }

    // -------------------------------------------------------------------------
    // Rate Quoting
    // -------------------------------------------------------------------------

    /**
     * Get estimated shipping rates for an order from a specific carrier.
     * Returns an array of rate options: [['service' => ..., 'amount' => ..., 'currency' => ..., 'transit_days' => ...], ...]
     *
     * @param Order    $order         The order to get rates for
     * @param Shipping $carrier       The shipping carrier to use
     * @param array    $itemOverrides Optional weight/dimension overrides per item
     * @param array    $unitOverrides Optional unit overrides ['weight_unit' => 'lbs|kg|oz', 'dimension_unit' => 'in|cm']
     *
     * @throws \RuntimeException if the carrier type is unsupported or token is unavailable
     */
    public function getRatesForOrder(Order $order, Shipping $carrier, array $itemOverrides = [], array $unitOverrides = []): array
    {
        $service = $this->resolveCarrierService($carrier);

        if (!$service) {
            throw new \RuntimeException("Carrier type '{$carrier->type}' is not supported for rate quotes.");
        }

        // Build a lookup map from user-supplied overrides keyed by order_item_id
        $overrideMap = [];
        foreach ($itemOverrides as $override) {
            if (!empty($override['order_item_id'])) {
                $overrideMap[(int) $override['order_item_id']] = $override;
            }
        }

        // Build package weight/dimensions from order items
        $totalWeight = 0.0;
        $maxLength   = 0.0;
        $maxWidth    = 0.0;
        $maxHeight   = 0.0;

        foreach ($order->items as $item) {
            $qty      = (int) ($item->quantity ?? 1);
            $override = $overrideMap[$item->id] ?? null;

            if ($override) {
                // Use user-supplied values (may be 0 if field was left blank — fall back below)
                $weight = (float) ($override['weight'] ?? 0);
                $length = (float) ($override['length'] ?? 0);
                $width  = (float) ($override['width']  ?? 0);
                $height = (float) ($override['height'] ?? 0);
            } else {
                $product = $item->product;
                $meta    = $product?->product_meta ?? [];
                $weight  = (float) ($meta['weight'] ?? $product?->weight ?? 0);
                $length  = (float) ($meta['length'] ?? $product?->length ?? 0);
                $width   = (float) ($meta['width']  ?? $product?->width  ?? 0);
                $height  = (float) ($meta['height'] ?? $product?->height ?? 0);
            }

            $totalWeight += $weight * $qty;
            $maxLength    = max($maxLength, $length);
            $maxWidth     = max($maxWidth,  $width);
            $maxHeight    = max($maxHeight, $height);
        }

        // Fallback to 1 lb / 12×12×12 if no product dimensions are set
        if ($totalWeight <= 0) {
            $totalWeight = 1.0;
        }
        if ($maxLength <= 0) { $maxLength = 12.0; }
        if ($maxWidth  <= 0) { $maxWidth  = 12.0; }
        if ($maxHeight <= 0) { $maxHeight = 12.0; }

        // Use unit overrides if provided, otherwise fall back to carrier settings
        $weightUnit    = $unitOverrides['weight_unit']    ?? $carrier->weight_unit    ?? 'lbs';
        $dimensionUnit = $unitOverrides['dimension_unit'] ?? $carrier->dimension_unit ?? 'in';

        // Convert weight if user selected a different unit than carrier default
        $totalWeight = $this->convertWeightToCarrierUnit($totalWeight, $weightUnit, $carrier->weight_unit ?? 'lbs');

        // Convert dimensions if user selected a different unit than carrier default
        $maxLength = $this->convertDimensionToCarrierUnit($maxLength, $dimensionUnit, $carrier->dimension_unit ?? 'in');
        $maxWidth  = $this->convertDimensionToCarrierUnit($maxWidth,  $dimensionUnit, $carrier->dimension_unit ?? 'in');
        $maxHeight = $this->convertDimensionToCarrierUnit($maxHeight, $dimensionUnit, $carrier->dimension_unit ?? 'in');

        // Use carrier's configured unit for the API call
        $weightUnit    = $carrier->weight_unit    ?? 'lbs';
        $dimensionUnit = $carrier->dimension_unit ?? 'in';

        // FedEx accepts only 'LB' or 'KG' — not 'LBS', 'KGS', etc.
        $fedexWeightUnit = in_array(strtolower($weightUnit), ['kg', 'kgs', 'kilogram', 'kilograms']) ? 'KG' : 'LB';
        $fedexDimUnit    = strtolower($dimensionUnit) === 'cm' ? 'CM' : 'IN';

        $shipmentDetails = [
            'accountNumber'       => ['value' => $carrier->account_number ?? ''],
            'requestedShipment'   => [
                'shipper' => [
                    'address' => [
                        'postalCode'  => $carrier->shipper_postal_code ?: config('shipping.shipper_postal_code', '10001'),
                        'countryCode' => $carrier->shipper_country     ?: config('shipping.shipper_country',     'US'),
                    ],
                ],
                'recipient' => [
                    'address' => [
                        'streetLines'         => array_values(array_filter([
                            $order->shipping_address_line1 ?? '',
                            $order->shipping_address_line2 ?? null,
                        ])),
                        'city'                => $order->shipping_city          ?? '',
                        'stateOrProvinceCode' => $order->shipping_state         ?? '',
                        'postalCode'          => $order->shipping_postal_code   ?? '',
                        'countryCode'         => $order->shipping_country       ?? 'US',
                        'residential'         => in_array($order->address_type, ['RESIDENTIAL', 'MIXED']),
                    ],
                ],
                'pickupType'               => 'USE_SCHEDULED_PICKUP',
                'rateRequestType'          => ['ACCOUNT', 'LIST'],
                'requestedPackageLineItems' => [[
                    'packageCount' => 1,
                    'weight' => [
                        'units' => $fedexWeightUnit,
                        'value' => round($totalWeight, 2),
                    ],
                    'dimensions' => [
                        'length' => (int) ceil($maxLength),
                        'width'  => (int) ceil($maxWidth),
                        'height' => (int) ceil($maxHeight),
                        'units'  => $fedexDimUnit,
                    ],
                ]],
            ],
        ];

        $rawRates = $service->getRates($shipmentDetails);

        return $this->normalizeRates($rawRates, $carrier->type);
    }

    /**
     * Convert weight from user-selected unit to carrier's expected unit.
     *
     * @param float  $weight   The weight value
     * @param string $fromUnit The user-selected unit (lbs, kg, oz)
     * @param string $toUnit   The carrier's expected unit (lbs, kg)
     * @return float The converted weight
     */
    protected function convertWeightToCarrierUnit(float $weight, string $fromUnit, string $toUnit): float
    {
        $fromUnit = strtolower($fromUnit);
        $toUnit   = strtolower($toUnit);

        if ($fromUnit === $toUnit) {
            return $weight;
        }

        // Convert to grams first (base unit)
        $grams = match ($fromUnit) {
            'lbs', 'lb' => $weight * 453.592,
            'kg'        => $weight * 1000,
            'oz'        => $weight * 28.3495,
            default     => $weight * 453.592, // assume lbs
        };

        // Convert from grams to target unit
        return match ($toUnit) {
            'lbs', 'lb' => $grams / 453.592,
            'kg'        => $grams / 1000,
            'oz'        => $grams / 28.3495,
            default     => $grams / 453.592, // assume lbs
        };
    }

    /**
     * Convert dimension from user-selected unit to carrier's expected unit.
     *
     * @param float  $dimension The dimension value
     * @param string $fromUnit  The user-selected unit (in, cm)
     * @param string $toUnit    The carrier's expected unit (in, cm, inches)
     * @return float The converted dimension
     */
    protected function convertDimensionToCarrierUnit(float $dimension, string $fromUnit, string $toUnit): float
    {
        $fromUnit = strtolower($fromUnit);
        $toUnit   = strtolower($toUnit);

        // Normalize 'inches' to 'in'
        if ($toUnit === 'inches') {
            $toUnit = 'in';
        }

        if ($fromUnit === $toUnit) {
            return $dimension;
        }

        // Convert between in and cm
        if ($fromUnit === 'in' && $toUnit === 'cm') {
            return $dimension * 2.54;
        }

        if ($fromUnit === 'cm' && $toUnit === 'in') {
            return $dimension / 2.54;
        }

        return $dimension;
    }

    /**
     * Normalize carrier-specific rate responses into a uniform structure.
     */
    protected function normalizeRates(array $rawRates, string $carrierType): array
    {
        $normalized = [];

        if (strtolower($carrierType) === 'fedex') {
            foreach ($rawRates as $rate) {
                $serviceType  = $rate['serviceType']  ?? 'UNKNOWN';
                $serviceName  = $rate['serviceName']  ?? ucwords(strtolower(str_replace('_', ' ', $serviceType)));
                $ratedShipmentDetails = $rate['ratedShipmentDetails'] ?? [];
                $amount   = null;
                $currency = 'USD';

                // Prefer ACCOUNT rate; fall back to LIST
                foreach ($ratedShipmentDetails as $detail) {
                    if (($detail['rateType'] ?? '') === 'ACCOUNT') {
                        $amount   = $detail['totalNetCharge']   ?? $detail['totalNetFedExCharge'] ?? null;
                        $currency = $detail['currency'] ?? 'USD';
                        break;
                    }
                }
                if ($amount === null && !empty($ratedShipmentDetails)) {
                    $first    = $ratedShipmentDetails[0];
                    $amount   = $first['totalNetCharge'] ?? $first['totalNetFedExCharge'] ?? null;
                    $currency = $first['currency'] ?? 'USD';
                }

                $transitDays = $rate['transitTime'] ?? null;
                if ($transitDays === null) {
                    $commitDetails = $rate['operationalDetail']['commitDays'] ?? null;
                    $transitDays   = $commitDetails;
                }

                $normalized[] = [
                    'service_code'  => $serviceType,
                    'service_name'  => $serviceName,
                    'amount'        => $amount !== null ? (float) $amount : null,
                    'currency'      => $currency,
                    'transit_days'  => $transitDays,
                ];
            }
        }

        // Sort cheapest first
        usort($normalized, fn($a, $b) => ($a['amount'] ?? PHP_INT_MAX) <=> ($b['amount'] ?? PHP_INT_MAX));

        return $normalized;
    }

    // -------------------------------------------------------------------------
    // Label Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a shipping label for an order.
     * Creates a shipment with the carrier, saves the label PDF, and returns tracking info.
     *
     * @param Order $order The order to ship
     * @param Shipping $carrier The carrier to use
     * @param string $serviceCode The service type code (e.g., 'FEDEX_GROUND')
     * @param array $itemOverrides Optional weight/dimension overrides keyed by order_item_id
     * @param array $unitOverrides Optional unit overrides ['weight_unit' => 'lbs|kg|oz', 'dimension_unit' => 'in|cm']
     * @return array ['tracking_number' => string, 'label_path' => string, 'carrier_name' => string]
     * @throws \RuntimeException on failure
     */
    public function generateLabelForOrder(Order $order, Shipping $carrier, string $serviceCode, array $itemOverrides = [], array $unitOverrides = []): array
    {
        $service = $this->resolveCarrierService($carrier);

        if (!$service) {
            throw new \RuntimeException("Carrier type '{$carrier->type}' is not supported for label generation.");
        }

        // Build a lookup map from user-supplied overrides keyed by order_item_id
        $overrideMap = [];
        foreach ($itemOverrides as $override) {
            if (!empty($override['order_item_id'])) {
                $overrideMap[(int) $override['order_item_id']] = $override;
            }
        }

        // Build package weight/dimensions from order items
        $totalWeight = 0.0;
        $maxLength   = 0.0;
        $maxWidth    = 0.0;
        $maxHeight   = 0.0;

        foreach ($order->items as $item) {
            $qty      = (int) ($item->quantity ?? 1);
            $override = $overrideMap[$item->id] ?? null;

            if ($override) {
                $weight = (float) ($override['weight'] ?? 0);
                $length = (float) ($override['length'] ?? 0);
                $width  = (float) ($override['width']  ?? 0);
                $height = (float) ($override['height'] ?? 0);
            } else {
                $product = $item->product;
                $meta    = $product?->product_meta ?? [];
                $weight  = (float) ($meta['weight'] ?? $product?->weight ?? 0);
                $length  = (float) ($meta['length'] ?? $product?->length ?? 0);
                $width   = (float) ($meta['width']  ?? $product?->width  ?? 0);
                $height  = (float) ($meta['height'] ?? $product?->height ?? 0);
            }

            $totalWeight += $weight * $qty;
            $maxLength    = max($maxLength, $length);
            $maxWidth     = max($maxWidth,  $width);
            $maxHeight    = max($maxHeight, $height);
        }

        // Fallback to 1 lb / 12×12×12 if no product dimensions are set
        if ($totalWeight <= 0) {
            $totalWeight = 1.0;
        }
        if ($maxLength <= 0) { $maxLength = 12.0; }
        if ($maxWidth  <= 0) { $maxWidth  = 12.0; }
        if ($maxHeight <= 0) { $maxHeight = 12.0; }

        // Use unit overrides if provided, otherwise fall back to carrier settings
        $userWeightUnit    = $unitOverrides['weight_unit']    ?? $carrier->weight_unit    ?? 'lbs';
        $userDimensionUnit = $unitOverrides['dimension_unit'] ?? $carrier->dimension_unit ?? 'in';

        // Convert weight if user selected a different unit than carrier default
        $totalWeight = $this->convertWeightToCarrierUnit($totalWeight, $userWeightUnit, $carrier->weight_unit ?? 'lbs');

        // Convert dimensions if user selected a different unit than carrier default
        $maxLength = $this->convertDimensionToCarrierUnit($maxLength, $userDimensionUnit, $carrier->dimension_unit ?? 'in');
        $maxWidth  = $this->convertDimensionToCarrierUnit($maxWidth,  $userDimensionUnit, $carrier->dimension_unit ?? 'in');
        $maxHeight = $this->convertDimensionToCarrierUnit($maxHeight, $userDimensionUnit, $carrier->dimension_unit ?? 'in');

        // Use carrier's configured unit for the API call
        $weightUnit    = $carrier->weight_unit    ?? 'lbs';
        $dimensionUnit = $carrier->dimension_unit ?? 'inches';

        // FedEx accepts only 'LB' or 'KG' — not 'LBS', 'KGS', etc.
        $fedexWeightUnit = in_array(strtolower($weightUnit), ['kg', 'kgs', 'kilogram', 'kilograms']) ? 'KG' : 'LB';
        $fedexDimUnit    = strtolower($dimensionUnit) === 'cm' ? 'CM' : 'IN';

        // Build shipper address from carrier
        $shipperStreetLines = array_values(array_filter([
            $carrier->shipper_address ?? '',
        ]));
        if (empty($shipperStreetLines)) {
            $shipperStreetLines = ['123 Shipper Street'];
        }

        // Build recipient address from order
        $recipientStreetLines = array_values(array_filter([
            $order->shipping_address_line1 ?? '',
            $order->shipping_address_line2 ?? null,
        ]));

        $shipmentPayload = [
            'labelResponseOptions' => 'LABEL',
            'accountNumber'        => ['value' => $carrier->account_number ?? ''],
            'requestedShipment'    => [
                'shipper' => [
                    'contact' => [
                        'personName'  => $carrier->shipper_name ?? 'Shipper',
                        'phoneNumber' => '1234567890',
                    ],
                    'address' => [
                        'streetLines'         => $shipperStreetLines,
                        'city'                => $carrier->shipper_city          ?? 'New York',
                        'stateOrProvinceCode' => $carrier->shipper_state         ?? 'NY',
                        'postalCode'          => $carrier->shipper_postal_code   ?? '10001',
                        'countryCode'         => $carrier->shipper_country       ?? 'US',
                    ],
                ],
                'recipients' => [[
                    'contact' => [
                        'personName'  => $order->shipping_name ?? $order->buyer_name ?? 'Recipient',
                        'phoneNumber' => $order->buyer_phone ?? '1234567890',
                    ],
                    'address' => [
                        'streetLines'         => $recipientStreetLines,
                        'city'                => $order->shipping_city          ?? '',
                        'stateOrProvinceCode' => $order->shipping_state         ?? '',
                        'postalCode'          => $order->shipping_postal_code   ?? '',
                        'countryCode'         => $order->shipping_country       ?? 'US',
                        'residential'         => in_array($order->address_type, ['RESIDENTIAL', 'MIXED']),
                    ],
                ]],
                'serviceType'          => $serviceCode,
                'packagingType'        => 'YOUR_PACKAGING',
                'pickupType'           => 'USE_SCHEDULED_PICKUP',
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor'       => [
                        'responsibleParty' => [
                            'accountNumber' => ['value' => $carrier->account_number ?? ''],
                        ],
                    ],
                ],
                'labelSpecification' => [
                    'labelFormatType' => 'COMMON2D',
                    'imageType'       => 'PDF',
                    'labelStockType'  => 'PAPER_7X475',
                ],
                'requestedPackageLineItems' => [[
                    'weight' => [
                        'units' => $fedexWeightUnit,
                        'value' => round($totalWeight, 2),
                    ],
                    'dimensions' => [
                        'length' => (int) ceil($maxLength),
                        'width'  => (int) ceil($maxWidth),
                        'height' => (int) ceil($maxHeight),
                        'units'  => $fedexDimUnit,
                    ],
                ]],
            ],
        ];

        // Call FedEx to create shipment
        $result = $service->createShipment($shipmentPayload);

        // Save the label PDF to storage
        $trackingNumber = $result['tracking_number'];
        $labelBase64    = $result['label_base64'];
        $labelFormat    = strtolower($result['label_format'] ?? 'pdf');

        $filename  = "shipping-labels/order-{$order->id}-{$trackingNumber}.{$labelFormat}";
        $labelData = base64_decode($labelBase64);

        Storage::put($filename, $labelData);

        Log::info('ShippingService: label generated and saved', [
            'order_id'        => $order->id,
            'tracking_number' => $trackingNumber,
            'label_path'      => $filename,
            'carrier'         => $carrier->name,
        ]);

        return [
            'tracking_number' => $trackingNumber,
            'label_path'      => $filename,
            'carrier_name'    => $carrier->name,
        ];
    }

    // -------------------------------------------------------------------------
    // Delivery Status Tracking
    // -------------------------------------------------------------------------

    /**
     * Check delivery status for a single order.
     * Updates order status to 'delivered' if the carrier reports it as delivered.
     *
     * @param Order $order The order to check
     * @return array ['checked' => bool, 'delivered' => bool, 'status' => string, 'error' => ?string]
     */
    public function checkOrderDeliveryStatus(Order $order): array
    {
        // Must have a tracking number and shipping carrier
        if (!$order->tracking_number || !$order->shipping_id) {
            return [
                'checked'   => false,
                'delivered' => false,
                'status'    => 'Missing tracking number or carrier',
                'error'     => 'Order missing tracking_number or shipping_id',
            ];
        }

        // Already delivered? Skip
        if ($order->order_status === 'delivered') {
            return [
                'checked'   => false,
                'delivered' => true,
                'status'    => 'Already delivered',
                'error'     => null,
            ];
        }

        $carrier = $order->shippingCarrier;
        if (!$carrier) {
            return [
                'checked'   => false,
                'delivered' => false,
                'status'    => 'Carrier not found',
                'error'     => "Carrier ID {$order->shipping_id} not found",
            ];
        }

        $service = $this->resolveCarrierService($carrier);
        if (!$service) {
            return [
                'checked'   => false,
                'delivered' => false,
                'status'    => 'Unsupported carrier',
                'error'     => "Carrier type '{$carrier->type}' not supported for tracking",
            ];
        }

        try {
            $trackingResult = $service->getTrackingStatus($order->tracking_number);

            // Update last checked timestamp
            $order->update(['tracking_last_checked_at' => now()]);

            if ($trackingResult['delivered']) {
                // Parse delivered_at from ISO 8601 string if available
                $deliveredAt = $trackingResult['delivered_at']
                    ? \Carbon\Carbon::parse($trackingResult['delivered_at'])
                    : now();

                $order->update([
                    'order_status' => 'delivered',
                    'delivered_at' => $deliveredAt,
                ]);

                Log::info('ShippingService: order marked as delivered', [
                    'order_id'        => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'delivered_at'    => $deliveredAt,
                ]);

                return [
                    'checked'   => true,
                    'delivered' => true,
                    'status'    => $trackingResult['status'],
                    'error'     => null,
                ];
            }

            return [
                'checked'   => true,
                'delivered' => false,
                'status'    => $trackingResult['status'],
                'error'     => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('ShippingService: tracking check failed', [
                'order_id'        => $order->id,
                'tracking_number' => $order->tracking_number,
                'error'           => $e->getMessage(),
            ]);

            return [
                'checked'   => false,
                'delivered' => false,
                'status'    => 'Error',
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Check delivery status for all shipped orders that haven't been delivered yet.
     *
     * @param int $limit Maximum number of orders to check in one run
     * @return array ['total' => int, 'checked' => int, 'delivered' => int, 'errors' => int]
     */
    public function checkAllPendingDeliveries(int $limit = 100): array
    {
        $stats = ['total' => 0, 'checked' => 0, 'delivered' => 0, 'errors' => 0];

        // Get shipped orders with tracking that aren't delivered yet
        $orders = Order::where('order_status', 'shipped')
            ->whereNotNull('tracking_number')
            ->whereNotNull('shipping_id')
            ->whereNull('delivered_at')
            ->orderBy('tracking_last_checked_at', 'asc') // Check oldest first
            ->limit($limit)
            ->get();

        $stats['total'] = $orders->count();

        foreach ($orders as $order) {
            $result = $this->checkOrderDeliveryStatus($order);

            if ($result['checked']) {
                $stats['checked']++;
                if ($result['delivered']) {
                    $stats['delivered']++;
                }
            }

            if ($result['error']) {
                $stats['errors']++;
            }

            // Small delay to avoid rate limiting
            usleep(200000); // 200ms
        }

        Log::info('ShippingService: delivery status check complete', $stats);

        return $stats;
    }
}
