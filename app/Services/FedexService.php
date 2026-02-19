<?php

namespace App\Services;

use App\Models\Shipping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FedexService
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
            Log::error('FedEx: missing client_id or client_secret', ['carrier_id' => $this->carrier->id]);
            return null;
        }

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        try {
            $response = Http::asForm()->post("{$endpoint}/oauth/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$response->successful()) {
                Log::error('FedEx: token request failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $data        = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $expiresIn   = (int) ($data['expires_in'] ?? 3600);

            $this->carrier->update([
                'access_token'            => $accessToken,
                'access_token_expires_at' => now()->addSeconds($expiresIn),
            ]);

            return $accessToken;
        } catch (\Throwable $e) {
            Log::error('FedEx: token request exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

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

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        $streetLines = array_values(array_filter([$street1, $street2]));

        $payload = [
            'addressesToValidate' => [[
                'address' => [
                    'streetLines'         => $streetLines,
                    'city'                => $city,
                    'stateOrProvinceCode' => $state,
                    'postalCode'          => $postalCode,
                    'countryCode'         => $country,
                ],
            ]],
        ];

        try {
            $response = Http::withToken($token)
                ->post("{$endpoint}/address/v1/addresses/resolve", $payload);

            if (!$response->successful()) {
                Log::warning('FedEx: address validation failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'address' => "{$street1}, {$city}, {$state} {$postalCode}",
                ]);
                return self::ADDRESS_TYPE_UNKNOWN;
            }

            return $this->parseAddressClassification($response->json());
        } catch (\Throwable $e) {
            Log::error('FedEx: address validation exception', ['message' => $e->getMessage()]);
            return self::ADDRESS_TYPE_UNKNOWN;
        }
    }

    protected function parseAddressClassification(array $data): string
    {
        $resolved       = $data['output']['resolvedAddresses'][0] ?? [];
        $classification = strtoupper($resolved['classification'] ?? 'UNKNOWN');

        return match ($classification) {
            'BUSINESS'    => self::ADDRESS_TYPE_BUSINESS,
            'RESIDENTIAL' => self::ADDRESS_TYPE_RESIDENTIAL,
            'MIXED'       => self::ADDRESS_TYPE_MIXED,
            default       => self::ADDRESS_TYPE_UNKNOWN,
        };
    }

    public function getRates(array $shipmentDetails): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        try {
            $response = Http::withToken($token)
                ->post("{$endpoint}/rate/v1/rates/quotes", $shipmentDetails);

            if (!$response->successful()) {
                Log::warning('FedEx: getRates failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $shipmentDetails,
                ]);
                return [];
            }

            return $response->json('output.rateReplyDetails', []);
        } catch (\Throwable $e) {
            Log::error('FedEx: getRates exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a shipment and generate a shipping label.
     *
     * @param array $shipmentDetails The full shipment payload for FedEx Ship API
     * @return array ['tracking_number' => string, 'label_base64' => string, 'label_format' => string]
     * @throws \RuntimeException on failure
     */
    public function createShipment(array $shipmentDetails): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('FedEx: Unable to obtain access token');
        }

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-locale'     => 'en_US',
                ])
                ->post("{$endpoint}/ship/v1/shipments", $shipmentDetails);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorCode = $errorBody['errors'][0]['code'] ?? '';
                $errorMessage = $errorBody['errors'][0]['message']
                    ?? $errorBody['error_description']
                    ?? $response->body();

                Log::error('FedEx: createShipment failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $shipmentDetails,
                ]);

                // Provide more helpful error message for common issues
                if ($response->status() === 403 || $errorCode === 'FORBIDDEN.ERROR') {
                    throw new \RuntimeException(
                        "FedEx authorization failed. Please ensure: (1) Ship API is enabled in your FedEx Developer Portal project, " .
                        "(2) Your account number is authorized for shipping, (3) You're using the correct sandbox/production credentials. " .
                        "Original error: {$errorMessage}"
                    );
                }

                throw new \RuntimeException("FedEx shipment creation failed: {$errorMessage}");
            }

            $data = $response->json();

            // Extract tracking number
            $trackingNumber = $data['output']['transactionShipments'][0]['masterTrackingNumber'] ?? null;
            if (!$trackingNumber) {
                $trackingNumber = $data['output']['transactionShipments'][0]['pieceResponses'][0]['trackingNumber'] ?? null;
            }

            // Extract label (base64 encoded)
            $labelBase64 = $data['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'] ?? null;
            $labelFormat = $data['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['docType'] ?? 'PDF';

            if (!$trackingNumber || !$labelBase64) {
                Log::error('FedEx: createShipment missing tracking or label', [
                    'response' => $data,
                ]);
                throw new \RuntimeException('FedEx: Shipment created but missing tracking number or label');
            }

            Log::info('FedEx: shipment created successfully', [
                'tracking_number' => $trackingNumber,
                'carrier_id'      => $this->carrier->id,
            ]);

            return [
                'tracking_number' => $trackingNumber,
                'label_base64'    => $labelBase64,
                'label_format'    => $labelFormat,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FedEx: createShipment exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("FedEx shipment creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Get tracking status for a shipment.
     *
     * @param string $trackingNumber The FedEx tracking number
     * @return array ['status_code' => string, 'status' => string, 'delivered' => bool, 'delivered_at' => ?string, 'raw' => array]
     */
    public function getTrackingStatus(string $trackingNumber): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('FedEx: Unable to obtain access token');
        }

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        $payload = [
            'includeDetailedScans' => false,
            'trackingInfo' => [[
                'trackingNumberInfo' => [
                    'trackingNumber' => $trackingNumber,
                ],
            ]],
        ];

        try {
            $response = Http::withToken($token)
                ->post("{$endpoint}/track/v1/trackingnumbers", $payload);

            if (!$response->successful()) {
                Log::warning('FedEx: getTrackingStatus failed', [
                    'status'          => $response->status(),
                    'body'            => $response->body(),
                    'tracking_number' => $trackingNumber,
                ]);
                throw new \RuntimeException('FedEx tracking request failed');
            }

            $data = $response->json();

            // Extract tracking result
            $trackResult = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? null;

            if (!$trackResult) {
                return [
                    'status_code' => 'UNKNOWN',
                    'status'      => 'Unknown',
                    'delivered'   => false,
                    'delivered_at' => null,
                    'raw'         => $data,
                ];
            }

            $latestStatus = $trackResult['latestStatusDetail'] ?? [];
            $statusCode   = $latestStatus['code'] ?? 'UNKNOWN';
            $statusText   = $latestStatus['statusByLocale'] ?? $latestStatus['description'] ?? 'Unknown';

            // Check if delivered
            $delivered   = ($statusCode === 'DL');
            $deliveredAt = null;

            if ($delivered) {
                // Find actual delivery date/time
                $dateAndTimes = $trackResult['dateAndTimes'] ?? [];
                foreach ($dateAndTimes as $dt) {
                    if (($dt['type'] ?? '') === 'ACTUAL_DELIVERY') {
                        $deliveredAt = $dt['dateTime'] ?? null;
                        break;
                    }
                }
            }

            Log::info('FedEx: tracking status retrieved', [
                'tracking_number' => $trackingNumber,
                'status_code'     => $statusCode,
                'status'          => $statusText,
                'delivered'       => $delivered,
            ]);

            return [
                'status_code'  => $statusCode,
                'status'       => $statusText,
                'delivered'    => $delivered,
                'delivered_at' => $deliveredAt,
                'raw'          => $trackResult,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FedEx: getTrackingStatus exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("FedEx tracking failed: {$e->getMessage()}");
        }
    }
}
