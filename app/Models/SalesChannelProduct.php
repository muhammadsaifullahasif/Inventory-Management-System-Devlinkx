<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        // Inventory sync fields
        'last_synced_quantity',
        'visible_quantity',
        'sync_enabled',
        'last_sync_attempted_at',
        'last_sync_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_sync_attempted_at' => 'datetime',
        'last_synced_quantity' => 'integer',
        'visible_quantity' => 'integer',
        'sync_enabled' => 'boolean',
    ];

    protected $attributes = [
        'visible_quantity' => 10,
        'sync_enabled' => true,
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

    /**
     * Mark inventory as synced with quantity.
     */
    public function markInventorySynced(int $quantity): void
    {
        $this->update([
            'last_synced_quantity' => $quantity,
            'last_synced_at' => now(),
            'last_sync_attempted_at' => now(),
            'last_sync_error' => null,
        ]);
    }

    /**
     * Mark sync as failed.
     */
    public function markSyncFailed(string $error): void
    {
        $this->update([
            'last_sync_attempted_at' => now(),
            'last_sync_error' => $error,
        ]);
    }

    /**
     * Check if sync is enabled and listing is active.
     */
    public function canSync(): bool
    {
        return $this->sync_enabled && $this->listing_status === self::STATUS_ACTIVE;
    }

    /**
     * Get sync logs for this listing.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(InventorySyncLog::class, 'sales_channel_id', 'sales_channel_id')
            ->where('product_id', $this->product_id);
    }

    /**
     * Check if listing needs sync (quantity changed).
     */
    public function needsSync(int $newQuantity): bool
    {
        if (!$this->canSync()) {
            return false;
        }

        // First sync
        if ($this->last_synced_quantity === null) {
            return true;
        }

        return $this->last_synced_quantity !== $newQuantity;
    }

    /**
     * Get time since last successful sync.
     */
    public function getTimeSinceLastSyncAttribute(): ?string
    {
        if (!$this->last_synced_at) {
            return null;
        }
        return $this->last_synced_at->diffForHumans();
    }

    /**
     * Check if there's a recent sync error.
     */
    public function hasRecentError(): bool
    {
        return !empty($this->last_sync_error);
    }

    /**
     * Scope: sync-enabled listings.
     */
    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    /**
     * Scope: active and sync-enabled.
     */
    public function scopeSyncable($query)
    {
        return $query->where('listing_status', self::STATUS_ACTIVE)
            ->where('sync_enabled', true);
    }
}
