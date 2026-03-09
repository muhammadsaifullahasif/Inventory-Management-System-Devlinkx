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
     * Create a shipment and generate a shipping label using two-step process.
     * Step 1: CONFIRM - validates shipment and gets shipmentId + tracking number + label
     * Step 2: SHIP - registers the shipment in FedEx system using shipmentId
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

        // =========================================================================
        // STEP 1: CONFIRM - Validate shipment and get shipmentId, tracking, label
        // =========================================================================

        // Override shipAction to CONFIRM for first step
        $confirmPayload = $shipmentDetails;
        $confirmPayload['shipAction'] = 'CONFIRM';

        Log::info('FedEx: createShipment STEP 1 (CONFIRM) request', [
            'endpoint' => $endpoint,
            'is_sandbox' => $this->carrier->is_sandbox,
            'carrier_name' => $this->carrier->name,
            'account_number' => $this->carrier->account_number,
            'ship_action' => 'CONFIRM',
        ]);

        try {
            $confirmResponse = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-locale'     => 'en_US',
                ])
                ->post("{$endpoint}/ship/v1/shipments", $confirmPayload);

            if (!$confirmResponse->successful()) {
                $errorBody = $confirmResponse->json();
                $errorCode = $errorBody['errors'][0]['code'] ?? '';
                $errorMessage = $errorBody['errors'][0]['message']
                    ?? $errorBody['error_description']
                    ?? $confirmResponse->body();

                Log::error('FedEx: createShipment CONFIRM failed', [
                    'status'  => $confirmResponse->status(),
                    'body'    => $confirmResponse->body(),
                    'json'    => $confirmResponse->json(),
                    'payload' => $confirmPayload,
                ]);

                if ($confirmResponse->status() === 403 || $errorCode === 'FORBIDDEN.ERROR') {
                    throw new \RuntimeException(
                        "FedEx authorization failed. Please ensure: (1) Ship API is enabled in your FedEx Developer Portal project, " .
                        "(2) Your account number is authorized for shipping, (3) You're using the correct sandbox/production credentials. " .
                        "Original error: {$errorMessage}"
                    );
                }

                throw new \RuntimeException("FedEx shipment CONFIRM failed: {$errorMessage}");
            }

            $confirmData = $confirmResponse->json();

            // Extract shipmentId from CONFIRM response
            $shipmentId = $confirmData['output']['transactionShipments'][0]['shipmentId'] ?? null;

            // Extract tracking number
            $trackingNumber = $confirmData['output']['transactionShipments'][0]['masterTrackingNumber'] ?? null;
            if (!$trackingNumber) {
                $trackingNumber = $confirmData['output']['transactionShipments'][0]['pieceResponses'][0]['trackingNumber'] ?? null;
            }

            // Extract label (base64 encoded)
            $labelBase64 = $confirmData['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'] ?? null;
            $labelFormat = $confirmData['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['docType'] ?? 'PDF';

            Log::info('FedEx: createShipment STEP 1 (CONFIRM) response', [
                'endpoint' => $endpoint,
                'is_sandbox' => $this->carrier->is_sandbox,
                'http_status' => $confirmResponse->status(),
                'transaction_id' => $confirmData['transactionId'] ?? null,
                'shipment_id' => $shipmentId,
                'tracking_number' => $trackingNumber,
                'has_label' => !empty($labelBase64),
                'alerts' => $confirmData['output']['alerts'] ?? [],
            ]);

            if (!$trackingNumber || !$labelBase64) {
                Log::error('FedEx: createShipment CONFIRM missing tracking or label', [
                    'response' => $confirmData,
                ]);
                throw new \RuntimeException('FedEx: CONFIRM succeeded but missing tracking number or label');
            }

            // =========================================================================
            // STEP 2: SHIP - Register the shipment in FedEx system using shipmentId
            // =========================================================================

            if ($shipmentId) {
                Log::info('FedEx: createShipment STEP 2 (SHIP) request', [
                    'endpoint' => $endpoint,
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $trackingNumber,
                ]);

                // Build SHIP payload - simpler, just needs shipmentId and accountNumber
                $shipPayload = [
                    'shipAction' => 'SHIP',
                    'processingOptionType' => 'SYNCHRONOUS_ONLY',
                    'accountNumber' => $shipmentDetails['accountNumber'] ?? ['value' => ''],
                    'shipmentId' => $shipmentId,
                ];

                $shipResponse = Http::withToken($token)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-locale'     => 'en_US',
                    ])
                    ->post("{$endpoint}/ship/v1/shipments", $shipPayload);

                if (!$shipResponse->successful()) {
                    $shipErrorBody = $shipResponse->json();
                    $shipErrorMessage = $shipErrorBody['errors'][0]['message']
                        ?? $shipErrorBody['error_description']
                        ?? $shipResponse->body();

                    Log::error('FedEx: createShipment SHIP failed', [
                        'status'  => $shipResponse->status(),
                        'body'    => $shipResponse->body(),
                        'json'    => $shipResponse->json(),
                        'payload' => $shipPayload,
                        'shipment_id' => $shipmentId,
                    ]);

                    // Even if SHIP fails, we have the label and tracking from CONFIRM
                    // Log warning but don't throw - shipment may still be usable
                    Log::warning('FedEx: SHIP step failed but CONFIRM succeeded. Shipment may not appear in FedEx Shipment History.', [
                        'tracking_number' => $trackingNumber,
                        'shipment_id' => $shipmentId,
                        'error' => $shipErrorMessage,
                    ]);
                } else {
                    $shipData = $shipResponse->json();

                    Log::info('FedEx: createShipment STEP 2 (SHIP) response - SUCCESS', [
                        'http_status' => $shipResponse->status(),
                        'transaction_id' => $shipData['transactionId'] ?? null,
                        'shipment_id' => $shipmentId,
                        'tracking_number' => $trackingNumber,
                        'alerts' => $shipData['output']['alerts'] ?? [],
                    ]);
                }
            } else {
                // No shipmentId returned - log warning
                Log::warning('FedEx: CONFIRM response did not return shipmentId. Shipment may not appear in FedEx Shipment History.', [
                    'tracking_number' => $trackingNumber,
                    'confirm_response' => $confirmData,
                ]);
            }

            Log::info('FedEx: shipment created successfully (two-step process)', [
                'tracking_number' => $trackingNumber,
                'carrier_id'      => $this->carrier->id,
                'endpoint' => $endpoint,
                'is_sandbox' => $this->carrier->is_sandbox,
                'shipment_id' => $shipmentId,
                'service_type' => $confirmData['output']['transactionShipments'][0]['serviceType'] ?? null,
            ]);

            return [
                'tracking_number' => $trackingNumber,
                'label_base64'    => $labelBase64,
                'label_format'    => $labelFormat,
                'shipment_id'     => $shipmentId,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FedEx: createShipment exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("FedEx shipment creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Close/commit shipments for the day (Ground End of Day).
     * This is required for FedEx Ground shipments to appear in Ship Manager
     * and to generate the manifest for pickup.
     *
     * @param string|null $closeDate The close date (YYYY-MM-DD), defaults to today
     * @return array ['success' => bool, 'confirmation_number' => ?string, 'manifest' => ?string, 'raw' => array]
     * @throws \RuntimeException on failure
     */
    public function closeShipments(?string $closeDate = null): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('FedEx: Unable to obtain access token');
        }

        $endpoint = $this->carrier->is_sandbox
            ? ($this->carrier->sandbox_endpoint ?: 'https://apis-sandbox.fedex.com')
            : ($this->carrier->api_endpoint     ?: 'https://apis.fedex.com');

        $closeDate = $closeDate ?? date('Y-m-d');

        $payload = [
            'accountNumber' => [
                'value' => $this->carrier->account_number ?? '',
            ],
            'groundServiceCategory' => 'GROUND', // For FedEx Ground shipments
            'closeReqType' => 'GCCLOSE', // Ground Close
            'closeDate' => $closeDate,
            'closeDocumentSpecification' => [
                'closeDocumentTypes' => ['MANIFEST'],
            ],
        ];

        Log::info('FedEx: closeShipments request', [
            'endpoint' => $endpoint,
            'is_sandbox' => $this->carrier->is_sandbox,
            'close_date' => $closeDate,
            'account_number' => $this->carrier->account_number,
        ]);

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-locale'     => 'en_US',
                ])
                ->post("{$endpoint}/ship/v1/shipments/packages/close", $payload);

            $data = $response->json();

            if (!$response->successful()) {
                $errorMessage = $data['errors'][0]['message']
                    ?? $data['error_description']
                    ?? $response->body();

                Log::warning('FedEx: closeShipments failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $payload,
                ]);

                // If no shipments to close, this is not an error
                if (str_contains(strtolower($errorMessage), 'no shipment') ||
                    str_contains(strtolower($errorMessage), 'nothing to close')) {
                    return [
                        'success' => true,
                        'confirmation_number' => null,
                        'manifest' => null,
                        'message' => 'No shipments to close for this date',
                        'raw' => $data,
                    ];
                }

                throw new \RuntimeException("FedEx close shipments failed: {$errorMessage}");
            }

            $confirmationNumber = $data['output']['closeDocuments'][0]['confirmationNumber'] ?? null;
            $manifestBase64 = $data['output']['closeDocuments'][0]['encodedDocument'] ?? null;

            Log::info('FedEx: closeShipments success', [
                'confirmation_number' => $confirmationNumber,
                'close_date' => $closeDate,
                'has_manifest' => !empty($manifestBase64),
            ]);

            return [
                'success' => true,
                'confirmation_number' => $confirmationNumber,
                'manifest' => $manifestBase64,
                'message' => 'Shipments closed successfully',
                'raw' => $data,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FedEx: closeShipments exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException("FedEx close shipments failed: {$e->getMessage()}");
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
