<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\SalesChannel;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\InventoryAccountingService;
use App\Services\SalesChannelAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncInventoryAccounting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:sync-inventory
                            {--dry-run : Show what would be done without making changes}
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}
                            {--skip-clear : Skip clearing existing journal entries}
                            {--skip-channel-accounts : Skip creating sales channel accounts}
                            {--backfill-bills : Only create bills for purchases that don\'t have one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync inventory with accounting: creates sales channel accounts, clears journal entries, and re-syncs from purchases and sales chronologically';

    protected InventoryAccountingService $accountingService;
    protected SalesChannelAccountService $channelAccountService;

    // Statistics
    protected int $channelAccountsCreated = 0;
    protected int $purchaseReceiptsCreated = 0;
    protected int $purchaseChargesCreated = 0;
    protected int $salesEntriesCreated = 0;
    protected int $cogsEntriesCreated = 0;
    protected float $totalPurchaseValue = 0;
    protected float $totalSalesValue = 0;
    protected float $totalCogsValue = 0;
    protected float $totalDutiesValue = 0;
    protected float $totalFreightValue = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $skipClear = $this->option('skip-clear');
        $skipChannelAccounts = $this->option('skip-channel-accounts');
        $backfillBills = $this->option('backfill-bills');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║         INVENTORY ACCOUNTING SYNC COMMAND                    ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->info('');
        }

        // Initialize services
        $this->accountingService = new InventoryAccountingService();
        $this->channelAccountService = new SalesChannelAccountService();

        // Step 1: Validate required accounts
        if (!$this->validateAccounts()) {
            return 1;
        }

        // Step 2: Create sales channel accounts (if not skipped)
        if (!$skipChannelAccounts) {
            $this->createSalesChannelAccounts($dryRun);
        }

        // If backfill-bills mode, only create bills for purchases without them
        if ($backfillBills) {
            return $this->backfillPurchaseBills($dryRun, $fromDate, $toDate);
        }

        // Step 3: Clear existing journal entries (unless skipped)
        if (!$skipClear && !$dryRun) {
            if (!$this->confirm('⚠️  This will DELETE all existing journal entries and lines. Continue?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
            $this->clearJournalEntries();
        } elseif ($skipClear) {
            $this->warn('Skipping journal entry clearing as requested.');
        }

        // Step 4: Collect all transactions (purchases and sales)
        $this->info('');
        $this->info('📦 Collecting transactions...');

        $transactions = $this->collectAllTransactions($fromDate, $toDate);

        $this->info("   Found {$transactions->count()} transactions to process");
        $this->info('');

        if ($transactions->isEmpty()) {
            $this->warn('No transactions found to process.');
            $this->displaySummary($dryRun);
            return 0;
        }

        // Step 5: Process transactions chronologically
        $this->info('⚙️  Processing transactions chronologically...');
        $this->info('');

        $progressBar = $this->output->createProgressBar($transactions->count());
        $progressBar->start();

        foreach ($transactions as $transaction) {
            if (!$dryRun) {
                DB::transaction(function () use ($transaction) {
                    $this->processTransaction($transaction);
                });
            } else {
                // In dry run, just count what would be done
                $this->countTransaction($transaction);
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Step 6: Display summary
        $this->displaySummary($dryRun);

        return 0;
    }

    /**
     * Backfill purchase bills for purchases that don't have one
     */
    protected function backfillPurchaseBills(bool $dryRun, ?string $fromDate, ?string $toDate): int
    {
        $this->info('📋 Backfilling purchase bills for old purchases...');
        $this->info('');

        // Get all purchases that don't have a purchase_bill journal entry
        $purchaseQuery = Purchase::with(['purchase_items.product', 'supplier'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.reference_id', 'purchases.id')
                    ->where('journal_entries.reference_type', 'purchase_bill');
            });

        if ($fromDate) {
            $purchaseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $purchaseQuery->whereDate('created_at', '<=', $toDate);
        }

        $purchases = $purchaseQuery->get();

        if ($purchases->isEmpty()) {
            $this->info('✓ All purchases already have bills. Nothing to backfill.');
            return 0;
        }

        $this->info("   Found {$purchases->count()} purchase(s) without bills");
        $this->info('');

        $progressBar = $this->output->createProgressBar($purchases->count());
        $progressBar->start();

        foreach ($purchases as $purchase) {
            if (!$dryRun) {
                DB::transaction(function () use ($purchase) {
                    $entry = $this->accountingService->recordPurchaseBill($purchase);
                    if ($entry) {
                        $entry->update(['entry_date' => $purchase->created_at]);
                        $this->purchaseReceiptsCreated++;

                        $purchaseValue = 0;
                        foreach ($purchase->purchase_items as $item) {
                            $purchaseValue += round((float) $item->quantity * (float) $item->price, 2);
                        }
                        $this->totalPurchaseValue += $purchaseValue;
                        $this->totalDutiesValue += (float) ($purchase->duties_customs ?? 0);
                        $this->totalFreightValue += (float) ($purchase->freight_charges ?? 0);
                    }
                });
            } else {
                // Count for dry run
                $purchaseValue = 0;
                foreach ($purchase->purchase_items as $item) {
                    $purchaseValue += round((float) $item->quantity * (float) $item->price, 2);
                }
                $this->purchaseReceiptsCreated++;
                $this->totalPurchaseValue += $purchaseValue;
                $this->totalDutiesValue += (float) ($purchase->duties_customs ?? 0);
                $this->totalFreightValue += (float) ($purchase->freight_charges ?? 0);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Display summary
        $totalValue = $this->totalPurchaseValue + $this->totalDutiesValue + $this->totalFreightValue;

        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                   BACKFILL SUMMARY                           ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        $this->table(['Category', 'Count', 'Amount'], [
            ['Purchase Bills Created', $this->purchaseReceiptsCreated, '$' . number_format($totalValue, 2)],
            ['  └─ Items Value', '-', '$' . number_format($this->totalPurchaseValue, 2)],
            ['  └─ Duties & Customs', '-', '$' . number_format($this->totalDutiesValue, 2)],
            ['  └─ Freight Charges', '-', '$' . number_format($this->totalFreightValue, 2)],
        ]);

        $this->info('');

        if ($dryRun) {
            $this->warn('═══════════════════════════════════════════════════════════════');
            $this->warn('  This was a DRY RUN. No changes were made to the database.');
            $this->warn('  Run without --dry-run to apply changes.');
            $this->warn('═══════════════════════════════════════════════════════════════');
        } else {
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info('  ✅ Purchase bills backfilled successfully!');
            $this->info('═══════════════════════════════════════════════════════════════');
        }

        return 0;
    }

    /**
     * Create accounting accounts for sales channels that don't have them
     */
    protected function createSalesChannelAccounts(bool $dryRun): void
    {
        $this->info('🏪 Checking sales channel accounts...');

        $channels = SalesChannel::where(function ($q) {
            $q->whereNull('receivable_account_id')
              ->orWhereNull('sales_account_id');
        })->get();

        if ($channels->isEmpty()) {
            $this->info('   ✓ All sales channels have accounting accounts');
            $this->info('');
            return;
        }

        $this->info("   Found {$channels->count()} channel(s) without accounts");

        foreach ($channels as $channel) {
            if ($dryRun) {
                $this->info("   → Would create accounts for: {$channel->name}");
                $this->channelAccountsCreated++;
            } else {
                if ($this->channelAccountService->createAccountsForChannel($channel)) {
                    $this->info("   ✓ Created accounts for: {$channel->name}");
                    $this->channelAccountsCreated++;
                } else {
                    $this->error("   ✗ Failed to create accounts for: {$channel->name}");
                }
            }
        }

        $this->info('');
    }

    /**
     * Validate that all required accounts exist
     */
    protected function validateAccounts(): bool
    {
        $this->info('🔍 Validating required accounts...');

        $accounts = [
            ['method' => 'getInventoryAccount', 'name' => 'Inventory (1201)'],
            ['method' => 'getCOGSAccount', 'name' => 'COGS (5001)'],
            ['method' => 'getPayablesAccount', 'name' => 'Trade Payables (2001)'],
            ['method' => 'getSalesAccount', 'name' => 'Product Sales (4001)'],
            ['method' => 'getReceivablesAccount', 'name' => 'Accounts Receivable (1301)'],
            ['method' => 'getDutiesAccount', 'name' => 'Duties & Customs (5003)'],
            ['method' => 'getFreightAccount', 'name' => 'Freight Charges (5002)'],
        ];

        $allFound = true;
        foreach ($accounts as $account) {
            $method = $account['method'];
            $found = $this->accountingService->$method();
            if ($found) {
                $this->info("   ✓ {$account['name']}");
            } else {
                $this->error("   ✗ {$account['name']} - NOT FOUND");
                $allFound = false;
            }
        }

        if (!$allFound) {
            $this->error('');
            $this->error('Please ensure all required accounts exist in the Chart of Accounts.');
            return false;
        }

        $this->info('');
        return true;
    }

    /**
     * Clear all existing journal entries and lines
     */
    protected function clearJournalEntries(): void
    {
        $this->info('🗑️  Clearing existing journal entries...');

        $lineCount = JournalEntryLine::count();
        $entryCount = JournalEntry::count();

        // Delete lines first (foreign key constraint)
        JournalEntryLine::query()->delete();
        JournalEntry::query()->delete();

        $this->info("   Deleted {$lineCount} journal entry lines");
        $this->info("   Deleted {$entryCount} journal entries");
    }

    /**
     * Collect all purchases and fulfilled orders, sorted by date
     */
    protected function collectAllTransactions(?string $fromDate, ?string $toDate)
    {
        $transactions = collect();

        // Get ALL purchases (bills are created when purchase is entered, not when received)
        $purchaseQuery = Purchase::with(['purchase_items.product', 'supplier']);

        if ($fromDate) {
            $purchaseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $purchaseQuery->whereDate('created_at', '<=', $toDate);
        }

        $purchases = $purchaseQuery->get();

        foreach ($purchases as $purchase) {
            $transactions->push([
                'type' => 'purchase',
                'date' => $purchase->created_at,
                'data' => $purchase,
            ]);
        }

        // Get paid orders (with salesChannel for channel-specific accounts)
        // Revenue is recognized when payment is received, not when shipped
        $orderQuery = Order::with(['items.product', 'salesChannel'])
            ->where('payment_status', 'paid')
            ->whereNotIn('order_status', ['cancelled', 'refunded']);

        if ($fromDate) {
            $orderQuery->whereDate('order_date', '>=', $fromDate);
        }
        if ($toDate) {
            $orderQuery->whereDate('order_date', '<=', $toDate);
        }

        $orders = $orderQuery->get();

        foreach ($orders as $order) {
            $transactions->push([
                'type' => 'sale',
                'date' => $order->order_date ?? $order->created_at,
                'data' => $order,
            ]);
        }

        // Sort all transactions by date (chronologically)
        return $transactions->sortBy(function ($transaction) {
            return Carbon::parse($transaction['date'])->timestamp;
        })->values();
    }

    /**
     * Process a single transaction
     */
    protected function processTransaction(array $transaction): void
    {
        if ($transaction['type'] === 'purchase') {
            $this->processPurchase($transaction['data']);
        } else {
            $this->processSale($transaction['data']);
        }
    }

    /**
     * Process a purchase - create purchase bill journal entry
     */
    protected function processPurchase(Purchase $purchase): void
    {
        // Calculate total purchase value for statistics
        $purchaseValue = 0;
        foreach ($purchase->purchase_items as $item) {
            $purchaseValue += round((float) $item->quantity * (float) $item->price, 2);
        }

        // Record purchase bill (includes items, duties and freight)
        $entry = $this->accountingService->recordPurchaseBill($purchase);

        if ($entry) {
            $entry->update(['entry_date' => $purchase->created_at]);
            $this->purchaseReceiptsCreated++;
            $this->totalPurchaseValue += $purchaseValue;
            $this->totalDutiesValue += (float) ($purchase->duties_customs ?? 0);
            $this->totalFreightValue += (float) ($purchase->freight_charges ?? 0);
        }
    }

    /**
     * Process a sale - create journal entries for COGS and Sales Revenue
     * Only processes items where inventory_updated = true
     */
    protected function processSale(Order $order): void
    {
        foreach ($order->items as $item) {
            // Skip bundle components (they don't have their own price)
            if ($item->bundle_product_id && !$item->is_bundle_summary) {
                continue;
            }

            // Skip if inventory was not updated (stock not deducted)
            if (!$item->inventory_updated) {
                continue;
            }

            $entryDate = $order->order_date ?? $order->created_at;

            // Record COGS
            $avgCost = $item->product ? $this->accountingService->getCurrentAverageCost($item->product_id) : 0;

            // If no avg cost from stock, try to get from product's cost field
            if ($avgCost <= 0 && $item->product) {
                $avgCost = (float) ($item->product->cost ?? 0);
            }

            if ($avgCost > 0) {
                $cogsEntry = $this->accountingService->recordCOGS($order, $item, $avgCost);
                if ($cogsEntry) {
                    $cogsEntry->update(['entry_date' => $entryDate]);
                    $this->cogsEntriesCreated++;
                    $this->totalCogsValue += ($item->quantity * $avgCost);
                }
            }

            // Record Sales Revenue
            $saleAmount = round($item->unit_price * $item->quantity, 2);
            if ($saleAmount > 0) {
                $salesEntry = $this->accountingService->recordSalesRevenue($order, $item);
                if ($salesEntry) {
                    $salesEntry->update(['entry_date' => $entryDate]);
                    $this->salesEntriesCreated++;
                    $this->totalSalesValue += $saleAmount;
                }
            }
        }
    }

    /**
     * Count what would be done (for dry run)
     */
    protected function countTransaction(array $transaction): void
    {
        if ($transaction['type'] === 'purchase') {
            $purchase = $transaction['data'];

            // Count purchase bill (one entry per purchase)
            $purchaseValue = 0;
            foreach ($purchase->purchase_items as $item) {
                $purchaseValue += round((float) $item->quantity * (float) $item->price, 2);
            }

            if ($purchaseValue > 0) {
                $this->purchaseReceiptsCreated++;
                $this->totalPurchaseValue += $purchaseValue;
                $this->totalDutiesValue += (float) ($purchase->duties_customs ?? 0);
                $this->totalFreightValue += (float) ($purchase->freight_charges ?? 0);
            }
        } else {
            $order = $transaction['data'];

            foreach ($order->items as $item) {
                if ($item->bundle_product_id && !$item->is_bundle_summary) {
                    continue;
                }

                // Skip if inventory was not updated (stock not deducted)
                if (!$item->inventory_updated) {
                    continue;
                }

                // Count COGS
                $avgCost = $item->product ? $this->accountingService->getCurrentAverageCost($item->product_id) : 0;
                if ($avgCost <= 0 && $item->product) {
                    $avgCost = (float) ($item->product->cost ?? 0);
                }

                if ($avgCost > 0) {
                    $this->cogsEntriesCreated++;
                    $this->totalCogsValue += ($item->quantity * $avgCost);
                }

                // Count Sales Revenue
                $saleAmount = round($item->unit_price * $item->quantity, 2);
                if ($saleAmount > 0) {
                    $this->salesEntriesCreated++;
                    $this->totalSalesValue += $saleAmount;
                }
            }
        }
    }

    /**
     * Display summary of operations
     */
    protected function displaySummary(bool $dryRun): void
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                        SUMMARY                               ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        $rows = [];

        // Add channel accounts row if any were created
        if ($this->channelAccountsCreated > 0) {
            $rows[] = ['Sales Channel Accounts', $this->channelAccountsCreated, '-'];
            $rows[] = ['─────────────────────', '─────────', '──────────────────'];
        }

        $totalPurchaseWithCharges = $this->totalPurchaseValue + $this->totalDutiesValue + $this->totalFreightValue;

        $rows = array_merge($rows, [
            ['Purchase Bills', $this->purchaseReceiptsCreated, '$' . number_format($totalPurchaseWithCharges, 2)],
            ['  └─ Items Value', '-', '$' . number_format($this->totalPurchaseValue, 2)],
            ['  └─ Duties & Customs', '-', '$' . number_format($this->totalDutiesValue, 2)],
            ['  └─ Freight Charges', '-', '$' . number_format($this->totalFreightValue, 2)],
            ['─────────────────────', '─────────', '──────────────────'],
            ['Sales Revenue', $this->salesEntriesCreated, '$' . number_format($this->totalSalesValue, 2)],
            ['Cost of Goods Sold', $this->cogsEntriesCreated, '$' . number_format($this->totalCogsValue, 2)],
            ['─────────────────────', '─────────', '──────────────────'],
            ['Gross Profit', '-', '$' . number_format($this->totalSalesValue - $this->totalCogsValue, 2)],
        ]);

        $this->table(['Category', 'Entries', 'Amount'], $rows);

        $totalEntries = $this->purchaseReceiptsCreated + $this->salesEntriesCreated + $this->cogsEntriesCreated;

        $this->info('');
        $this->info("Total Journal Entries: {$totalEntries}");
        $this->info('');

        if ($dryRun) {
            $this->warn('═══════════════════════════════════════════════════════════════');
            $this->warn('  This was a DRY RUN. No changes were made to the database.');
            $this->warn('  Run without --dry-run to apply changes.');
            $this->warn('═══════════════════════════════════════════════════════════════');
        } else {
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info('  ✅ Inventory accounting sync completed successfully!');
            $this->info('═══════════════════════════════════════════════════════════════');
        }
    }
}
