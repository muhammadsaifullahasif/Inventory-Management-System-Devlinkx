<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $fillable = [
        'name',
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
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'platform_notifications_enabled' => 'boolean',
        'platform_notification_events' => 'array',
        'notification_subscriptions' => 'array',
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
}
