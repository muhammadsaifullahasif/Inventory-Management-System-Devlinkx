<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBundleComponent extends Model
{
    protected $fillable = [
        'bundle_product_id',
        'component_product_id',
        'quantity_required',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
    ];

    /**
     * Get the bundle product (parent)
     */
    public function bundleProduct()
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    /**
     * Get the component product (child)
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
