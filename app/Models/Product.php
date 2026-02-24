<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'warehouse_id',
        'rack_id',
        'category_id',
        'brand_id',
        'short_description',
        'description',
        'price',
        'product_image',
        'is_featured',
        'active_status',
        'delete_status',
    ];

    protected $with = ['product_meta'];

    public function product_meta()
    {
        return $this->hasMany(ProductMeta::class);
    }

    /**
     * Override attribute retrieval to transform product_meta
     */
    public function getAttribute($key)
    {
        // For product_meta, ensure relationship is loaded and transform it
        if ($key === 'product_meta') {
            // Load the relationship if not already loaded
            if (!$this->relationLoaded('product_meta')) {
                $this->load('product_meta');
            }

            $value = $this->getRelation('product_meta');

            // Transform to key-value pairs
            if ($value instanceof \Illuminate\Database\Eloquent\Collection) {
                return ProductMeta::toKeyValue($value);
            }

            return $value;
        }

        return parent::getAttribute($key);
    }

    /**
     * Override array conversion to include transformed product_meta
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Ensure product_meta is transformed in array output
        if (isset($array['product_meta']) && is_array($array['product_meta'])) {
            // If it's already an array of meta objects, transform it
            if (isset($array['product_meta'][0]['meta_key'])) {
                $collection = collect($array['product_meta']);
                $array['product_meta'] = $collection->mapWithKeys(function ($item) {
                    return [$item['meta_key'] => $item['meta_value']];
                })->toArray();
            }
        }

        return $array;
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand() {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function product_stocks() {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    public function sales_channels() {
        return $this->belongsToMany(SalesChannel::class, 'sales_channel_product')
            ->withPivot('listing_url', 'external_listing_id', 'listing_status', 'listing_error', 'listing_format', 'last_synced_at')
            ->withTimestamps()
            ->using(SalesChannelProduct::class);
    }

    /**
     * Get active sales channels (where listing is active)
     */
    public function activeSalesChannels()
    {
        return $this->belongsToMany(SalesChannel::class, 'sales_channel_product')
            ->withPivot('listing_url', 'external_listing_id', 'listing_status', 'listing_error', 'listing_format', 'last_synced_at')
            ->withTimestamps()
            ->wherePivot('listing_status', SalesChannelProduct::STATUS_ACTIVE)
            ->using(SalesChannelProduct::class);
    }

    /**
     * Check if product is listed on a specific sales channel
     */
    public function isListedOn(int $salesChannelId): bool
    {
        return $this->sales_channels()->where('sales_channel_id', $salesChannelId)->exists();
    }

    /**
     * Get listing status for a specific sales channel
     */
    public function getListingStatus(int $salesChannelId): ?string
    {
        $channel = $this->sales_channels()->where('sales_channel_id', $salesChannelId)->first();
        return $channel?->pivot?->listing_status;
    }

    /**
     * Get the product image URL
     * Handles three sources: external URL, storage path, or uploads path
     */
    public function getImageUrl(): ?string
    {
        if (empty($this->product_image)) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (filter_var($this->product_image, FILTER_VALIDATE_URL)) {
            return $this->product_image;
        }

        // If it starts with 'products/', it's in storage
        if (str_starts_with($this->product_image, 'products/')) {
            return asset('storage/' . $this->product_image);
        }

        // Otherwise, it's in the uploads folder
        return asset('uploads/' . $this->product_image);
    }
}
