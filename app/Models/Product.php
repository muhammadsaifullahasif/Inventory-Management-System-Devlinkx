<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        'is_bundle',
        'bundle_type',
        'active_status',
        'delete_status',
    ];

    protected $with = ['product_meta'];

    protected $casts = [
        'is_bundle' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function product_meta()
    {
        return $this->hasMany(ProductMeta::class);
    }

    /**
     * Shared catalog filters (search/category/brand/sales channel/product
     * type/stock+warehouse+rack/date range) — same logic ProductController
     * uses for the products list and export, extracted so other queries
     * (e.g. Market Research) can filter through the product relation.
     */
    public function scopeCatalogFilter($query, \Illuminate\Http\Request $request)
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('sales_channel_id')) {
            $query->whereHas('sales_channels', function ($q) use ($request) {
                $q->where('sales_channels.id', $request->sales_channel_id);
            });
        }

        if ($request->filled('product_type')) {
            if ($request->product_type === 'bundle') {
                $query->where('is_bundle', true);
            } elseif ($request->product_type === 'regular') {
                $query->where('is_bundle', false);
            }
        }

        $warehouseId = $request->warehouse_id;
        $rackId = $request->rack_id;
        $stockStatus = $request->stock_status;

        $applyStockLocationFilter = function ($stockQuery) use ($warehouseId, $rackId) {
            if ($warehouseId) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }
            if ($rackId) {
                $stockQuery->where('rack_id', $rackId);
            }
        };

        $applyOutOfStockComponentCondition = function ($componentQuery) use ($warehouseId, $rackId) {
            $sql = "(
                SELECT COALESCE(SUM(CAST(ps.quantity AS decimal(15,4))), 0)
                FROM product_stocks ps
                WHERE ps.product_id = product_bundle_components.component_product_id
                    AND ps.active_status = '1'
                    AND ps.delete_status = '0'";

            $bindings = [];

            if ($warehouseId) {
                $sql .= " AND ps.warehouse_id = ?";
                $bindings[] = $warehouseId;
            }

            if ($rackId) {
                $sql .= " AND ps.rack_id = ?";
                $bindings[] = $rackId;
            }

            $sql .= ") < product_bundle_components.quantity_required";

            $componentQuery->whereRaw($sql, $bindings);
        };

        if ($warehouseId || $rackId || $stockStatus) {
            $query->where(function ($q) use (
                $warehouseId,
                $rackId,
                $stockStatus,
                $applyStockLocationFilter,
                $applyOutOfStockComponentCondition
            ) {
                if ($stockStatus === 'out_of_stock') {
                    $q->where(function ($stockStatusQuery) use ($applyStockLocationFilter, $applyOutOfStockComponentCondition) {
                        $stockStatusQuery->where(function ($regularQuery) use ($applyStockLocationFilter) {
                            $regularQuery->where('is_bundle', false)
                                ->where(function ($outOfStockQuery) use ($applyStockLocationFilter) {
                                    $outOfStockQuery->whereHas('product_stocks', function ($stockQuery) use ($applyStockLocationFilter) {
                                        $applyStockLocationFilter($stockQuery);
                                        $stockQuery->where('quantity', '<=', 0);
                                    })->orWhereDoesntHave('product_stocks', function ($stockQuery) use ($applyStockLocationFilter) {
                                        $applyStockLocationFilter($stockQuery);
                                    });
                                });
                        })
                        ->orWhere(function ($bundleQuery) use ($applyOutOfStockComponentCondition) {
                            $bundleQuery->where('is_bundle', true)
                                ->where(function ($bundleOutOfStockQuery) use ($applyOutOfStockComponentCondition) {
                                    $bundleOutOfStockQuery->whereDoesntHave('bundleComponents')
                                        ->orWhereHas('bundleComponents', function ($componentQuery) use ($applyOutOfStockComponentCondition) {
                                            $applyOutOfStockComponentCondition($componentQuery);
                                        });
                                });
                        });
                    });
                } elseif ($stockStatus === 'in_stock') {
                    $q->where(function ($stockStatusQuery) use ($applyStockLocationFilter, $applyOutOfStockComponentCondition) {
                        $stockStatusQuery->where(function ($regularQuery) use ($applyStockLocationFilter) {
                            $regularQuery->where('is_bundle', false)
                                ->whereHas('product_stocks', function ($stockQuery) use ($applyStockLocationFilter) {
                                    $applyStockLocationFilter($stockQuery);
                                    $stockQuery->where('quantity', '>', 0);
                                });
                        })
                        ->orWhere(function ($bundleQuery) use ($applyOutOfStockComponentCondition) {
                            $bundleQuery->where('is_bundle', true)
                                ->whereHas('bundleComponents')
                                ->whereDoesntHave('bundleComponents', function ($componentQuery) use ($applyOutOfStockComponentCondition) {
                                    $applyOutOfStockComponentCondition($componentQuery);
                                });
                        });
                    });
                } else {
                    $q->whereHas('product_stocks', function ($stockQuery) use ($applyStockLocationFilter) {
                        $applyStockLocationFilter($stockQuery);
                    });

                    if ($warehouseId && !$rackId) {
                        $q->orWhere(function ($bundleQuery) use ($warehouseId) {
                            $bundleQuery->where('is_bundle', true)
                                ->whereHas('bundleComponents.product.product_stocks', function ($componentStockQuery) use ($warehouseId) {
                                    $componentStockQuery->where('warehouse_id', $warehouseId);
                                });
                        });
                    }
                }
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * Get bundle components (for bundle products)
     */
    public function bundleComponents()
    {
        return $this->hasMany(ProductBundleComponent::class, 'bundle_product_id');
    }

    /**
     * Get bundles this product is a component of
     */
    public function isComponentOf()
    {
        return $this->hasMany(ProductBundleComponent::class, 'component_product_id');
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

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function priceComparisons()
    {
        return $this->hasMany(ProductPriceComparison::class);
    }

    public function sales_channels() {
        return $this->belongsToMany(SalesChannel::class, 'sales_channel_product')
            ->withPivot('listing_url', 'external_listing_id', 'listing_status', 'listing_error', 'listing_format', 'last_synced_at', 'last_synced_quantity', 'visible_quantity', 'sync_enabled', 'last_sync_attempted_at', 'last_sync_error')
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

    /**
     * Static array to track products being calculated (prevents infinite recursion)
     */
    private static array $calculatingStock = [];

    /**
     * Get available stock quantity
     * For regular products: sum from product_stocks
     * For bundle products: calculated from components (minimum available)
     */
    public function getAvailableStockAttribute(): int
    {
        // Prevent infinite recursion for circular bundle references
        if (isset(self::$calculatingStock[$this->id])) {
            return 0;
        }

        if (!$this->is_bundle) {
            // Regular product - sum from product_stocks
            return (int) ProductStock::where('product_id', $this->id)
                ->where('active_status', '1')
                ->where('delete_status', '0')
                ->sum(DB::raw('CAST(quantity AS UNSIGNED)'));
        }

        // Mark this product as being calculated
        self::$calculatingStock[$this->id] = true;

        try {
            // Bundle product - calculate from components
            if (!$this->relationLoaded('bundleComponents')) {
                $this->load('bundleComponents.product');
            }

            if ($this->bundleComponents->isEmpty()) {
                return 0;
            }

            $minStock = PHP_INT_MAX;

            foreach ($this->bundleComponents as $component) {
                // Skip if component product doesn't exist
                if (!$component->product) {
                    continue;
                }

                // For component products, get stock directly from product_stocks (don't recurse into bundles)
                if ($component->product->is_bundle) {
                    // Nested bundle - recursively calculate (with protection)
                    $componentStock = $component->product->available_stock;
                } else {
                    // Regular product - direct query (faster)
                    $componentStock = (int) ProductStock::where('product_id', $component->product->id)
                        ->where('active_status', '1')
                        ->where('delete_status', '0')
                        ->sum(DB::raw('CAST(quantity AS UNSIGNED)'));
                }

                $possibleBundles = (int) floor($componentStock / max(1, $component->quantity_required));
                $minStock = min($minStock, $possibleBundles);
            }

            return $minStock === PHP_INT_MAX ? 0 : $minStock;
        } finally {
            // Always clean up the tracking flag
            unset(self::$calculatingStock[$this->id]);
        }
    }

    /**
     * Get stock details for bundle (which component is limiting)
     */
    public function getBundleStockDetails(): array
    {
        if (!$this->is_bundle) {
            return [];
        }

        // Load components if not loaded
        if (!$this->relationLoaded('bundleComponents')) {
            $this->load('bundleComponents.product');
        }

        $details = [];
        $minStock = PHP_INT_MAX;
        $limitingComponent = null;

        foreach ($this->bundleComponents as $component) {
            // Skip if component product doesn't exist
            if (!$component->product) {
                continue;
            }

            // Get component stock (uses the protected available_stock accessor)
            $componentStock = $component->product->available_stock;
            $quantityRequired = max(1, $component->quantity_required);
            $possibleBundles = (int) floor($componentStock / $quantityRequired);

            $details[] = [
                'product_name' => $component->product->name ?? 'Unknown',
                'product_sku' => $component->product->sku ?? 'N/A',
                'required_qty' => $component->quantity_required,
                'available_stock' => $componentStock,
                'possible_bundles' => $possibleBundles,
            ];

            if ($possibleBundles < $minStock) {
                $minStock = $possibleBundles;
                $limitingComponent = $component->product->name ?? 'Unknown';
            }
        }

        return [
            'available_bundles' => $minStock === PHP_INT_MAX ? 0 : $minStock,
            'limiting_component' => $limitingComponent,
            'components' => $details,
        ];
    }

    /**
     * Get bundle stock details per warehouse
     * Returns available bundle quantity for each warehouse (including incomplete ones)
     */
    public function getBundleStockByWarehouse(): array
    {
        if (!$this->is_bundle) {
            return [];
        }

        if (!$this->relationLoaded('bundleComponents')) {
            $this->load('bundleComponents.product.product_stocks.warehouse');
        }

        if ($this->bundleComponents->isEmpty()) {
            return [];
        }

        // Get all warehouses that have at least one component
        $warehouses = [];
        $allWarehouses = Warehouse::where('active_status', '1')->where('delete_status', '0')->get();

        // Initialize all warehouses
        foreach ($allWarehouses as $warehouse) {
            $warehouses[$warehouse->id] = [
                'warehouse' => $warehouse,
                'available_bundles' => PHP_INT_MAX,
                'components' => [],
                'has_all_components' => true,
                'missing_components' => [],
            ];
        }

        // Calculate bundle stock for each warehouse
        foreach ($this->bundleComponents as $component) {
            foreach ($warehouses as $warehouseId => &$warehouseData) {
                $componentStock = $component->product->product_stocks
                    ->where('warehouse_id', $warehouseId)
                    ->sum('quantity');

                $possibleBundles = (int) floor($componentStock / $component->quantity_required);

                // If component stock is 0, mark as missing
                if ($componentStock == 0) {
                    $warehouseData['has_all_components'] = false;
                    $warehouseData['missing_components'][] = $component->product->name;
                }

                // Update minimum available bundles for this warehouse
                $warehouseData['available_bundles'] = min(
                    $warehouseData['available_bundles'],
                    $possibleBundles
                );

                $warehouseData['components'][] = [
                    'product_name' => $component->product->name,
                    'product_sku' => $component->product->sku,
                    'required_qty' => $component->quantity_required,
                    'available_stock' => $componentStock,
                    'possible_bundles' => $possibleBundles,
                    'is_missing' => $componentStock == 0,
                ];
            }
        }

        // Convert PHP_INT_MAX to 0 for warehouses with incomplete data
        foreach ($warehouses as &$warehouse) {
            if ($warehouse['available_bundles'] === PHP_INT_MAX) {
                $warehouse['available_bundles'] = 0;
            }
        }

        return $warehouses;
    }
}
