<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view products'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add products'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit products'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete products'), ['only' => ['destroy']]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with('sales_channels')->orderBy('created_at', 'DESC')->paginate(25);
        // $products = Product::with('sales_channels')->orderBy('created_at', 'DESC')->first();
        // return $products->sales_channels;
        return view('products.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();
        $brands = Brand::all();
        $salesChannels = SalesChannel::where('active_status', 1)->get();
        return view('products.new', compact('categories', 'brands', 'salesChannels'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'product_image' => 'nullable|image|max:2048',
            'is_featured' => 'sometimes|boolean',
            'active_status' => 'sometimes|boolean',
            'sales_channels' => 'nullable|array',
            'sales_channels.*' => 'exists:sales_channels,id',
        ]);

        try {
            DB::beginTransaction();

            // Product creation logic here
            $product = new Product();
            $product->name = $request->name;
            $product->sku = $request->sku;
            $product->barcode = $request->barcode;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            if (empty($request->sale_price)) {
                $product->price = $request->regular_price;
            } else {
                $product->price = $request->sale_price;
            }
            $product->stock_quantity = $request->stock_quantity;

            if($request->has('product_image') != '') {
                $image = $request->product_image;
                $ext = $image->getClientOriginalExtension();
                $imageName = time() . '.' . $ext;

                $image->move(public_path('uploads'), $imageName);
                $product->product_image = $imageName;
            }
            $product->save();

            $product->product_meta()->createMany([
                [
                    'meta_key' => 'weight',
                    'meta_value' => $request->weight,
                ],
                [
                    'meta_key' => 'length',
                    'meta_value' => $request->length,
                ],
                [
                    'meta_key' => 'width',
                    'meta_value' => $request->width,
                ],
                [
                    'meta_key' => 'height',
                    'meta_value' => $request->height,
                ],
                [
                    'meta_key' => 'regular_price',
                    'meta_value' => $request->regular_price,
                ],
                [
                    'meta_key' => 'sale_price',
                    'meta_value' => $request->sale_price,
                ],
                [
                    'meta_key' => 'alert_quantity',
                    'meta_value' => $request->alert_quantity ?? 0,
                ]
            ]);

            // Handle Sales Channels - Create listings
            $selectedChannels = $request->input('sales_channels', []);
            if (!empty($selectedChannels)) {
                $this->syncSalesChannels($product, $selectedChannels, []);
            }

            DB::commit();

            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'An error occurred while creating the product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::findOrFail($id);
        return view('products.show', compact('product'));
    }

    /**
     * Search the specified resource.
     */
    public function search(string $query)
    {
        $products = Product::where('name', 'LIKE', "%$query%")
            ->orWhere('sku', 'LIKE', "%$query%")
            ->orWhere('barcode', 'LIKE', "%$query%")
            ->orWhereHas('category', function ($categoryQuery) use ($query) {
                $categoryQuery->where('name', 'LIKE', "%$query%");
            })
            ->orWhereHas('brand', function ($brandQuery) use ($query) {
                $brandQuery->where('name', 'LIKE', "%$query%");
            })
            ->get();

        return response()->json($products);

        // return view('products.index', compact('products'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $product = Product::with(['sales_channels', 'product_stocks.warehouse', 'product_stocks.rack'])->findOrFail($id);
        $categories = Category::all();
        $brands = Brand::all();
        $salesChannels = SalesChannel::where('active_status', 1)->get();
        return view('products.edit', compact('product', 'categories', 'brands', 'salesChannels'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'product_image' => 'nullable|image|max:2048',
            'is_featured' => 'sometimes|boolean',
            'active_status' => 'sometimes|boolean',
            'sales_channels' => 'nullable|array',
            'sales_channels.*' => 'exists:sales_channels,id',
        ]);

        try {
            DB::beginTransaction();

            $product = Product::with('sales_channels')->findOrFail($id);
            $oldSku = $product->sku;
            $currentChannelIds = $product->sales_channels->pluck('id')->toArray();

            $product->name = $request->name;
            $product->sku = $request->sku;
            $product->barcode = $request->barcode;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            if (empty($request->sale_price)) {
                $product->price = $request->regular_price;
            } else {
                $product->price = $request->sale_price;
            }
            $product->stock_quantity = $request->stock_quantity;

            if($request->has('product_image') != '') {
                $image = $request->product_image;
                $ext = $image->getClientOriginalExtension();
                $imageName = time() . '.' . $ext;

                $image->move(public_path('uploads'), $imageName);
                $product->product_image = $imageName;
            }
            $product->save();

            // Update product meta
            foreach (['weight', 'length', 'width', 'height', 'regular_price', 'sale_price'] as $metaKey) {
                $metaValue = $request->$metaKey;
                $product->product_meta()->updateOrCreate(
                    ['meta_key' => $metaKey],
                    ['meta_value' => $metaValue]
                );
            }

            // Handle Sales Channels sync
            $selectedChannels = $request->input('sales_channels', []);
            $this->syncSalesChannels($product, $selectedChannels, $currentChannelIds, $oldSku !== $request->sku);

            DB::commit();

            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed', ['error' => $e->getMessage(), 'product_id' => $id]);
            return redirect()->back()->with('error', 'An error occurred while updating the product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the product: ' . $e->getMessage());
        }
    }

    /**
     * Print barcode for the specified product.
     */
    public function printBarcode(string $id)
    {
        $product = Product::findOrFail($id);
        return view('products.print-barcode', compact('product'));
    }

    /**
     * Print barcode view for the specified product.
     */
    public function printBarcodeView(Request $request, string $id)
    {
        $product = Product::findOrFail($id);
        $quantity = (int) $request->get('quantity', 21);
        $quantity = max(1, min(100, $quantity)); // Clamp between 1 and 100
        $columns = (int) $request->get('columns', 3);
        $columns = max(2, min(5, $columns)); // Clamp between 2 and 5

        $pdf = Pdf::loadView('products.barcode', compact('product', 'quantity', 'columns'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('barcode_' . $product->barcode . '.pdf');
    }

    /**
     * Show bulk barcode printing form.
     */
    public function bulkPrintBarcodeForm()
    {
        $products = Product::whereNotNull('barcode')->orderBy('name')->get();
        return view('products.bulk-print-barcode', compact('products'));
    }

    /**
     * Generate PDF with barcodes for multiple products.
     */
    public function bulkPrintBarcode(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1|max:100',
            'columns' => 'nullable|integer|min:2|max:5',
        ]);

        $columns = (int) $request->get('columns', 3);
        $columns = max(2, min(5, $columns)); // Clamp between 2 and 5

        $productsData = [];
        foreach ($request->products as $productInput) {
            $product = Product::find($productInput['id']);
            if ($product && $product->barcode) {
                $productsData[] = [
                    'product' => $product,
                    'quantity' => (int) $productInput['quantity'],
                ];
            }
        }

        if (empty($productsData)) {
            return redirect()->back()->with('error', 'No valid products selected for barcode printing.');
        }

        $pdf = Pdf::loadView('products.bulk-barcode', compact('productsData', 'columns'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('barcodes_' . date('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Sync product with sales channels (inventory management only)
     * - Link products to existing eBay listings by SKU (don't create new listings)
     * - Update inventory/quantity on eBay when product is updated
     * - Unlink products from channels when unchecked (don't end listings on eBay)
     */
    protected function syncSalesChannels(Product $product, array $selectedChannelIds, array $currentChannelIds, bool $skuChanged = false): void
    {
        $ebayController = app(EbayController::class);

        // Channels to add (newly selected - link to existing eBay listing)
        $channelsToAdd = array_diff($selectedChannelIds, $currentChannelIds);

        // Channels to remove (unchecked - just unlink, don't end listing)
        $channelsToRemove = array_diff($currentChannelIds, $selectedChannelIds);

        // Channels to update (still selected - sync inventory)
        $channelsToUpdate = array_intersect($selectedChannelIds, $currentChannelIds);

        // Process new channels - Find and link existing eBay listings by SKU
        foreach ($channelsToAdd as $channelId) {
            $channel = SalesChannel::find($channelId);
            if (!$channel || !$channel->isEbay()) {
                continue;
            }

            try {
                // Find existing eBay listing by SKU
                $existingListing = $ebayController->findEbayListingBySku($channel, $product->sku);

                if ($existingListing) {
                    // Found listing - link it and sync inventory
                    $listingUrl = "https://www.ebay.com/itm/{$existingListing['ItemID']}";

                    // Sync inventory to eBay
                    $result = $ebayController->syncInventory($channel, $existingListing['ItemID'], $product);

                    $product->sales_channels()->attach($channelId, [
                        'listing_url' => $listingUrl,
                        'external_listing_id' => $existingListing['ItemID'],
                        'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
                        'listing_error' => $result['success'] ? null : $this->extractListingError($result),
                        'listing_format' => $existingListing['ListingType'] ?? 'FixedPriceItem',
                        'last_synced_at' => now(),
                    ]);

                    Log::info('Product linked to eBay listing', [
                        'product_id' => $product->id,
                        'channel_id' => $channelId,
                        'ebay_item_id' => $existingListing['ItemID'],
                        'sku' => $product->sku,
                    ]);
                } else {
                    // No listing found on eBay - attach with "not found" status
                    $product->sales_channels()->attach($channelId, [
                        'listing_status' => 'not_found',
                        'listing_error' => "No eBay listing found with SKU: {$product->sku}",
                        'last_synced_at' => now(),
                    ]);

                    Log::warning('No eBay listing found for product', [
                        'product_id' => $product->id,
                        'channel_id' => $channelId,
                        'sku' => $product->sku,
                    ]);
                }

            } catch (\Exception $e) {
                $product->sales_channels()->attach($channelId, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to link product to sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process removed channels - Just unlink (don't end listing on eBay)
        foreach ($channelsToRemove as $channelId) {
            // Simply detach - the listing remains on eBay, we just stop managing it
            $product->sales_channels()->detach($channelId);

            Log::info('Product unlinked from sales channel', [
                'product_id' => $product->id,
                'channel_id' => $channelId,
            ]);
        }

        // Process existing channels - Sync inventory only
        foreach ($channelsToUpdate as $channelId) {
            $channel = SalesChannel::find($channelId);
            if (!$channel || !$channel->isEbay()) {
                continue;
            }

            try {
                $pivot = $product->sales_channels()->where('sales_channel_id', $channelId)->first()?->pivot;
                $externalId = $pivot?->external_listing_id;

                // Skip if no external ID (listing not found)
                if (!$externalId) {
                    // Try to find listing again by SKU
                    $existingListing = $ebayController->findEbayListingBySku($channel, $product->sku);
                    if ($existingListing) {
                        $externalId = $existingListing['ItemID'];
                        $listingUrl = "https://www.ebay.com/itm/{$externalId}";

                        // Update pivot with found listing
                        $product->sales_channels()->updateExistingPivot($channelId, [
                            'external_listing_id' => $externalId,
                            'listing_url' => $listingUrl,
                            'listing_format' => $existingListing['ListingType'] ?? 'FixedPriceItem',
                        ]);
                    } else {
                        continue; // Still no listing found
                    }
                }

                // Sync inventory to eBay
                $result = $ebayController->syncInventory($channel, $externalId, $product);

                $product->sales_channels()->updateExistingPivot($channelId, [
                    'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $result['success'] ? null : $this->extractListingError($result),
                    'last_synced_at' => now(),
                ]);

                Log::info('Product inventory synced on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'ebay_item_id' => $externalId,
                ]);

            } catch (\Exception $e) {
                $product->sales_channels()->updateExistingPivot($channelId, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to sync inventory on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Extract error message from eBay API result
     */
    private function extractListingError(array $result): ?string
    {
        // Check for 'message' key (from exception handling in EbayController)
        if (!empty($result['message'])) {
            return $result['message'];
        }

        // Check for 'errors' array (from eBay API response)
        if (!empty($result['errors']) && is_array($result['errors'])) {
            $errorMessages = [];
            foreach ($result['errors'] as $error) {
                if (isset($error['long_message'])) {
                    $errorMessages[] = $error['long_message'];
                } elseif (isset($error['short_message'])) {
                    $errorMessages[] = $error['short_message'];
                }
            }
            return !empty($errorMessages) ? implode('; ', $errorMessages) : null;
        }

        // Fallback to 'error' key
        return $result['error'] ?? null;
    }

    /**
     * Sync a product's inventory to all linked sales channels
     * This is a public static method that can be called from other controllers (e.g., PurchaseController)
     */
    public static function syncProductInventoryToChannels(Product $product): void
    {
        // Get all linked sales channels with external listing IDs
        $linkedChannels = $product->sales_channels()
            ->whereNotNull('sales_channel_product.external_listing_id')
            ->get();

        if ($linkedChannels->isEmpty()) {
            return;
        }

        $ebayController = app(EbayController::class);

        foreach ($linkedChannels as $channel) {
            if (!$channel->isEbay() || !$channel->hasValidToken()) {
                continue;
            }

            $externalId = $channel->pivot->external_listing_id;

            try {
                $result = $ebayController->syncInventory($channel, $externalId, $product);

                $product->sales_channels()->updateExistingPivot($channel->id, [
                    'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $result['success'] ? null : self::extractListingErrorStatic($result),
                    'last_synced_at' => now(),
                ]);

                Log::info('Product inventory synced to channel after stock change', [
                    'product_id' => $product->id,
                    'channel_id' => $channel->id,
                    'ebay_item_id' => $externalId,
                    'success' => $result['success'],
                ]);

            } catch (\Exception $e) {
                $product->sales_channels()->updateExistingPivot($channel->id, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to sync inventory after stock change', [
                    'product_id' => $product->id,
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Static version of extractListingError for use in static context
     */
    private static function extractListingErrorStatic(array $result): ?string
    {
        if (!empty($result['message'])) {
            return $result['message'];
        }

        if (!empty($result['errors']) && is_array($result['errors'])) {
            $errorMessages = [];
            foreach ($result['errors'] as $error) {
                if (isset($error['long_message'])) {
                    $errorMessages[] = $error['long_message'];
                } elseif (isset($error['short_message'])) {
                    $errorMessages[] = $error['short_message'];
                }
            }
            return !empty($errorMessages) ? implode('; ', $errorMessages) : null;
        }

        return $result['error'] ?? null;
    }

    /**
     * Update product stock quantities
     */
    public function updateStock(Request $request, string $id)
    {
        $request->validate([
            'stock_id' => 'required|array',
            'stock_id.*' => 'required|exists:product_stocks,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);

            foreach ($request->stock_id as $index => $stockId) {
                $stock = $product->product_stocks()->find($stockId);
                if ($stock) {
                    $stock->quantity = $request->quantity[$index];
                    $stock->save();
                }
            }

            // Sync inventory to all linked sales channels
            self::syncProductInventoryToChannels($product);

            DB::commit();

            return redirect()->back()->with('success', 'Stock quantities updated successfully and synced to sales channels.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock update failed', ['error' => $e->getMessage(), 'product_id' => $id]);
            return redirect()->back()->with('error', 'An error occurred while updating stock: ' . $e->getMessage());
        }
    }
}
