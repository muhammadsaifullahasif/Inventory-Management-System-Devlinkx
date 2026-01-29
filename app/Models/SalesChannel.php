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
            ->withPivot('listing_url', 'external_listing_id')
            ->withTimestamps();
    }
}
