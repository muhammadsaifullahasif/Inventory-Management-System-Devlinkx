<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $fillable = [
        'name',
        'type',
        'external_account_id',
        'external_account_ids', // Array of all platform account IDs for this seller
        'client_id',
        'client_secret',
        'ru_name',
        'provider_config',
        'user_scopes',
        'authorization_code',
        'access_token',
        'access_token_expires_at',
        'refresh_token',
        'refresh_token_expires_at',
        'additional_info',
        'status',
        'active_status',
        'delete_status',
        // Notification fields
        'platform_notifications_enabled',
        'platform_notification_events',
        'notification_destination_id',
        'notification_verification_token',
        'notification_subscriptions',
        'webhook_url',
        // Accounting fields
        'receivable_account_id',
        'sales_account_id',
    ];

    // Secrets: encrypted at rest with APP_KEY, and kept out of toArray()/JSON
    // (which also keeps them out of the audit log, see AuditLogger::filterFields).
    protected $hidden = [
        'client_secret',
        'authorization_code',
        'access_token',
        'refresh_token',
        'notification_verification_token',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'authorization_code' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'notification_verification_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'platform_notifications_enabled' => 'boolean',
        'platform_notification_events' => 'array',
        'notification_subscriptions' => 'array',
        'external_account_ids' => 'array', // Cast to array
        'provider_config' => 'array',
    ];

    /**
     * eBay's OAuth redirect-name concept — has no equivalent on other platforms,
     * so it lives inside provider_config rather than as its own column.
     */
    protected function ruName(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->provider_config ?? [])['ru_name'] ?? null,
            // Raw attribute values bypass the 'array' cast, so encode it ourselves.
            set: fn ($value) => ['provider_config' => json_encode(array_merge($this->provider_config ?? [], ['ru_name' => $value]))],
        );
    }

    /**
     * eBay keyset Dev ID — needed to verify the NotificationSignature on
     * Platform Notifications sent to the webhook.
     */
    protected function devId(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->provider_config ?? [])['dev_id'] ?? null,
            set: fn ($value) => ['provider_config' => json_encode(array_merge($this->provider_config ?? [], ['dev_id' => $value]))],
        );
    }

    /*
     * eBay keyset this channel's tokens belong to. Stores connected through
     * "Login with eBay" use the shared app in config/services.php; values on
     * the row only remain on channels connected before that, and are cleared
     * by usesSharedEbayApp() when they are reconnected.
     */
    public function ebayClientId(): ?string
    {
        return $this->client_id ?: config('services.ebay.client_id');
    }

    public function ebayClientSecret(): ?string
    {
        return $this->client_secret ?: config('services.ebay.client_secret');
    }

    public function ebayDevId(): ?string
    {
        return $this->dev_id ?: config('services.ebay.dev_id');
    }

    public function ebayRuName(): ?string
    {
        return $this->ru_name ?: config('services.ebay.ru_name');
    }

    public function ebayScopes(): ?string
    {
        return $this->user_scopes ?: config('services.ebay.scopes');
    }

    /**
     * Drop any per-channel keyset so the shared app's credentials apply.
     */
    public function usesSharedEbayApp(): static
    {
        $this->client_id = null;
        $this->client_secret = null;
        $this->user_scopes = null;
        $this->provider_config = array_diff_key($this->provider_config ?? [], array_flip(['ru_name', 'dev_id'])) ?: null;

        return $this;
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'sales_channel_product')
            ->withPivot('listing_url', 'external_listing_id', 'listing_status', 'listing_error', 'listing_format', 'last_synced_at')
            ->withTimestamps()
            ->using(SalesChannelProduct::class);
    }

    /**
     * Get active products (where listing is active)
     */
    public function activeProducts()
    {
        return $this->belongsToMany(Product::class, 'sales_channel_product')
            ->withPivot('listing_url', 'external_listing_id', 'listing_status', 'listing_error', 'listing_format', 'last_synced_at')
            ->withTimestamps()
            ->wherePivot('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->using(SalesChannelProduct::class);
    }

    /**
     * Check if this is an eBay channel
     */
    public function isEbay(): bool
    {
        return $this->type === 'ebay';
    }

    /**
     * Check if channel has valid access token
     */
    public function hasValidToken(): bool
    {
        return !empty($this->access_token) &&
               $this->access_token_expires_at &&
               $this->access_token_expires_at->isFuture();
    }

    /**
     * Get the bank account for this sales channel (stored in receivable_account_id)
     */
    public function bankAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivable_account_id');
    }

    /**
     * Alias for bankAccount for backwards compatibility
     */
    public function receivableAccount()
    {
        return $this->bankAccount();
    }

    /**
     * Get the sales revenue account for this sales channel
     */
    public function salesAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_account_id');
    }

    /**
     * Check if this sales channel has accounting accounts set up
     */
    public function hasAccountingSetup(): bool
    {
        return !empty($this->receivable_account_id) && !empty($this->sales_account_id);
    }
}
