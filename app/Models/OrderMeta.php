<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'meta_key',
        'meta_value',
    ];

    /**
     * Get the order this meta belongs to
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get meta value as JSON decoded array
     */
    public function getValueAsArrayAttribute(): ?array
    {
        if (empty($this->meta_value)) {
            return null;
        }

        $decoded = json_decode($this->meta_value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Set meta value from array (auto JSON encode)
     */
    public function setValueFromArray(array $value): void
    {
        $this->meta_value = json_encode($value);
        $this->save();
    }

    /**
     * Scope to find by key
     */
    public function scopeKey($query, string $key)
    {
        return $query->where('meta_key', $key);
    }
}
