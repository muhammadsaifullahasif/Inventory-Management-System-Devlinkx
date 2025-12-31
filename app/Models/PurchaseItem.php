<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'barcode',
        'sku',
        'name',
        'quantity',
        'price',
        'note',
        'rack_id',
        'active_status',
        'delete_status',
    ];
}
