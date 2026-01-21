<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EbayImportLog extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'sales_channel_id',
        'total_listings',
        'total_batches',
        'completed_batches',
        'items_inserted',
        'items_updated',
        'items_failed',
        'status',
        'started_at',
        'completed_at',
        'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the sales channel relationship
     */
    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * Check if import is complete
     */
    public function isComplete(): bool
    {
        return $this->completed_batches >= $this->total_batches;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_batches === 0) {
            return 0;
        }
        
        return round(($this->completed_batches / $this->total_batches) * 100, 2);
    }

    /**
     * Increment completed batches
     */
    public function incrementCompletedBatches(): void
    {
        $this->increment('completed_batches');
        
        if ($this->isComplete()) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Add import statistics
     */
    public function addStatistics(int $inserted, int $updated, int $failed): void
    {
        $this->increment('items_inserted', $inserted);
        $this->increment('items_updated', $updated);
        $this->increment('items_failed', $failed);
    }
}
