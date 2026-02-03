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
        $product = Product::with('sales_channels')->findOrFail($id);
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
     * Sync product with sales channels
     * - Create listings for newly selected channels
     * - Update listings for existing channels
     * - End/Draft listings for removed channels
     */
    protected function syncSalesChannels(Product $product, array $selectedChannelIds, array $currentChannelIds, bool $skuChanged = false): void
    {
        $ebayController = app(EbayController::class);

        // Channels to add (newly selected)
        $channelsToAdd = array_diff($selectedChannelIds, $currentChannelIds);

        // Channels to remove (unchecked)
        $channelsToRemove = array_diff($currentChannelIds, $selectedChannelIds);

        // Channels to update (still selected)
        $channelsToUpdate = array_intersect($selectedChannelIds, $currentChannelIds);

        // Process new channels - Create listings
        foreach ($channelsToAdd as $channelId) {
            $channel = SalesChannel::find($channelId);
            if (!$channel || !$channel->isEbay()) {
                continue;
            }

            try {
                // Check if product already exists on eBay (by SKU)
                $existingListing = $ebayController->findEbayListingBySku($channel, $product->sku);

                if ($existingListing) {
                    // Product exists, revise and activate it
                    $result = $ebayController->reviseEbayItem($channel, $product, $existingListing['ItemID']);
                    $listingStatus = $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR;
                    $listingUrl = $result['listing_url'] ?? null;
                    $externalId = $result['item_id'] ?? $existingListing['ItemID'];
                    $listingError = $result['error'] ?? null;
                } else {
                    // Create new listing
                    $result = $ebayController->createEbayListing($channel, $product);
                    $listingStatus = $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR;
                    $listingUrl = $result['listing_url'] ?? null;
                    $externalId = $result['item_id'] ?? null;
                    $listingError = $result['error'] ?? null;
                }

                // Attach to pivot table
                $product->sales_channels()->attach($channelId, [
                    'listing_url' => $listingUrl,
                    'external_listing_id' => $externalId,
                    'listing_status' => $listingStatus,
                    'listing_error' => $listingError,
                    'listing_format' => 'FixedPriceItem',
                    'last_synced_at' => now(),
                ]);

                Log::info('Product listing created on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'status' => $listingStatus,
                ]);

            } catch (\Exception $e) {
                // Still attach but mark as error
                $product->sales_channels()->attach($channelId, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to create listing on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process removed channels - End/Draft listings
        foreach ($channelsToRemove as $channelId) {
            $channel = SalesChannel::find($channelId);
            if (!$channel || !$channel->isEbay()) {
                $product->sales_channels()->detach($channelId);
                continue;
            }

            try {
                // Get the existing listing
                $pivot = $product->sales_channels()->where('sales_channel_id', $channelId)->first()?->pivot;
                $externalId = $pivot?->external_listing_id;

                if ($externalId) {
                    // End the listing on eBay
                    $result = $ebayController->endEbayItem($channel, $externalId, 'NotAvailable');

                    // Update pivot with ended status instead of detaching
                    $product->sales_channels()->updateExistingPivot($channelId, [
                        'listing_status' => SalesChannelProduct::STATUS_ENDED,
                        'listing_error' => $result['success'] ? null : ($result['error'] ?? 'Failed to end listing'),
                        'last_synced_at' => now(),
                    ]);

                    Log::info('Product listing ended on sales channel', [
                        'product_id' => $product->id,
                        'channel_id' => $channelId,
                        'external_id' => $externalId,
                    ]);
                } else {
                    // No external ID, just detach
                    $product->sales_channels()->detach($channelId);
                }

            } catch (\Exception $e) {
                // Update with error status
                $product->sales_channels()->updateExistingPivot($channelId, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to end listing on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process existing channels - Update listings
        foreach ($channelsToUpdate as $channelId) {
            $channel = SalesChannel::find($channelId);
            if (!$channel || !$channel->isEbay()) {
                continue;
            }

            try {
                $pivot = $product->sales_channels()->where('sales_channel_id', $channelId)->first()?->pivot;
                $externalId = $pivot?->external_listing_id;
                $currentStatus = $pivot?->listing_status;

                // Skip if no external ID and status is not pending
                if (!$externalId && $currentStatus !== SalesChannelProduct::STATUS_PENDING) {
                    continue;
                }

                if ($externalId) {
                    // Revise existing listing
                    $result = $ebayController->reviseEbayItem($channel, $product, $externalId);

                    $product->sales_channels()->updateExistingPivot($channelId, [
                        'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
                        'listing_error' => $result['error'] ?? null,
                        'last_synced_at' => now(),
                    ]);
                } else {
                    // No existing ID, create new listing
                    $result = $ebayController->createEbayListing($channel, $product);

                    $product->sales_channels()->updateExistingPivot($channelId, [
                        'listing_url' => $result['listing_url'] ?? null,
                        'external_listing_id' => $result['item_id'] ?? null,
                        'listing_status' => $result['success'] ? SalesChannelProduct::STATUS_ACTIVE : SalesChannelProduct::STATUS_ERROR,
                        'listing_error' => $result['error'] ?? null,
                        'listing_format' => 'FixedPriceItem',
                        'last_synced_at' => now(),
                    ]);
                }

                Log::info('Product listing updated on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                ]);

            } catch (\Exception $e) {
                $product->sales_channels()->updateExistingPivot($channelId, [
                    'listing_status' => SalesChannelProduct::STATUS_ERROR,
                    'listing_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]);

                Log::error('Failed to update listing on sales channel', [
                    'product_id' => $product->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
