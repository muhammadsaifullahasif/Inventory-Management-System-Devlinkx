<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $fillable = [
        'name',
        'ebay_user_id',
        'ebay_user_ids', // Array of all eBay user IDs for this seller
        'client_id',
        'client_secret',
        'ru_name',
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

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'platform_notifications_enabled' => 'boolean',
        'platform_notification_events' => 'array',
        'notification_subscriptions' => 'array',
        'ebay_user_ids' => 'array', // Cast to array
    ];

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
        return !empty($this->client_id) && !empty($this->client_secret);
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
