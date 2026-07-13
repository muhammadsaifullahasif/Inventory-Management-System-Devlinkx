<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_return_id',
        'order_item_id',
        'product_id',
        'quantity',
        'restocked',
        'restocked_at',
    ];

    protected $casts = [
        'restocked' => 'boolean',
        'restocked_at' => 'datetime',
    ];

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Restock the linked order item's full quantity and mark this return item restocked.
     * Idempotent — OrderItem::restoreInventory() no-ops if already restored.
     */
    public function restock(): bool
    {
        if ($this->restocked) {
            return true;
        }

        $orderItem = $this->orderItem;
        if (!$orderItem) {
            return false;
        }

        $result = $orderItem->restoreInventory();

        if ($result) {
            $this->update([
                'restocked' => true,
                'restocked_at' => now(),
            ]);
        }

        return $result;
    }
}
