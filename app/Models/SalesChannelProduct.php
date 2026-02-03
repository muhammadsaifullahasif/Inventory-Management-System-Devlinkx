<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SalesChannelProduct extends Pivot
{
    protected $table = 'sales_channel_product';

    protected $fillable = [
        'product_id',
        'sales_channel_id',
        'listing_url',
        'external_listing_id',
        'listing_status',
        'listing_error',
        'listing_format',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    // Listing status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_DRAFT = 'draft';
    const STATUS_ENDED = 'ended';
    const STATUS_ERROR = 'error';

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the sales channel
     */
    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * Check if listing is active
     */
    public function isActive(): bool
    {
        return $this->listing_status === self::STATUS_ACTIVE;
    }

    /**
     * Check if listing is draft
     */
    public function isDraft(): bool
    {
        return $this->listing_status === self::STATUS_DRAFT;
    }

    /**
     * Check if listing is ended
     */
    public function isEnded(): bool
    {
        return $this->listing_status === self::STATUS_ENDED;
    }

    /**
     * Mark as synced
     */
    public function markAsSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }
}
