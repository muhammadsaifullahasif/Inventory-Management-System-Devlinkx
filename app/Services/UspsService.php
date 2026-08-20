<?php

namespace App\Services;

use App\Models\Shipping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UspsService
{
    protected Shipping $carrier;

    const ADDRESS_TYPE_BUSINESS    = 'BUSINESS';
    const ADDRESS_TYPE_RESIDENTIAL = 'RESIDENTIAL';
    const ADDRESS_TYPE_MIXED       = 'MIXED';
    const ADDRESS_TYPE_UNKNOWN     = 'UNKNOWN';

    public function __construct(Shipping $carrier)
    {
        $this->carrier = $carrier;
    }

    /**
     * USPS APIs (api.usps.com) are served from a single host for both live and
     * CTP/sandbox traffic — access to test data is granted per-application via
     * the registered scopes, not a separate hostname. A configured
     * sandbox_endpoint on the carrier row is still honored if set.
     */
    protected function baseUrl(): string
    {
        if ($this->carrier->is_sandbox && $this->carrier->sandbox_endpoint) {
            return rtrim($this->carrier->sandbox_endpoint, '/');
        }

        return rtrim($this->carrier->api_endpoint ?: 'https://api.usps.com', '/');
    }

    public function getAccessToken(): ?string
    {
        if ($this->carrier->access_token && $this->carrier->access_token_expires_at) {
            $expiresAt = \Carbon\Carbon::parse($this->carrier->access_token_expires_at);
            if ($expiresAt->subMinutes(5)->isFuture()) {
                return $this->carrier->access_token;
            }
        }

        return $this->refreshAccessToken();
    }

    public function refreshAccessToken(): ?string
    {
        $credentials  = $this->carrier->credentials ?? [];
        $clientId     = $credentials['client_id']     ?? null;
        $clientSecret = $credentials['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            Log::error('USPS: missing client_id or client_secret', ['carrier_id' => $this->carrier->id]);
            return null;
        }

        try {
            $response = Http::asForm()->post("{$this->baseUrl()}/oauth2/v3/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'addresses prices tracking labels',
            ]);

            if (!$response->successful()) {
                Log::error('USPS: token request failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $data        = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $expiresIn   = (int) ($data['expires_in'] ?? 28800);

            $this->carrier->update([
                'access_token'            => $accessToken,
                'access_token_expires_at' => now()->addSeconds($expiresIn),
            ]);

            return $accessToken;
        } catch (\Throwable $e) {
            Log::error('USPS: token request exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Standardize/verify an address via the USPS Addresses API.
     *
     * Unlike FedEx, USPS's Addresses API does not classify an address as
     * BUSINESS/RESIDENTIAL/MIXED — it only standardizes and confirms
     * deliverability. This always returns UNKNOWN for classification.
     */
    public function validateAddress(
        string $street1,
        string $city,
        string $state,
        string $postalCode,
        string $country = 'US',
        ?string $street2 = null
    ): string {
        $token = $this->getAccessToken();
        if (!$token) {
            return self::ADDRESS_TYPE_UNKNOWN;
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl()}/addresses/v3/address", array_filter([
                    'streetAddress'    => $street1,
                    'secondaryAddress' => $street2,
                    'city'             => $city,
                    'state'            => $state,
                    'ZIPCode'          => substr($postalCode, 0, 5),
                ]));

            if (!$response->successful()) {
                Log::warning('USPS: address validation failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'address' => "{$street1}, {$city}, {$state} {$postalCode}",
                ]);
                return self::ADDRESS_TYPE_UNKNOWN;
            }

            return self::ADDRESS_TYPE_UNKNOWN;
        } catch (\Throwable $e) {
            Log::error('USPS: address validation exception', ['message' => $e->getMessage()]);
            return self::ADDRESS_TYPE_UNKNOWN;
        }
    }

    /**
     * Get a base rate quote for one mail class via the USPS Prices API.
     *
     * @param array $shipmentDetails Payload for POST /prices/v3/base-rates/search
     *                               (originZIPCode, destinationZIPCode, weight, length,
     *                               width, height, mailClass, processingCategory,
     *                               rateIndicator, destinationEntryFacilityType, priceType)
     * @return array Raw rateOptions array from the USPS response
     */
    public function getRates(array $shipmentDetails): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl()}/prices/v3/base-rates/search", $shipmentDetails);

            if (!$response->successful()) {
                Log::warning('USPS: getRates failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $shipmentDetails,
                ]);
                return [];
            }

            return $response->json('rateOptions', []);
        } catch (\Throwable $e) {
            Log::error('USPS: getRates exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a shipment and generate a shipping label via the USPS Labels API.
     *
     * @param array $shipmentDetails Payload for POST /labels/v3/label
     * @return array ['tracking_number','label_base64','label_format','shipment_id','shipping_cost','shipping_currency']
     * @throws \RuntimeException on failure
     */
    public function createShipment(array $shipmentDetails): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('USPS: Unable to obtain access token');
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl()}/labels/v3/label", $shipmentDetails);

            if (!$response->successful()) {
                $errorBody    = $response->json();
                $errorMessage = $errorBody['error']['message']
                    ?? ($errorBody['errors'][0]['detail'] ?? null)
                    ?? $response->body();

                Log::error('USPS: createShipment failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $shipmentDetails,
                ]);

                throw new \RuntimeException("USPS shipment creation failed: {$errorMessage}");
            }

            $data = $response->json();

            $trackingNumber = $data['trackingNumber'] ?? null;
            $labelBase64    = $data['labelImage'] ?? ($data['labelMetadata']['labelImage'] ?? null);
            $labelFormat    = $data['labelMetadata']['imageType'] ?? ($shipmentDetails['imageInfo']['imageType'] ?? 'PDF');
            $shippingCost   = $data['postage'] ?? $data['totalPrice'] ?? null;

            if (!$trackingNumber || !$labelBase64) {
                Log::error('USPS: createShipment missing tracking or label', ['response' => $data]);
                throw new \RuntimeException('USPS: Shipment created but missing tracking number or label');
            }

            return [
                'tracking_number'   => $trackingNumber,
                'label_base64'      => $labelBase64,
                'label_format'      => $labelFormat,
                'shipment_id'       => $data['SKU'] ?? $trackingNumber,
                'shipping_cost'     => $shippingCost !== null ? (float) $shippingCost : null,
                'shipping_currency' => 'USD',
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('USPS: createShipment exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("USPS shipment creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Get tracking status for a shipment via the USPS Tracking API.
     *
     * @param string $trackingNumber The USPS tracking number
     * @return array ['status_code' => string, 'status' => string, 'delivered' => bool, 'delivered_at' => ?string, 'raw' => array]
     */
    public function getTrackingStatus(string $trackingNumber): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('USPS: Unable to obtain access token');
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl()}/tracking/v3/tracking/{$trackingNumber}");

            if (!$response->successful()) {
                $errorBody    = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();

                Log::warning('USPS: getTrackingStatus failed', [
                    'status'          => $response->status(),
                    'body'            => $response->body(),
                    'tracking_number' => $trackingNumber,
                ]);

                throw new \RuntimeException("USPS tracking request failed: {$errorMessage}");
            }

            $data = $response->json();

            $statusCategory = $data['statusCategory'] ?? $data['status'] ?? 'Unknown';
            $delivered      = stripos((string) $statusCategory, 'delivered') !== false;

            $deliveredAt = null;
            if ($delivered) {
                foreach (($data['trackingEvents'] ?? []) as $event) {
                    if (stripos($event['eventType'] ?? '', 'delivered') !== false) {
                        $deliveredAt = $event['eventTimestamp'] ?? null;
                        break;
                    }
                }
            }

            return [
                'status_code'  => $statusCategory,
                'status'       => $data['statusSummary'] ?? $data['status'] ?? 'Unknown',
                'delivered'    => $delivered,
                'delivered_at' => $deliveredAt,
                'raw'          => $data,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('USPS: getTrackingStatus exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("USPS tracking failed: {$e->getMessage()}");
        }
    }

    /**
     * Cancel/refund a shipping label via the USPS Labels API.
     *
     * @param string $trackingNumber The USPS tracking number to cancel
     * @return array ['success' => bool, 'message' => string]
     * @throws \RuntimeException on failure
     */
    public function cancelShipment(string $trackingNumber): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('USPS: Unable to obtain access token');
        }

        try {
            $response = Http::withToken($token)
                ->delete("{$this->baseUrl()}/labels/v3/label/{$trackingNumber}");

            $data = $response->json();

            if (!$response->successful()) {
                $errorMessage = $data['error']['message'] ?? $response->body();

                Log::warning('USPS: cancelShipment failed', [
                    'status'          => $response->status(),
                    'body'            => $response->body(),
                    'tracking_number' => $trackingNumber,
                ]);

                throw new \RuntimeException("USPS cancel shipment failed: {$errorMessage}");
            }

            return [
                'success' => true,
                'message' => 'Shipment cancelled successfully',
                'raw'     => $data,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('USPS: cancelShipment exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("USPS cancel shipment failed: {$e->getMessage()}");
        }
    }
}
