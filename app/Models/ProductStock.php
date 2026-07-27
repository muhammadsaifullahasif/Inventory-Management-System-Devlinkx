<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'rack_id',
        'quantity',
        'previous_quantity',
        'avg_cost',
        'active_status',
        'delete_status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'previous_quantity' => 'decimal:4',
        'avg_cost' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get total stock value for this stock record
     */
    public function getTotalValueAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->avg_cost, 2);
    }
}
