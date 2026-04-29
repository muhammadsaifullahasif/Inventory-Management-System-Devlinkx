<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log for inventory sync operations.
 *
 * @property int $id
 * @property int $product_id
 * @property int $sales_channel_id
 * @property int|null $previous_quantity
 * @property int $new_quantity
 * @property int $central_stock
 * @property int $visible_threshold
 * @property string $status pending|success|failed|skipped
 * @property string|null $skip_reason
 * @property string|null $error_message
 * @property string|null $ebay_item_id
 * @property string|null $trigger_source
 * @property string|null $trigger_reference
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InventorySyncLog extends Model
{
    protected $fillable = [
        'product_id',
        'sales_channel_id',
        'previous_quantity',
        'new_quantity',
        'central_stock',
        'visible_threshold',
        'status',
        'skip_reason',
        'error_message',
        'ebay_item_id',
        'trigger_source',
        'trigger_reference',
    ];

    protected $casts = [
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
        'central_stock' => 'integer',
        'visible_threshold' => 'integer',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    // Trigger sources
    public const TRIGGER_ORDER = 'order';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_SCHEDULED = 'scheduled';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * Scope: successful syncs only.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope: failed syncs only.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: recent logs (last N hours).
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get quantity change.
     */
    public function getQuantityChangeAttribute(): ?int
    {
        if ($this->previous_quantity === null) {
            return null;
        }
        return $this->new_quantity - $this->previous_quantity;
    }

    /**
     * Check if this was a successful sync.
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if this was a failed sync.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if sync was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }
}
