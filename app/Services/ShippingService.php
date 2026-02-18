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
}
