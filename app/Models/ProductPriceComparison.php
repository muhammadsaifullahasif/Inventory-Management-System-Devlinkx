<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceComparison extends Model
{
    protected $fillable = [
        'product_id',
        'competitor_seller',
        'competitor_price',
        'currency',
        'items_sold_last_month',
        'ebay_item_id',
        'listing_url',
        'rank',
        'captured_at',
    ];

    protected $casts = [
        'competitor_price' => 'decimal:2',
        'items_sold_last_month' => 'integer',
        'rank' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
