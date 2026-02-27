<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'warehouse_id',
        'purchase_note',
        'duties_customs',
        'freight_charges',
        'purchase_status',
        'active_status',
        'delete_status',
    ];

    protected $casts = [
        'duties_customs' => 'decimal:2',
        'freight_charges' => 'decimal:2',
    ];

    protected $with = ['purchase_items'];

    public function purchase_items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the total items cost (sum of quantity * price for all items)
     */
    public function getItemsTotalAttribute(): float
    {
        return $this->purchase_items->sum(function ($item) {
            return (float) $item->quantity * (float) $item->price;
        });
    }

    /**
     * Get the grand total (items + duties + freight)
     */
    public function getGrandTotalAttribute(): float
    {
        return $this->items_total + (float) $this->duties_customs + (float) $this->freight_charges;
    }
}
