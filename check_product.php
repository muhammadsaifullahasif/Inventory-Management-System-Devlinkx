<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$items = \Illuminate\Support\Facades\DB::table('order_items')
    ->select('order_items.id as item_id', 'order_items.order_id', 'orders.order_status', 'order_items.quantity', 'order_items.inventory_updated')
    ->join('orders', 'order_items.order_id', '=', 'orders.id')
    ->where('order_items.product_id', 671)
    ->orderBy('order_items.id')
    ->get();

echo "Total order_items for product 671: " . $items->count() . "\n\n";

$totalQty = 0;
$deductedQty = 0;

foreach ($items as $item) {
    $totalQty += $item->quantity;
    if ($item->inventory_updated) {
        $deductedQty += $item->quantity;
    }

    echo sprintf(
        "Item %d | Order %d | %s | Qty: %d | Inv: %s\n",
        $item->item_id,
        $item->order_id,
        str_pad($item->order_status, 20),
        $item->quantity,
        $item->inventory_updated ? 'YES' : 'NO'
    );
}

echo "\nSummary:\n";
echo "Total qty in all orders: $totalQty\n";
echo "Total qty deducted (inventory_updated=1): $deductedQty\n";
