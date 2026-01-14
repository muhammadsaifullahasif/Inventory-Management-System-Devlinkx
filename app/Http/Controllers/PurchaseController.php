<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function index()
    {
        $purchases = Purchase::orderBy('created_at', 'desc')->paginate(25);
        return view('purchases.index', compact('purchases'));
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
                        
                        $existingItemIds[] = $newItem->id;
                    }
                }

                // Delete purchase items that were not removed
                $purchase->purchase_items()->whereNotIn('id', $existingItemIds)->delete();
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
            $purchase = Purchase::findOrFail($id);
            $purchase->delete();

            return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully.');
        } catch (\Exception $e) {
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
            }

            return redirect()->route('purchases.index')->with('success', 'Stock received successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while receiving stock: ' . $e->getMessage())->withInput();
        }
    }
}
