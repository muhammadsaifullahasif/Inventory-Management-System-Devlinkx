<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ProductController;
use App\Imports\PurchaseImport;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PurchaseController extends Controller
{
    protected $aliases = [
        'purchase_number' => ['purchase_number', 'po_number', 'po', 'purchase_no', 'purchaseno'],
        'supplier' => ['supplier', 'supplier_id', 'supplier_name', 'vendor', 'vendor_name'],
        'warehouse' => ['warehouse', 'warehouse_id', 'warehouse_name', 'wh', 'wh_name'],
        'rack' => ['rack', 'rack_id', 'rack_name'],
        'sku' => ['sku', 'product_sku', 'item_sku'],
        'quantity' => ['quantity', 'qty', 'amount'],
        'price' => ['price', 'unit_price', 'cost', 'unit_cost'],
        'note' => ['note', 'notes', 'product_note', 'item_note'],
        'purchase_note' => ['purchase_note', 'po_note', 'po_notes'],
    ];

    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view purchases'), ['only' => ['index', 'show']]);
        $this->middleware(PermissionMiddleware::using('add purchases'), ['only' => ['create', 'store', 'import_purchases', 'import_purchase_preview', 'import_purchases_store']]);
        $this->middleware(PermissionMiddleware::using('edit purchases'), ['only' => ['edit', 'update', 'receiveStock', 'processReceiveStock']]);
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
        $products = Product::withSum('product_stocks', 'quantity')->orderBy('name')->get();
        return view('purchases.new', compact('suppliers', 'warehouses', 'products'));
    }

    /**
     * Store a newly created resource in storage.
     * Note: Stock is NOT added here. Stock is added when items are received via receiveStock().
     */
    public function store(Request $request)
    {
        $request->validate([
            'purchase_number' => 'required|string|unique:purchases,purchase_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'duties_customs' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.rack' => 'required|exists:racks,id',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.note' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create the purchase with pending status
            $purchase = Purchase::create([
                'purchase_number' => $request->purchase_number,
                'supplier_id'     => $request->supplier_id,
                'warehouse_id'    => $request->warehouse_id,
                'purchase_note'   => $request->purchase_note ?? null,
                'duties_customs'  => $request->duties_customs ?? 0,
                'freight_charges' => $request->freight_charges ?? 0,
                'purchase_status' => 'pending',
            ]);

            // Create purchase items (no stock added yet - will be added on receive)
            foreach ($request->products as $productInput) {
                $product = Product::find($productInput['id']);

                $purchase->purchase_items()->create([
                    'product_id'        => $product->id,
                    'barcode'           => $product->barcode ?? '',
                    'sku'               => $product->sku ?? '',
                    'name'              => $product->name,
                    'quantity'          => (float) $productInput['quantity'],
                    'received_quantity' => 0,
                    'price'             => (float) $productInput['price'],
                    'note'              => $productInput['note'] ?? null,
                    'rack_id'           => $productInput['rack'],
                ]);
            }

            DB::commit();

            return redirect()->route('purchases.index')->with('success', 'Purchase created successfully. Use "Receive Stock" to add items to inventory.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase creation failed', ['error' => $e->getMessage()]);
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
        $products = Product::withSum('product_stocks', 'quantity')->orderBy('name')->get();
        return view('purchases.edit', compact('purchase', 'suppliers', 'warehouses', 'products'));
    }

    /**
     * Update the specified resource in storage.
     * Note: Items that have been received (received_quantity > 0) cannot be modified.
     * Stock adjustments only happen via receive/unreceive operations.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'purchase_number' => 'required|string|unique:purchases,purchase_number,' . $id,
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'duties_customs' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.rack' => 'required|exists:racks,id',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.note' => 'nullable|string',
            'products.*.purchase_item_id' => 'nullable|integer',
        ]);

        try {
            DB::beginTransaction();

            $purchase = Purchase::with('purchase_items')->findOrFail($id);

            // Check if any items have been received - prevent warehouse change if so
            $hasReceivedItems = $purchase->purchase_items->where('received_quantity', '>', 0)->count() > 0;

            if ($hasReceivedItems && $purchase->warehouse_id != $request->warehouse_id) {
                return redirect()->back()
                    ->with('error', 'Cannot change warehouse after items have been received.')
                    ->withInput();
            }

            // Update the purchase details
            $purchase->purchase_number = $request->purchase_number;
            $purchase->supplier_id = $request->supplier_id;
            $purchase->warehouse_id = $request->warehouse_id;
            $purchase->purchase_note = $request->purchase_note ?? null;
            $purchase->duties_customs = $request->duties_customs ?? 0;
            $purchase->freight_charges = $request->freight_charges ?? 0;
            $purchase->save();

            $existingItemIds = [];

            foreach ($request->products as $productInput) {
                $product = Product::find($productInput['id']);
                $itemId = $productInput['purchase_item_id'] ?? null;

                if ($itemId && $itemId != '') {
                    // Update existing purchase item
                    $purchaseItem = $purchase->purchase_items()->find($itemId);

                    if ($purchaseItem) {
                        $receivedQty = (float) $purchaseItem->received_quantity;
                        $newQuantity = (float) $productInput['quantity'];

                        // Cannot reduce quantity below what's already received
                        if ($newQuantity < $receivedQty) {
                            DB::rollBack();
                            return redirect()->back()
                                ->with('error', "Cannot reduce quantity for '{$product->name}' below received amount ({$receivedQty}).")
                                ->withInput();
                        }

                        // Cannot change product or rack if already received
                        if ($receivedQty > 0) {
                            if ($purchaseItem->product_id != $product->id) {
                                DB::rollBack();
                                return redirect()->back()
                                    ->with('error', "Cannot change product for items already received.")
                                    ->withInput();
                            }
                            if ($purchaseItem->rack_id != $productInput['rack']) {
                                DB::rollBack();
                                return redirect()->back()
                                    ->with('error', "Cannot change rack for '{$product->name}' after items received. Received: {$receivedQty}")
                                    ->withInput();
                            }
                        }

                        $purchaseItem->update([
                            'product_id' => $product->id,
                            'barcode'    => $product->barcode ?? '',
                            'sku'        => $product->sku ?? '',
                            'name'       => $product->name,
                            'quantity'   => $newQuantity,
                            'price'      => (float) $productInput['price'],
                            'note'       => $productInput['note'] ?? null,
                            'rack_id'    => $productInput['rack'],
                        ]);

                        $existingItemIds[] = $itemId;
                    }
                } else {
                    // Create new purchase item
                    $newItem = $purchase->purchase_items()->create([
                        'product_id'        => $product->id,
                        'barcode'           => $product->barcode ?? '',
                        'sku'               => $product->sku ?? '',
                        'name'              => $product->name,
                        'quantity'          => (float) $productInput['quantity'],
                        'received_quantity' => 0,
                        'price'             => (float) $productInput['price'],
                        'note'              => $productInput['note'] ?? null,
                        'rack_id'           => $productInput['rack'],
                    ]);

                    $existingItemIds[] = $newItem->id;
                }
            }

            // Handle removed purchase items - only allow if not received
            $removedItems = $purchase->purchase_items()->whereNotIn('id', $existingItemIds)->get();

            foreach ($removedItems as $removedItem) {
                if ((float) $removedItem->received_quantity > 0) {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', "Cannot remove '{$removedItem->name}' - {$removedItem->received_quantity} units already received.")
                        ->withInput();
                }
            }

            // Delete the removed purchase items (only unreceived items reach here)
            $purchase->purchase_items()->whereNotIn('id', $existingItemIds)->delete();

            // Update purchase status
            $this->updatePurchaseStatus($purchase);

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'An error occurred while updating the purchase: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     * Only subtracts RECEIVED quantities from stock (not ordered quantities).
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $purchase = Purchase::with(['purchase_items', 'supplier'])->findOrFail($id);
            $productsToSync = [];

            // Initialize inventory accounting service
            $inventoryAccountingService = new InventoryAccountingService();

            // Only decrease stock for RECEIVED quantities
            foreach ($purchase->purchase_items as $purchaseItem) {
                $receivedQty = (float) $purchaseItem->received_quantity;

                // Skip if nothing was received
                if ($receivedQty <= 0) {
                    continue;
                }

                $product = Product::find($purchaseItem->product_id);
                if ($product) {
                    // Decrease stock from the corresponding warehouse and rack
                    $stock = $product->product_stocks()
                        ->where('warehouse_id', $purchase->warehouse_id)
                        ->where('rack_id', $purchaseItem->rack_id)
                        ->first();

                    if ($stock) {
                        $stock->quantity = max(0, (float) $stock->quantity - $receivedQty);
                        if ($stock->quantity <= 0) {
                            $stock->delete();
                        } else {
                            $stock->save();
                        }
                    }

                    // Reverse the accounting journal entry for this receipt
                    $unitCost = (float) $purchaseItem->price;
                    $inventoryAccountingService->reversePurchaseReceipt(
                        $purchase,
                        $purchaseItem,
                        $receivedQty,
                        $unitCost
                    );

                    // Track for eBay sync
                    $productsToSync[$product->id] = $product;

                    Log::info('Stock decreased for deleted purchase', [
                        'purchase_id'      => $purchase->id,
                        'product_id'       => $product->id,
                        'product_sku'      => $product->sku,
                        'received_removed' => $receivedQty,
                        'warehouse_id'     => $purchase->warehouse_id,
                        'rack_id'          => $purchaseItem->rack_id,
                    ]);
                }
            }

            // Delete the purchase (this will cascade delete purchase_items)
            $purchase->delete();

            DB::commit();

            // Sync inventory to all linked sales channels for affected products
            foreach ($productsToSync as $productToSync) {
                ProductController::syncProductInventoryToChannels($productToSync);
            }

            return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully. Accounting entries reversed.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase deletion failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'An error occurred while deleting the purchase: ' . $e->getMessage());
        }
    }

    /**
     * Show the receive stock form for a purchase.
     */
    public function receiveStock(string $id)
    {
        $purchase = Purchase::with(['supplier', 'warehouse', 'purchase_items.product', 'purchase_items.rack'])
            ->findOrFail($id);

        // Get racks for the purchase's warehouse
        $racks = Rack::where('warehouse_id', $purchase->warehouse_id)
            ->where('active_status', '1')
            ->where('delete_status', '0')
            ->get();

        return view('purchases.receive', compact('purchase', 'racks'));
    }

    /**
     * Process the receive stock form.
     */
    public function processReceiveStock(Request $request, string $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_items,id',
            'items.*.receive_quantity' => 'nullable|numeric|min:0',
            'items.*.rack_id' => 'nullable|exists:racks,id',
        ]);

        try {
            DB::beginTransaction();

            $purchase = Purchase::with(['purchase_items', 'supplier'])->findOrFail($id);
            $productsToSync = [];
            $itemsReceived = 0;

            // Initialize inventory accounting service
            $inventoryAccountingService = new InventoryAccountingService();

            foreach ($request->items as $itemData) {
                $receiveQty = (float) $itemData['receive_quantity'];

                // Skip items with 0 receive quantity
                if ($receiveQty <= 0) {
                    continue;
                }

                $purchaseItem = $purchase->purchase_items->find($itemData['item_id']);
                if (!$purchaseItem) {
                    continue;
                }

                // Calculate how much can still be received
                $pendingQty = (float) $purchaseItem->quantity - (float) $purchaseItem->received_quantity;
                $actualReceiveQty = min($receiveQty, $pendingQty);

                if ($actualReceiveQty <= 0) {
                    continue;
                }

                // Use the specified rack or fall back to the original rack
                $rackId = $itemData['rack_id'] ?? $purchaseItem->rack_id;

                // Get purchase unit cost for this item
                $unitCost = (float) $purchaseItem->price;

                // Update purchase item received quantity
                $purchaseItem->received_quantity = (float) $purchaseItem->received_quantity + $actualReceiveQty;
                $purchaseItem->received_at = now();
                $purchaseItem->save();

                // Update product stock
                $product = Product::find($purchaseItem->product_id);
                if ($product) {
                    // Calculate new weighted average cost before updating stock
                    $newAvgCost = $inventoryAccountingService->calculateWeightedAverageCost(
                        $product->id,
                        $actualReceiveQty,
                        $unitCost
                    );

                    $existingStock = $product->product_stocks()
                        ->where('warehouse_id', $purchase->warehouse_id)
                        ->where('rack_id', $rackId)
                        ->first();

                    if ($existingStock) {
                        $existingStock->quantity = (float) $existingStock->quantity + $actualReceiveQty;
                        $existingStock->avg_cost = $newAvgCost;
                        $existingStock->save();
                    } else {
                        $product->product_stocks()->create([
                            'warehouse_id'  => $purchase->warehouse_id,
                            'rack_id'       => $rackId,
                            'quantity'      => $actualReceiveQty,
                            'avg_cost'      => $newAvgCost,
                            'active_status' => '1',
                            'delete_status' => '0',
                        ]);
                    }

                    // Update avg_cost on all ProductStock records for this product
                    $inventoryAccountingService->updateProductStockCost($product->id, $newAvgCost);

                    // Record journal entry for inventory receipt
                    // DEBIT: Inventory Asset, CREDIT: Accounts Payable
                    $inventoryAccountingService->recordPurchaseReceipt(
                        $purchase,
                        $purchaseItem,
                        $actualReceiveQty,
                        $unitCost
                    );

                    $productsToSync[$product->id] = $product;
                }

                $itemsReceived++;
            }

            // Update purchase status based on received quantities
            $this->updatePurchaseStatus($purchase);

            // Record duties and freight charges as journal entries (only once when purchase is fully received)
            $purchase->refresh();
            if ($purchase->purchase_status === 'received') {
                // Record duties & customs if any
                if ((float) $purchase->duties_customs > 0) {
                    $inventoryAccountingService->recordPurchaseCharges($purchase, 'duties', (float) $purchase->duties_customs);
                }

                // Record freight charges if any
                if ((float) $purchase->freight_charges > 0) {
                    $inventoryAccountingService->recordPurchaseCharges($purchase, 'freight', (float) $purchase->freight_charges);
                }
            }

            DB::commit();

            // Sync inventory to all linked sales channels
            foreach ($productsToSync as $productToSync) {
                ProductController::syncProductInventoryToChannels($productToSync);
            }

            if ($itemsReceived > 0) {
                return redirect()->route('purchases.show', $purchase->id)
                    ->with('success', "Stock received successfully. {$itemsReceived} item(s) updated. Accounting entries recorded.");
            } else {
                return redirect()->back()->with('warning', 'No items were received. Please enter quantities to receive.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Receive stock failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'An error occurred while receiving stock: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update purchase status based on received quantities.
     */
    protected function updatePurchaseStatus(Purchase $purchase): void
    {
        $purchase->refresh();

        $totalOrdered = $purchase->purchase_items->sum('quantity');
        $totalReceived = $purchase->purchase_items->sum('received_quantity');

        if ($totalReceived <= 0) {
            $purchase->purchase_status = 'pending';
        } elseif ($totalReceived >= $totalOrdered) {
            $purchase->purchase_status = 'received';
        } else {
            $purchase->purchase_status = 'partial';
        }

        $purchase->save();
    }

    public function import_purchases()
    {
        return view('purchases.import');
    }

    protected function normalizedHeader($header)
    {
        return strtolower(trim(str_replace([' ', '#'], ['_', ''], $header)));
    }

    public function import_purchase_preview(Request $request)
    {
        $request->validate([
            'upload' => 'required|file|mimes:csv,txt',
        ]);

        $data = Excel::toArray(new PurchaseImport, $request->file('upload'));

        $headers = $data[0][0] ?? [];
        $normalizedHeaders = [];

        foreach ($headers as $header) {
            $clean = $this->normalizedHeader($header);
            $mapped = $clean;
            foreach ($this->aliases as $dbField => $words) {
                foreach ($words as $word) {
                    if ($clean === $this->normalizedHeader($word)) {
                        $mapped = $dbField;
                        break 2;
                    }
                }
            }
            $normalizedHeaders[] = $mapped;
        }

        $rows = array_slice($data[0], 1);
        $importErrors = [];

        // Map rows to associative arrays
        $mapped = [];
        foreach ($rows as $rowIndex => $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            if (count($normalizedHeaders) === count($row)) {
                $mapped[] = array_combine($normalizedHeaders, $row);
            } else {
                $importErrors[] = "Row " . ($rowIndex + 2) . " has mismatched column count.";
            }
        }

        // Group rows by purchase_number
        $groupedPurchases = [];
        foreach ($mapped as $row) {
            $purchaseNumber = $row['purchase_number'] ?? '';
            if (empty($purchaseNumber)) {
                $importErrors[] = "A row is missing purchase_number.";
                continue;
            }

            if (!isset($groupedPurchases[$purchaseNumber])) {
                $groupedPurchases[$purchaseNumber] = [
                    'purchase_number' => $purchaseNumber,
                    'supplier_id' => $row['supplier'] ?? null,
                    'warehouse_id' => $row['warehouse'] ?? null,
                    'purchase_note' => $row['purchase_note'] ?? '',
                    'products' => [],
                ];
            }

            // Add product to this purchase
            $groupedPurchases[$purchaseNumber]['products'][] = [
                'sku' => $row['sku'] ?? '',
                'rack_id' => $row['rack'] ?? null,
                'quantity' => $row['quantity'] ?? 1,
                'price' => $row['price'] ?? 0,
                'note' => $row['note'] ?? '',
            ];
        }

        // Validate IDs and look up product info
        $purchases = [];
        foreach ($groupedPurchases as $purchaseData) {
            // Validate supplier ID
            $supplierId = $purchaseData['supplier_id'];
            $supplier = $supplierId ? Supplier::find($supplierId) : null;

            // Validate warehouse ID
            $warehouseId = $purchaseData['warehouse_id'];
            $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;

            $purchaseEntry = [
                'purchase_number' => $purchaseData['purchase_number'],
                'supplier_id' => $supplier->id ?? null,
                'supplier_name' => $supplier ? ($supplier->last_name ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name) : 'Unknown Supplier',
                'warehouse_id' => $warehouse->id ?? null,
                'warehouse_name' => $warehouse->name ?? 'Unknown Warehouse',
                'purchase_note' => $purchaseData['purchase_note'],
                'products' => [],
            ];

            if (empty($supplier)) {
                $importErrors[] = "Supplier ID not found for purchase {$purchaseData['purchase_number']}: {$supplierId}";
            }
            if (empty($warehouse)) {
                $importErrors[] = "Warehouse ID not found for purchase {$purchaseData['purchase_number']}: {$warehouseId}";
            }

            // Look up product IDs by SKU and validate rack IDs
            foreach ($purchaseData['products'] as $productData) {
                $sku = $productData['sku'];
                $product = Product::where('sku', $sku)->first();

                $rackId = $productData['rack_id'];
                $rack = $rackId ? Rack::find($rackId) : null;

                $purchaseEntry['products'][] = [
                    'sku' => $sku,
                    'product_id' => $product->id ?? null,
                    'product_name' => $product->name ?? 'Unknown Product',
                    'rack_id' => $rack->id ?? null,
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'note' => $productData['note'],
                ];

                if (empty($product)) {
                    $importErrors[] = "SKU not found in purchase {$purchaseData['purchase_number']}: {$sku}";
                }
                if ($rackId && empty($rack)) {
                    $importErrors[] = "Rack ID not found in purchase {$purchaseData['purchase_number']}: {$rackId}";
                }
            }

            $purchases[] = $purchaseEntry;
        }

        // Get all suppliers and warehouses for dropdowns
        $suppliers = Supplier::orderBy('first_name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        // Get racks grouped by warehouse_id
        $allRacks = Rack::orderBy('name')->get();
        $racks = [];
        foreach ($allRacks as $rack) {
            if (!isset($racks[$rack->warehouse_id])) {
                $racks[$rack->warehouse_id] = [];
            }
            $racks[$rack->warehouse_id][] = $rack;
        }

        // Store purchases in session for validation failure redirect
        session(['import_purchases' => $purchases]);

        return view('purchases.import-preview', compact('purchases', 'suppliers', 'warehouses', 'racks', 'importErrors'));
    }

    /**
     * Store imported purchases.
     * Imported purchases are marked as fully received and stock is added immediately.
     */
    public function import_purchases_store(Request $request)
    {
        try {
            DB::beginTransaction();

            $purchases = $request->input('purchases', []);

            if (empty($purchases)) {
                return redirect()->route('purchases.import')->with('error', 'No purchases to import.');
            }

            $productsToSync = [];

            // Initialize inventory accounting service
            $inventoryAccountingService = new InventoryAccountingService();

            foreach ($purchases as $purchase_row) {
                // Skip if no supplier or warehouse selected
                if (empty($purchase_row['supplier_id']) || empty($purchase_row['warehouse_id'])) {
                    continue;
                }

                // Create the purchase with 'received' status (imported = already received)
                $purchase = Purchase::create([
                    'purchase_number' => $purchase_row['purchase_number'],
                    'supplier_id'     => $purchase_row['supplier_id'],
                    'warehouse_id'    => $purchase_row['warehouse_id'],
                    'purchase_note'   => $purchase_row['purchase_note'] ?? null,
                    'duties_customs'  => $purchase_row['duties_customs'] ?? 0,
                    'freight_charges' => $purchase_row['freight_charges'] ?? 0,
                    'purchase_status' => 'received',
                ]);

                // Load supplier for accounting
                $purchase->load('supplier');

                // Create purchase items
                $products = $purchase_row['products'] ?? [];

                foreach ($products as $productInput) {
                    // Skip products without valid product_id
                    if (empty($productInput['product_id'])) {
                        continue;
                    }

                    $product = Product::find($productInput['product_id']);
                    if (!$product) {
                        continue;
                    }

                    $incomingQty   = (float) ($productInput['quantity'] ?? 1);
                    $purchasePrice = (float) ($productInput['price'] ?? 0);
                    $warehouseId   = $purchase_row['warehouse_id'];
                    $rackId        = $productInput['rack_id'] ?? null;

                    // Create purchase item - mark as fully received
                    $purchaseItem = $purchase->purchase_items()->create([
                        'product_id'        => $product->id,
                        'barcode'           => $product->barcode ?? '',
                        'sku'               => $product->sku ?? '',
                        'name'              => $product->name,
                        'quantity'          => $incomingQty,
                        'received_quantity' => $incomingQty, // Fully received
                        'received_at'       => now(),
                        'price'             => $purchasePrice,
                        'note'              => $productInput['note'] ?? null,
                        'rack_id'           => $rackId,
                    ]);

                    // Calculate new weighted average cost
                    $newAvgCost = $inventoryAccountingService->calculateWeightedAverageCost(
                        $product->id,
                        $incomingQty,
                        $purchasePrice
                    );

                    // Add to stock
                    $stockQuery = $product->product_stocks()
                        ->where('warehouse_id', $warehouseId);

                    if ($rackId) {
                        $stockQuery->where('rack_id', $rackId);
                    } else {
                        $stockQuery->whereNull('rack_id');
                    }

                    $existingStock = $stockQuery->first();

                    if ($existingStock) {
                        $existingStock->quantity = (float) $existingStock->quantity + $incomingQty;
                        $existingStock->avg_cost = $newAvgCost;
                        $existingStock->save();
                    } else {
                        $product->product_stocks()->create([
                            'warehouse_id'  => $warehouseId,
                            'rack_id'       => $rackId,
                            'quantity'      => $incomingQty,
                            'avg_cost'      => $newAvgCost,
                            'active_status' => '1',
                            'delete_status' => '0',
                        ]);
                    }

                    // Update avg_cost on all ProductStock records for this product
                    $inventoryAccountingService->updateProductStockCost($product->id, $newAvgCost);

                    // Record journal entry for inventory receipt
                    $inventoryAccountingService->recordPurchaseReceipt(
                        $purchase,
                        $purchaseItem,
                        $incomingQty,
                        $purchasePrice
                    );

                    $productsToSync[$product->id] = $product;
                }

                // Record duties & customs if any
                if ((float) ($purchase_row['duties_customs'] ?? 0) > 0) {
                    $inventoryAccountingService->recordPurchaseCharges($purchase, 'duties', (float) $purchase_row['duties_customs']);
                }

                // Record freight charges if any
                if ((float) ($purchase_row['freight_charges'] ?? 0) > 0) {
                    $inventoryAccountingService->recordPurchaseCharges($purchase, 'freight', (float) $purchase_row['freight_charges']);
                }
            }

            DB::commit();

            // Sync inventory to all linked sales channels
            foreach ($productsToSync as $productToSync) {
                Log::info('Triggering inventory sync after purchase import', [
                    'product_id'  => $productToSync->id,
                    'product_sku' => $productToSync->sku,
                ]);
                ProductController::syncProductInventoryToChannels($productToSync);
            }

            return redirect()->route('purchases.index')->with('success', 'Purchase(s) imported and received successfully. Accounting entries recorded.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('purchases.import')->with('error', 'An error occurred while importing the purchase(s): ' . $e->getMessage());
        }
    }

    public function downloadImportTemplate()
    {
        $filePath = public_path('Purchases.csv');

        if (!file_exists($filePath)) {
            abort(404, 'Template file not found.');
        }

        return response()->download($filePath, 'Purchases.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
