<?php

namespace App\Services\Ebay;

use Exception;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level HTTP client for eBay Trading API.
 *
 * Single source of truth for:
 * - API transport (HTTP calls with proper headers)
 * - Token lifecycle (check expiry, refresh, save)
 * - XML → Array conversion (all responses converted at this level)
 * - Error checking
 *
 * Usage:
 *   $client = app(EbayApiClient::class);
 *   $channel = $client->ensureValidToken($salesChannel);
 *   $response = $client->call($channel, 'GetItem', $xmlRequest);
 *   // $response is already a PHP array
 */
class EbayApiClient
{
    public const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    public const EBAY_TOKEN_URL = 'https://api.ebay.com/identity/v1/oauth2/token';
    public const API_COMPATIBILITY_LEVEL = '967';
    public const API_SITE_ID = '0'; // US

    // Default timeouts (seconds)
    private const DEFAULT_TIMEOUT = 120;
    private const DEFAULT_CONNECT_TIMEOUT = 30;

    /**
     * Execute a Trading API call and return the response as a PHP array.
     *
     * Options:
     *   'timeout'        => int (seconds, default 120)
     *   'connectTimeout' => int (seconds, default 30)
     *   'rawXml'         => bool (return raw XML string instead of array, default false)
     */
    public function call(SalesChannel $salesChannel, string $callName, string $xmlRequest, array $options = []): array|string
    {
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $connectTimeout = $options['connectTimeout'] ?? self::DEFAULT_CONNECT_TIMEOUT;
        $rawXml = $options['rawXml'] ?? false;

        $response = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'X-EBAY-API-SITEID' => self::API_SITE_ID,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => self::API_COMPATIBILITY_LEVEL,
                'X-EBAY-API-CALL-NAME' => $callName,
                'X-EBAY-API-IAF-TOKEN' => $salesChannel->access_token,
                'Content-Type' => 'text/xml',
            ])
            ->withBody($xmlRequest, 'text/xml')
            ->post(self::EBAY_API_URL);

        if ($response->failed()) {
            Log::error("eBay {$callName} Failed", [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw new Exception("eBay {$callName} failed: " . $response->body());
        }

        $xmlBody = $response->body();

        if ($rawXml) {
            return $xmlBody;
        }

        return $this->xmlToArray($xmlBody);
    }

    // =========================================
    // TOKEN MANAGEMENT
    // =========================================

    /**
     * Get a SalesChannel with a guaranteed-valid access token.
     * Refreshes automatically if expired or expiring within 5 minutes.
     */
    public function ensureValidToken(SalesChannel $salesChannel): SalesChannel
    {
        if (!$this->isTokenExpired($salesChannel)) {
            return $salesChannel;
        }

        return $this->refreshToken($salesChannel);
    }

    /**
     * Check if the access token is expired or about to expire (5-min buffer).
     */
    public function isTokenExpired(SalesChannel $salesChannel): bool
    {
        if (empty($salesChannel->access_token) || empty($salesChannel->access_token_expires_at)) {
            return true;
        }

        return now()->addMinutes(5)->greaterThanOrEqualTo($salesChannel->access_token_expires_at);
    }

    /**
     * Refresh the access token, save to DB, and return updated SalesChannel.
     */
    public function refreshToken(SalesChannel $salesChannel): SalesChannel
    {
        if (empty($salesChannel->refresh_token)) {
            throw new Exception('No refresh token available. Please re-authorize with eBay.');
        }

        if ($salesChannel->refresh_token_expires_at && now()->greaterThanOrEqualTo($salesChannel->refresh_token_expires_at)) {
            throw new Exception('Refresh token has expired. Please re-authorize with eBay.');
        }

        $tokenData = $this->refreshUserToken($salesChannel);

        $salesChannel->access_token = $tokenData['access_token'];
        $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

        if (isset($tokenData['refresh_token'])) {
            $salesChannel->refresh_token = $tokenData['refresh_token'];
            if (isset($tokenData['refresh_token_expires_in'])) {
                $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
            }
        }

        $salesChannel->save();

        Log::info('eBay access token refreshed', [
            'sales_channel_id' => $salesChannel->id,
            'new_expires_at' => $salesChannel->access_token_expires_at,
        ]);

        return $salesChannel;
    }

    /**
     * Call the OAuth endpoint to refresh the user access token.
     */
    public function refreshUserToken(SalesChannel $salesChannel): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
            ])
            ->asForm()
            ->post(self::EBAY_TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $salesChannel->refresh_token,
                'scope' => $salesChannel->user_scopes,
            ]);

        if ($response->failed()) {
            Log::error('eBay Refresh Token Failed', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw new Exception('eBay refresh token failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Exchange an authorization code for initial access + refresh tokens.
     */
    public function exchangeAuthorizationCode(SalesChannel $salesChannel, string $code): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
            ])
            ->asForm()
            ->post(self::EBAY_TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $salesChannel->ru_name,
            ]);

        if ($response->failed()) {
            throw new Exception('eBay user token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Generate the OAuth authorization URL for user consent.
     */
    public function getAuthorizationUrl(SalesChannel $salesChannel, ?string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));

        return 'https://auth.ebay.com/oauth2/authorize?' . http_build_query([
            'client_id' => $salesChannel->client_id,
            'redirect_uri' => $salesChannel->ru_name,
            'response_type' => 'code',
            'scope' => $salesChannel->user_scopes,
            'state' => $state,
        ]);
    }

    // =========================================
    // XML UTILITIES
    // =========================================

    /**
     * Convert an XML string to a PHP array.
     * Handles attributes (prefixed with @), multiple same-named children, and text nodes.
     */
    public function xmlToArray(string $xmlString): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            $errorMessages = array_map(fn($e) => trim($e->message), $errors);
            throw new Exception('Failed to parse eBay XML response: ' . implode(', ', $errorMessages));
        }

        return $this->xmlNodeToArray($xml);
    }

    /**
     * Recursively convert a SimpleXMLElement to a PHP array.
     */
    protected function xmlNodeToArray(\SimpleXMLElement $node): array|string
    {
        $result = [];

        // Get attributes
        foreach ($node->attributes() as $attrName => $attrValue) {
            $result['@' . $attrName] = (string) $attrValue;
        }

        // Get child elements
        $children = $node->children();

        if ($children->count() === 0) {
            $text = trim((string) $node);
            if (!empty($result)) {
                // Has attributes — add text as @value
                if (!empty($text)) {
                    $result['@value'] = $text;
                }
                return $result;
            }
            return $text;
        }

        // Process children
        $childArray = [];
        foreach ($children as $childName => $childNode) {
            $childValue = $this->xmlNodeToArray($childNode);

            if (isset($childArray[$childName])) {
                if (!is_array($childArray[$childName]) || !isset($childArray[$childName][0])) {
                    $childArray[$childName] = [$childArray[$childName]];
                }
                $childArray[$childName][] = $childValue;
            } else {
                $childArray[$childName] = $childValue;
            }
        }

        return array_merge($result, $childArray);
    }

    /**
     * Clean SOAP XML by removing namespace prefixes.
     * Used specifically for webhook payloads which may arrive wrapped in SOAP envelopes.
     */
    public function cleanSoapXml(string $xmlContent): string
    {
        $cleanedXml = preg_replace('/<\?xml[^>]*\?>/', '', $xmlContent);
        $cleanedXml = preg_replace('/\s+xmlns(:[a-zA-Z0-9_-]+)?="[^"]*"/', '', $cleanedXml);
        $cleanedXml = preg_replace('/<(\/?)([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '<$1$3', $cleanedXml);
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:([a-zA-Z0-9_-]+)=/', ' $1=', $cleanedXml);
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+="[^"]*"/', '', $cleanedXml);
        $cleanedXml = preg_replace('/\s+/', ' ', $cleanedXml);
        $cleanedXml = preg_replace('/\s+>/', '>', $cleanedXml);
        $cleanedXml = '<?xml version="1.0" encoding="UTF-8"?>' . trim($cleanedXml);

        return $cleanedXml;
    }

    /**
     * Escape special characters for safe XML embedding.
     */
    public function escapeXml(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check for API-level errors in a parsed response array.
     * Throws an Exception if the Ack field indicates failure.
     */
    public function checkForErrors(array $response): void
    {
        $ack = $response['Ack'] ?? '';

        if ($ack === 'Failure') {
            $errors = $response['Errors'] ?? [];
            // Handle single error (not wrapped in array)
            if (isset($errors['ShortMessage'])) {
                $errors = [$errors];
            }
            $short = $errors[0]['ShortMessage'] ?? 'Unknown error';
            $long = $errors[0]['LongMessage'] ?? '';
            throw new Exception("eBay API Error: {$short}" . ($long ? " - {$long}" : ''));
        }
    }

    /**
     * Extract structured errors and warnings from a parsed response array.
     */
    public function extractErrorsAndWarnings(array $response): array
    {
        $result = ['errors' => [], 'warnings' => []];
        $errors = $response['Errors'] ?? [];

        // Handle single error (not wrapped in array)
        if (isset($errors['ShortMessage'])) {
            $errors = [$errors];
        }

        foreach ($errors as $error) {
            $entry = [
                'code' => $error['ErrorCode'] ?? '',
                'short_message' => $error['ShortMessage'] ?? '',
                'long_message' => $error['LongMessage'] ?? '',
                'severity' => $error['SeverityCode'] ?? '',
            ];

            if (($entry['severity'] ?? '') === 'Warning') {
                $result['warnings'][] = $entry;
            } else {
                $result['errors'][] = $entry;
            }
        }

        return $result;
    }
}
