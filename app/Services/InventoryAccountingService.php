<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryAccountingService
{
    // Default account codes
    const INVENTORY_ACCOUNT_CODE = '1201';      // Stock in Hand
    const COGS_ACCOUNT_CODE = '5001';           // Inventory Cost (Cost of Sales)
    const TRADE_PAYABLES_CODE = '2001';         // Trade Payables
    const PRODUCT_SALES_CODE = '4001';          // Product Sales
    const FREIGHT_CHARGES_CODE = '5002';        // Freight Charges (under Cost of Sales)
    const DUTIES_CUSTOMS_CODE = '5003';         // Duties & Customs (under Cost of Sales)

    /**
     * Get the inventory asset account
     */
    public function getInventoryAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::INVENTORY_ACCOUNT_CODE)->first();
    }

    /**
     * Get the COGS account
     */
    public function getCOGSAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::COGS_ACCOUNT_CODE)->first();
    }

    /**
     * Get the trade payables account
     */
    public function getPayablesAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::TRADE_PAYABLES_CODE)->first();
    }

    /**
     * Get the product sales account
     */
    public function getSalesAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::PRODUCT_SALES_CODE)->first();
    }

    /**
     * Get the duties & customs expense account
     */
    public function getDutiesAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::DUTIES_CUSTOMS_CODE)->first();
    }

    /**
     * Get the freight charges expense account
     */
    public function getFreightAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', self::FREIGHT_CHARGES_CODE)->first();
    }

    /**
     * Record journal entry for purchase duties and freight charges
     *
     * DEBIT: Duties & Customs Expense (6005) OR Freight Expense (6006)
     * CREDIT: Accounts Payable (2001) - increases liability
     *
     * @param Purchase $purchase The purchase order
     * @param string $type 'duties' or 'freight'
     * @param float $amount The amount
     * @return JournalEntry|null
     */
    public function recordPurchaseCharges(Purchase $purchase, string $type, float $amount): ?JournalEntry
    {
        if ($amount <= 0) {
            return null;
        }

        $payablesAccount = $this->getPayablesAccount();
        $expenseAccount = $type === 'duties' ? $this->getDutiesAccount() : $this->getFreightAccount();
        $chargeType = $type === 'duties' ? 'Duties & Customs' : 'Freight Charges';

        if (!$expenseAccount || !$payablesAccount) {
            Log::warning("Inventory accounting: Required accounts not found for {$type}", [
                'expense_account' => $expenseAccount ? 'found' : 'missing',
                'payables_account' => $payablesAccount ? 'found' : 'missing',
            ]);
            return null;
        }

        // Use supplier's specific payable account if set
        $supplierPayableId = $purchase->supplier->payable_account_id ?? $payablesAccount->id;

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => now(),
            'reference_type' => 'purchase_charges',
            'reference_id' => $purchase->id,
            'narration' => "{$chargeType} for PO #{$purchase->purchase_number}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // DEBIT: Expense Account (increases expense)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $expenseAccount->id,
            'description' => "{$chargeType} for purchase {$purchase->purchase_number}",
            'debit' => $amount,
            'credit' => 0,
        ]);

        // CREDIT: Accounts Payable (increases liability)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierPayableId,
            'description' => "{$chargeType} payable",
            'debit' => 0,
            'credit' => $amount,
        ]);

        Log::info("Inventory accounting: {$chargeType} recorded", [
            'journal_entry_id' => $journalEntry->id,
            'purchase_id' => $purchase->id,
            'amount' => $amount,
        ]);

        return $journalEntry;
    }

    /**
     * Record journal entry when stock is received from a purchase
     *
     * DEBIT: Inventory Asset (1201) - increases inventory value
     * CREDIT: Accounts Payable (2001) - increases liability to supplier
     *
     * @param Purchase $purchase The purchase order
     * @param PurchaseItem $purchaseItem The specific item being received
     * @param float $receivedQty The quantity being received
     * @param float $unitCost The cost per unit
     * @return JournalEntry|null
     */
    public function recordPurchaseReceipt(Purchase $purchase, PurchaseItem $purchaseItem, float $receivedQty, float $unitCost): ?JournalEntry
    {
        $inventoryAccount = $this->getInventoryAccount();
        $payablesAccount = $this->getPayablesAccount();

        if (!$inventoryAccount || !$payablesAccount) {
            Log::warning('Inventory accounting: Required accounts not found', [
                'inventory_account' => $inventoryAccount ? 'found' : 'missing',
                'payables_account' => $payablesAccount ? 'found' : 'missing',
            ]);
            return null;
        }

        $totalCost = round($receivedQty * $unitCost, 2);

        if ($totalCost <= 0) {
            return null; // Don't create journal entry for zero value
        }

        // Use supplier's specific payable account if set
        $supplierPayableId = $purchase->supplier->payable_account_id ?? $payablesAccount->id;

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => now(),
            'reference_type' => 'purchase_receipt',
            'reference_id' => $purchase->id,
            'narration' => "Stock receipt: {$purchaseItem->name} (PO #{$purchase->purchase_number}) - Qty: {$receivedQty} @ {$unitCost}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // DEBIT: Inventory Asset (increases asset)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $inventoryAccount->id,
            'description' => "Stock received: {$purchaseItem->name} ({$receivedQty} units)",
            'debit' => $totalCost,
            'credit' => 0,
        ]);

        // CREDIT: Accounts Payable (increases liability)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierPayableId,
            'description' => "Payable for stock: {$purchaseItem->name}",
            'debit' => 0,
            'credit' => $totalCost,
        ]);

        Log::info('Inventory accounting: Purchase receipt recorded', [
            'journal_entry_id' => $journalEntry->id,
            'purchase_id' => $purchase->id,
            'product_name' => $purchaseItem->name,
            'quantity' => $receivedQty,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
        ]);

        return $journalEntry;
    }

    /**
     * Record COGS journal entry when an order is fulfilled (shipped)
     *
     * DEBIT: Cost of Goods Sold (5001) - increases expense
     * CREDIT: Inventory Asset (1201) - decreases inventory value
     *
     * @param Order $order The order being fulfilled
     * @param OrderItem $orderItem The specific item
     * @param float $avgCost The average cost per unit at time of sale
     * @return JournalEntry|null
     */
    public function recordCOGS(Order $order, OrderItem $orderItem, float $avgCost): ?JournalEntry
    {
        $inventoryAccount = $this->getInventoryAccount();
        $cogsAccount = $this->getCOGSAccount();

        if (!$inventoryAccount || !$cogsAccount) {
            Log::warning('Inventory accounting: Required accounts not found for COGS', [
                'inventory_account' => $inventoryAccount ? 'found' : 'missing',
                'cogs_account' => $cogsAccount ? 'found' : 'missing',
            ]);
            return null;
        }

        $totalCOGS = round($orderItem->quantity * $avgCost, 2);

        if ($totalCOGS <= 0) {
            return null; // Don't create journal entry for zero value
        }

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => now(),
            'reference_type' => 'order_fulfillment',
            'reference_id' => $order->id,
            'narration' => "COGS: {$orderItem->title} (Order #{$order->order_number}) - Qty: {$orderItem->quantity} @ {$avgCost}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // DEBIT: Cost of Goods Sold (increases expense)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cogsAccount->id,
            'description' => "Cost of goods sold: {$orderItem->title} ({$orderItem->quantity} units)",
            'debit' => $totalCOGS,
            'credit' => 0,
        ]);

        // CREDIT: Inventory Asset (decreases asset)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $inventoryAccount->id,
            'description' => "Inventory reduction: {$orderItem->title}",
            'debit' => 0,
            'credit' => $totalCOGS,
        ]);

        Log::info('Inventory accounting: COGS recorded', [
            'journal_entry_id' => $journalEntry->id,
            'order_id' => $order->id,
            'product_title' => $orderItem->title,
            'quantity' => $orderItem->quantity,
            'avg_cost' => $avgCost,
            'total_cogs' => $totalCOGS,
        ]);

        return $journalEntry;
    }

    /**
     * Reverse COGS when an order is cancelled/refunded (inventory restored)
     *
     * DEBIT: Inventory Asset (1201) - increases inventory value
     * CREDIT: Cost of Goods Sold (5001) - decreases expense
     *
     * @param Order $order The cancelled order
     * @param OrderItem $orderItem The specific item
     * @param float $avgCost The average cost per unit
     * @return JournalEntry|null
     */
    public function reverseCOGS(Order $order, OrderItem $orderItem, float $avgCost): ?JournalEntry
    {
        $inventoryAccount = $this->getInventoryAccount();
        $cogsAccount = $this->getCOGSAccount();

        if (!$inventoryAccount || !$cogsAccount) {
            return null;
        }

        $totalCOGS = round($orderItem->quantity * $avgCost, 2);

        if ($totalCOGS <= 0) {
            return null;
        }

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => now(),
            'reference_type' => 'order_cancellation',
            'reference_id' => $order->id,
            'narration' => "COGS Reversal: {$orderItem->title} (Order #{$order->order_number}) - Qty: {$orderItem->quantity}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // DEBIT: Inventory Asset (increases asset - stock returned)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $inventoryAccount->id,
            'description' => "Inventory restored: {$orderItem->title} ({$orderItem->quantity} units)",
            'debit' => $totalCOGS,
            'credit' => 0,
        ]);

        // CREDIT: Cost of Goods Sold (decreases expense)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cogsAccount->id,
            'description' => "COGS reversal: {$orderItem->title}",
            'debit' => 0,
            'credit' => $totalCOGS,
        ]);

        Log::info('Inventory accounting: COGS reversed', [
            'journal_entry_id' => $journalEntry->id,
            'order_id' => $order->id,
            'product_title' => $orderItem->title,
            'quantity' => $orderItem->quantity,
            'total_cogs' => $totalCOGS,
        ]);

        return $journalEntry;
    }

    /**
     * Reverse purchase receipt (when purchase is deleted after receiving)
     *
     * DEBIT: Accounts Payable (2001) - decreases liability
     * CREDIT: Inventory Asset (1201) - decreases inventory value
     *
     * @param Purchase $purchase The purchase being deleted
     * @param PurchaseItem $purchaseItem The specific item
     * @param float $receivedQty The quantity to reverse
     * @param float $unitCost The cost per unit
     * @return JournalEntry|null
     */
    public function reversePurchaseReceipt(Purchase $purchase, PurchaseItem $purchaseItem, float $receivedQty, float $unitCost): ?JournalEntry
    {
        $inventoryAccount = $this->getInventoryAccount();
        $payablesAccount = $this->getPayablesAccount();

        if (!$inventoryAccount || !$payablesAccount) {
            return null;
        }

        $totalCost = round($receivedQty * $unitCost, 2);

        if ($totalCost <= 0) {
            return null;
        }

        $supplierPayableId = $purchase->supplier->payable_account_id ?? $payablesAccount->id;

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => now(),
            'reference_type' => 'purchase_reversal',
            'reference_id' => $purchase->id,
            'narration' => "Stock receipt reversal: {$purchaseItem->name} (PO #{$purchase->purchase_number}) - Qty: {$receivedQty}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // DEBIT: Accounts Payable (decreases liability)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierPayableId,
            'description' => "Payable reversal for stock: {$purchaseItem->name}",
            'debit' => $totalCost,
            'credit' => 0,
        ]);

        // CREDIT: Inventory Asset (decreases asset)
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $inventoryAccount->id,
            'description' => "Stock receipt reversal: {$purchaseItem->name} ({$receivedQty} units)",
            'debit' => 0,
            'credit' => $totalCost,
        ]);

        Log::info('Inventory accounting: Purchase receipt reversed', [
            'journal_entry_id' => $journalEntry->id,
            'purchase_id' => $purchase->id,
            'product_name' => $purchaseItem->name,
            'quantity' => $receivedQty,
            'total_cost' => $totalCost,
        ]);

        return $journalEntry;
    }

    /**
     * Calculate weighted average cost for a product
     * New Avg Cost = (Current Stock Value + New Stock Value) / (Current Qty + New Qty)
     *
     * @param int $productId The product ID
     * @param float $newQty New quantity being added
     * @param float $newUnitCost Cost per unit for new stock
     * @return float The new weighted average cost
     */
    public function calculateWeightedAverageCost(int $productId, float $newQty, float $newUnitCost): float
    {
        // Get current stock quantities and costs
        $stocks = ProductStock::where('product_id', $productId)->get();

        $currentQty = 0;
        $currentValue = 0;

        foreach ($stocks as $stock) {
            $qty = (float) $stock->quantity;
            $cost = (float) ($stock->avg_cost ?? 0);
            $currentQty += $qty;
            $currentValue += $qty * $cost;
        }

        // Calculate new weighted average
        $totalQty = $currentQty + $newQty;
        $totalValue = $currentValue + ($newQty * $newUnitCost);

        if ($totalQty <= 0) {
            return $newUnitCost; // If no stock, use the new cost
        }

        return round($totalValue / $totalQty, 4);
    }

    /**
     * Get the current weighted average cost for a product
     *
     * @param int $productId The product ID
     * @return float The current average cost
     */
    public function getCurrentAverageCost(int $productId): float
    {
        $stocks = ProductStock::where('product_id', $productId)->get();

        $totalQty = 0;
        $totalValue = 0;

        foreach ($stocks as $stock) {
            $qty = (float) $stock->quantity;
            $cost = (float) ($stock->avg_cost ?? 0);
            $totalQty += $qty;
            $totalValue += $qty * $cost;
        }

        if ($totalQty <= 0) {
            return 0;
        }

        return round($totalValue / $totalQty, 4);
    }

    /**
     * Update avg_cost on ProductStock records after receiving new stock
     *
     * @param int $productId The product ID
     * @param float $newAvgCost The new weighted average cost
     */
    public function updateProductStockCost(int $productId, float $newAvgCost): void
    {
        ProductStock::where('product_id', $productId)
            ->update(['avg_cost' => $newAvgCost]);
    }

    /**
     * Get inventory valuation summary
     *
     * @return array
     */
    public function getInventoryValuation(): array
    {
        $stocks = ProductStock::with('product')
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->get();

        $totalValue = 0;
        $items = [];

        foreach ($stocks as $stock) {
            $avgCost = $this->getCurrentAverageCost($stock->product_id);
            $value = $stock->total_qty * $avgCost;
            $totalValue += $value;

            $items[] = [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name ?? 'Unknown',
                'product_sku' => $stock->product->sku ?? '',
                'quantity' => $stock->total_qty,
                'avg_cost' => $avgCost,
                'total_value' => round($value, 2),
            ];
        }

        return [
            'total_value' => round($totalValue, 2),
            'items' => $items,
        ];
    }

    /**
     * Get inventory account balance from journal entries
     *
     * @param string|null $asOfDate Optional date to calculate balance as of
     * @return float
     */
    public function getInventoryAccountBalance(?string $asOfDate = null): float
    {
        $inventoryAccount = $this->getInventoryAccount();

        if (!$inventoryAccount) {
            return 0;
        }

        $query = JournalEntryLine::where('account_id', $inventoryAccount->id)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true);
                if ($asOfDate) {
                    $q->whereDate('entry_date', '<=', $asOfDate);
                }
            });

        $debits = (clone $query)->sum('debit');
        $credits = (clone $query)->sum('credit');

        // For asset accounts: balance = debits - credits
        return round($debits - $credits, 2);
    }

    /**
     * Compare inventory valuation with accounting balance
     *
     * @return array
     */
    public function reconcileInventory(): array
    {
        $physicalValue = $this->getInventoryValuation()['total_value'];
        $accountingBalance = $this->getInventoryAccountBalance();
        $variance = round($physicalValue - $accountingBalance, 2);

        return [
            'physical_inventory_value' => $physicalValue,
            'accounting_balance' => $accountingBalance,
            'variance' => $variance,
            'is_reconciled' => abs($variance) < 0.01, // Allow for small rounding differences
        ];
    }
}
