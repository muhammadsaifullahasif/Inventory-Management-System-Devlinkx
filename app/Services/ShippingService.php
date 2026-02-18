<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\Log;

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
     * @throws \RuntimeException if the carrier type is unsupported or token is unavailable
     */
    public function getRatesForOrder(Order $order, Shipping $carrier, array $itemOverrides = []): array
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

        $weightUnit    = $carrier->weight_unit    ?? 'lbs';
        $dimensionUnit = $carrier->dimension_unit ?? 'inches';

        // FedEx accepts only 'LB' or 'KG' — not 'LBS', 'KGS', etc.
        $fedexWeightUnit = in_array(strtolower($weightUnit), ['kg', 'kgs', 'kilogram', 'kilograms']) ? 'KG' : 'LB';
        $fedexDimUnit    = strtolower($dimensionUnit) === 'cm' ? 'CM' : 'IN';

        $shipmentDetails = [
            'accountNumber'       => ['value' => $carrier->account_number ?? ''],
            'requestedShipment'   => [
                'shipper' => [
                    'address' => [
                        'postalCode'  => config('shipping.shipper_postal_code', '77477'),
                        'countryCode' => config('shipping.shipper_country',     'US'),
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
}
