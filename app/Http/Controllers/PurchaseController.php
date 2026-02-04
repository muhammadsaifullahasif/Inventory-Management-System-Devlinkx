<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view purchases'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add purchases'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit purchases'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete purchases'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'warehouse', 'purchase_items']);

        // Filter by search term (purchase number)
        if ($request->filled('search')) {
            $query->where('purchase_number', 'like', "%{$request->search}%");
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by status
        if ($request->filled('purchase_status')) {
            $query->where('purchase_status', $request->purchase_status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $purchases = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Get filter options
        $suppliers = Supplier::orderBy('first_name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('purchases.index', compact('purchases', 'suppliers', 'warehouses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $suppliers = Supplier::all();
        $warehouses = Warehouse::all();
        return view('purchases.new', compact('suppliers', 'warehouses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'purchase_number' => 'required|string|unique:purchases,purchase_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'rack' => 'required|array',
            'rack.*' => 'required|exists:racks,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|numeric|min:1',
            'price' => 'required|array|min:1',
            'price.*' => 'required|numeric|min:0',
            'note' => 'nullable|array',
            'note.*' => 'nullable|string',
        ]);

        try {
            // Create the purchase
            $purchase = Purchase::create([
                'purchase_number' => $request->purchase_number,
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'purchase_note' => $request->purchase_note ?? null,
                'purchase_status' => 'received',
            ]);

            // Create purchase items
            foreach ($request->product_id as $index => $product_id) {
                // Get product details
                $product = Product::find($product_id);

                $purchase->purchase_items()->create([
                    'product_id' => $product_id,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $request->quantity[$index],
                    'price' => $request->price[$index],
                    'note' => $request->note[$index] ?? null,
                    'rack_id' => $request->rack[$index],
                ]);

                // Add stock using update with DB::raw or create
                $product->product_stocks()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->where('rack_id', $request->rack[$index])
                    ->update(['quantity' => DB::raw('quantity + ' . $request->quantity[$index])])
                    ?: $product->product_stocks()->create([
                        'product_id' => $product->id,
                        'warehouse_id' => $request->warehouse_id,
                        'rack_id' => $request->rack[$index],
                        'quantity' => $request->quantity[$index]
                    ]);

                // Sync inventory to all linked sales channels
                Log::info('Triggering inventory sync after purchase creation', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'quantity_added' => $request->quantity[$index],
                ]);
                ProductController::syncProductInventoryToChannels($product);
            }

            return redirect()->route('purchases.index')->with('success', 'Purchase created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the purchase: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $purchase = Purchase::findOrFail($id);
        return view('purchases.view', compact('purchase'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $purchase = Purchase::findOrFail($id);
        $suppliers = Supplier::all();
        $warehouses = Warehouse::all();
        return view('purchases.edit', compact('purchase', 'suppliers', 'warehouses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'purchase_number' => 'required|string|unique:purchases,purchase_number,' . $id,
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'rack' => 'required|array',
            'rack.*' => 'required|exists:racks,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|numeric|min:1',
            'price' => 'required|array|min:1',
            'price.*' => 'required|numeric|min:0',
            'note' => 'nullable|array',
            'note.*' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Update the purchase
            $purchase = Purchase::findOrFail($id);

            // Store old warehouse ID before updating
            $oldPurchaseWarehouseId = $purchase->warehouse_id;

            $purchase->purchase_number = $request->purchase_number;
            $purchase->supplier_id = $request->supplier_id;
            $purchase->warehouse_id = $request->warehouse_id;
            $purchase->purchase_note = $request->purchase_note ?? null;
            $purchase->save();

            // Handle purchase items update
            if ($request->has('product_id') && is_array($request->product_id)) {
                $itemIds = $request->purchase_item_id ?? [];
                $existingItemIds = [];
                $productsToSync = []; // Track products that need inventory sync

                foreach ($request->product_id as $index => $product_id) {
                    $product = Product::find($product_id);
                    $itemId = $itemIds[$index] ?? null;

                    if ($itemId && $itemId != '') {
                        // Update existing purchase item
                        $purchaseItem = $purchase->purchase_items()->find($itemId);
                        if ($purchaseItem) {
                            // Store old values BEFORE updating
                            $oldQuantity = $purchaseItem->quantity;
                            $oldRackId = $purchaseItem->rack_id;
                            $oldWarehouseId = $oldPurchaseWarehouseId;

                            $newQuantity = $request->quantity[$index];
                            $newRackId = $request->rack[$index];
                            $newWarehouseId = $request->warehouse_id;

                            // Update the purchase item
                            $purchaseItem->update([
                                'product_id' => $product_id,
                                'barcode' => $product->barcode,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'quantity' => $newQuantity,
                                'price' => $request->price[$index],
                                'note' => $request->note[$index] ?? null,
                                'rack_id' => $newRackId
                            ]);

                            // Step 1: Subtract old quantity from old location
                            $oldStock = $product->product_stocks()
                                ->where('warehouse_id', $oldWarehouseId)
                                ->where('rack_id', $oldRackId)
                                ->first();

                            if ($oldStock) {
                                $oldStock->quantity -= $oldQuantity;
                                if ($oldStock->quantity <= 0) {
                                    $oldStock->delete();
                                } else {
                                    $oldStock->save();
                                }
                            }

                            // Step 2: Add new quantity to new location using upsert
                            $product->product_stocks()
                                ->where('product_id', $product->id)
                                ->where('warehouse_id', $newWarehouseId)
                                ->where('rack_id', $newRackId)
                                ->update(['quantity' => DB::raw("quantity + $newQuantity")])
                                ?: $product->product_stocks()->create([
                                    'product_id' => $product->id,
                                    'warehouse_id' => $newWarehouseId,
                                    'rack_id' => $newRackId,
                                    'quantity' => $newQuantity
                                ]);

                            // Track product for inventory sync
                            $productsToSync[$product->id] = $product;

                            $existingItemIds[] = $itemId;
                        }
                    } else {
                        // Create new purchase item
                        $newItem = $purchase->purchase_items()->create([
                            'product_id' => $product_id,
                            'barcode' => $product->barcode,
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'quantity' => $request->quantity[$index],
                            'price' => $request->price[$index],
                            'note' => $request->note[$index] ?? null,
                            'rack_id' => $request->rack[$index]
                        ]);

                        // Add stock using update with DB::raw or create
                        $product->product_stocks()
                            ->where('product_id', $product->id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->where('rack_id', $request->rack[$index])
                            ->update(['quantity' => DB::raw('quantity + ' . $request->quantity[$index])])
                            ?: $product->product_stocks()->create([
                                'product_id' => $product->id,
                                'warehouse_id' => $request->warehouse_id,
                                'rack_id' => $request->rack[$index],
                                'quantity' => $request->quantity[$index]
                            ]);

                        // Track product for inventory sync
                        $productsToSync[$product->id] = $product;

                        $existingItemIds[] = $newItem->id;
                    }
                }

                // Handle removed purchase items - decrease stock before deleting
                $removedItems = $purchase->purchase_items()->whereNotIn('id', $existingItemIds)->get();
                foreach ($removedItems as $removedItem) {
                    $removedProduct = Product::find($removedItem->product_id);
                    if ($removedProduct) {
                        // Decrease stock from the old location
                        $stock = $removedProduct->product_stocks()
                            ->where('warehouse_id', $oldPurchaseWarehouseId)
                            ->where('rack_id', $removedItem->rack_id)
                            ->first();

                        if ($stock) {
                            $stock->quantity -= $removedItem->quantity;
                            if ($stock->quantity <= 0) {
                                $stock->delete();
                            } else {
                                $stock->save();
                            }
                        }

                        // Track for eBay sync
                        $productsToSync[$removedProduct->id] = $removedProduct;

                        Log::info('Stock decreased for removed purchase item', [
                            'product_id' => $removedProduct->id,
                            'product_sku' => $removedProduct->sku,
                            'quantity_removed' => $removedItem->quantity,
                            'warehouse_id' => $oldPurchaseWarehouseId,
                            'rack_id' => $removedItem->rack_id,
                        ]);
                    }
                }

                // Delete the removed purchase items
                $purchase->purchase_items()->whereNotIn('id', $existingItemIds)->delete();

                // Sync inventory to all linked sales channels for affected products
                foreach ($productsToSync as $productToSync) {
                    ProductController::syncProductInventoryToChannels($productToSync);
                }
            }

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'An error occurred while updating the purchase: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $purchase = Purchase::with('purchase_items')->findOrFail($id);
            $productsToSync = [];

            // Decrease stock for all purchase items before deleting
            foreach ($purchase->purchase_items as $purchaseItem) {
                $product = Product::find($purchaseItem->product_id);
                if ($product) {
                    // Decrease stock from the corresponding warehouse and rack
                    $stock = $product->product_stocks()
                        ->where('warehouse_id', $purchase->warehouse_id)
                        ->where('rack_id', $purchaseItem->rack_id)
                        ->first();

                    if ($stock) {
                        $stock->quantity -= $purchaseItem->quantity;
                        if ($stock->quantity <= 0) {
                            $stock->delete();
                        } else {
                            $stock->save();
                        }
                    }

                    // Track for eBay sync
                    $productsToSync[$product->id] = $product;

                    Log::info('Stock decreased for deleted purchase', [
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'quantity_removed' => $purchaseItem->quantity,
                        'warehouse_id' => $purchase->warehouse_id,
                        'rack_id' => $purchaseItem->rack_id,
                    ]);
                }
            }

            // Delete the purchase (this will cascade delete purchase_items if configured)
            $purchase->delete();

            DB::commit();

            // Sync inventory to all linked sales channels for affected products
            foreach ($productsToSync as $productToSync) {
                ProductController::syncProductInventoryToChannels($productToSync);
            }

            return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully and stock updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'An error occurred while deleting the purchase: ' . $e->getMessage());
        }
    }

    public function purchase_receive_stock(Request $request, string $id)
    {
        $request->validate([
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|numeric|min:1',
            'rack_id' => 'required|array',
            'rack_id.*' => 'required|exists:racks,id',
        ]);

        try {
            $purchase = Purchase::findOrFail($id);

            $productsToSync = [];

            foreach ($request->product_id as $index => $product_id) {
                $product = Product::find($product_id);
                $rack_id = $request->rack_id[$index];

                $product->product_stocks()->upsert(
                    [
                        ['product_id' => $product->id,'warehouse_id' => $purchase->warehouse_id, 'rack_id' => $rack_id, 'quantity' => $request->quantity[$index]],
                    ],
                    ['product_id', 'warehouse_id', 'rack_id'],
                    ['quantity' => DB::raw("product_stocks.quantity + VALUES(quantity)")]
                );

                $productsToSync[$product->id] = $product;
            }

            // Sync inventory to all linked sales channels
            foreach ($productsToSync as $productToSync) {
                ProductController::syncProductInventoryToChannels($productToSync);
            }

            return redirect()->route('purchases.index')->with('success', 'Stock received successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while receiving stock: ' . $e->getMessage())->withInput();
        }
    }
}
