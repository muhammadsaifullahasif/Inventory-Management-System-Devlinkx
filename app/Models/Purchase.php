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
        'purchase_status',
        'active_status',
        'delete_status',
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
}
