<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Category;
use App\Services\JournalService;
use App\Services\InventoryAccountingService;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
        $this->middleware('permission:accounting-reports-view');
    }

    /**
     * Reports dashboard
     */
    public function index()
    {
        return view('reports.index');
    }

    /**
     * Trail Balance Report
     */
    public function trialBalance(Request $request)
    {
        $asOfDate = $request->get('as_of_date', date('Y-m-d'));

        $accounts = $this->journalService->getTrialBalance($asOfDate);

        $totalDebit = $accounts->sum('debit');
        $totalCredit = $accounts->sum('credit');

        return view('reports.trial-balance', compact(
            'accounts',
            'totalDebit',
            'totalCredit',
            'asOfDate'
        ));
    }

    /**
     * Expense Report - breakdown by category
     */
    public function expenseReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $groupId = $request->get('group_id');

        // Get expense groups for filter dropdown
        $expenseGroups = ChartOfAccount::where('type', 'group')
            ->where('nature', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Build query: get bill items from posted bills within date range
        $query = BillItem::select(
                'bill_items.expense_account_id',
                DB::raw('SUM(bill_items.amount) as total_amount'),
                DB::raw('COUNT(DISTINCT bill_items.bill_id) as bill_count')
            )
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereIn('bills.status', ['unpaid', 'partially_paid', 'paid'])
            ->where('bills.bill_date', '>=', $dateFrom)
            ->where('bills.bill_date', '<=', $dateTo);

        //Filter by expense group if selected
        if ($groupId) {
            $accountIds = ChartOfAccount::where('parent_id', $groupId)
                ->pluck('id');
            $query->whereIn('bill_items.expense_account_id', $accountIds);
        }

        $expenseItems = $query->groupBy('bill_items.expense_account_id')->get();

        // Load account and group info
        $accountIds = $expenseItems->pluck('expense_account_id');
        $accounts = ChartOfAccount::whereIn('id', $accountIds)
            ->with('parent')
            ->get()
            ->keyBy('id');

        // Build grouped report data using array (not collection) to avoid indirect modification issues
        $reportDataArray = [];
        foreach ($expenseItems as $item) {
            $account = $accounts->get($item->expense_account_id);
            if (!$account) continue;

            $groupName = $account->parent?->name ?? 'Ungrouped';
            $groupCode = $account->parent?->code ?? '0000';

            if (!isset($reportDataArray[$groupName])) {
                $reportDataArray[$groupName] = [
                    'code' => $groupCode,
                    'name' => $groupName,
                    'total' => 0,
                    'items' => [],
                ];
            }

            $reportDataArray[$groupName]['total'] += $item->total_amount;
            $reportDataArray[$groupName]['items'][] = [
                'code' => $account->code,
                'name' => $account->name,
                'bill_count' => $item->bill_count,
                'total_amount' => $item->total_amount,
            ];
        }

        // Convert to collection and sort groups by code, items by total descending
        $reportData = collect($reportDataArray)->sortBy('code')->values();
        $reportData = $reportData->map(function ($group) {
            $group['items'] = collect($group['items'])->sortByDesc('total_amount')->values();
            return $group;
        });

        $grandTotal = $expenseItems->sum('total_amount');

        return view('reports.expense-report', compact(
            'reportData',
            'grandTotal',
            'expenseGroups',
            'dateFrom',
            'dateTo',
            'groupId'
        ));
    }

    /**
     * Supplier Ledger Report
     */
    public function supplierLedger(Request $request)
    {
        $supplierId = $request->get('supplier_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Get all active suppliers for dropdown
        $suppliers = Supplier::where('delete_status', '0')
            ->orderBy('first_name')
            ->get();

        $supplier = null;
        $transactions = collect();
        $openingBalance = 0;
        $totalBills = 0;
        $totalPayments = 0;

        if ($supplierId) {
            $supplier = Supplier::findOrFail($supplierId);

            // Get bills query
            $billsQuery = Bill::where('supplier_id', $supplierId)
                ->whereIn('status', ['unpaid', 'partially_paid', 'paid']);

            if ($dateFrom) {
                $billsQuery->where('bill_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $billsQuery->where('bill_date', '<=', $dateTo);
            }

            $bills = $billsQuery->orderBy('bill_date')->get();

            // Get payments for this supplier's bills
            $billIds = Bill::where('supplier_id', $supplierId)
                ->whereIn('status', ['unpaid', 'partially_paid', 'paid'])
                ->pluck('id');

            $paymentsQuery = Payment::whereIn('bill_id', $billIds)
                ->where('status', 'posted');

            if ($dateFrom) {
                $paymentsQuery->where('payment_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $paymentsQuery->where('payment_date', '<=', $dateTo);
            }

            $payments = $paymentsQuery->orderBy('payment_date')->get();

            // Calculate opening balance (bills - payments before date_from)
            if ($dateFrom) {
                $billsBefore = Bill::where('supplier_id', $supplierId)
                    ->whereIn('status', ['unpaid', 'partially_paid', 'paid'])
                    ->where('bill_date', '<', $dateFrom)
                    ->sum('total_amount');

                $paymentsBefore = Payment::whereIn('bill_id', $billIds)
                    ->where('status', 'posted')
                    ->where('payment_date', '<', $dateFrom)
                    ->sum('amount');

                $openingBalance = $billsBefore - $paymentsBefore;
            }

            // Build combined timeline
            $combined = collect();

            foreach ($bills as $bill) {
                $combined->push([
                    'date' => $bill->bill_date,
                    'type' => 'bill',
                    'reference' => $bill->bill_number,
                    'reference_id' => $bill->id,
                    'description' => 'Bill - ' . ($bill->notes ?? 'Expense Bill'),
                    'debit' => $bill->total_amount,
                    'credit' => 0,
                ]);
            }

            foreach ($payments as $payment) {
                $combined->push([
                    'date' => $payment->payment_date,
                    'type' => 'payment',
                    'reference' => $payment->payment_number,
                    'reference_id' => $payment->id,
                    'bill_number' => $payment->bill->bill_number ?? '',
                    'description' => 'Payment - ' . ucfirst($payment->payment_method) . ($payment->reference ? " ({$payment->reference})" : ''),
                    'debit' => 0,
                    'credit' => $payment->amount,
                ]);
            }

            // Sort by date, then bills before payments on same date
            $transactions = $combined->sortBy([
                ['date', 'asc'],
                ['type', 'asc'], // 'bill' comes before 'payment' alphabetically
            ])->values();

            $totalBills = $bills->sum('total_amount');
            $totalPayments = $payments->sum('amount');

        }
        
        return view('reports.supplier-ledger', compact(
            'suppliers',
            'supplier',
            'transactions',
            'openingBalance',
            'totalBills',
            'totalPayments',
            'supplierId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Bank & Cash Summary Report
     */
    public function bankSummary(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));

        // Get all bank/cash accounts
        $bankAccounts = ChartOfAccount::where('is_bank_cash', true)
            ->where('type', 'account')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $accountSummaries = collect();

        foreach ($bankAccounts as $account) {
            // Get transactions within date range from journal entry lines
            $transactions = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '>=', $dateFrom)
                      ->where('entry_date', '<=', $dateTo);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit, COUNT(*) as transaction_count')
                ->first();

            $totalDebit = $transactions->total_debit ?? 0;
            $totalCredit = $transactions->total_credit ?? 0;
            $transactionCount = $transactions->transaction_count ?? 0;

            // Opening balance (before date_from)
            $openingQuery = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($dateFrom) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<', $dateFrom);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $openingDebit = $openingQuery->total_debit ?? 0;
            $openingCredit = $openingQuery->total_credit ?? 0;
            $openingBalance = $account->opening_balance + ($openingDebit - $openingCredit);

            // Closing balance
            $closingBalance = $openingBalance + ($totalDebit - $totalCredit);

            // For bank/cash (asset accounts): Debit = inflow, Credit = outflow
            $accountSummaries->push([
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'opening_balance' => $openingBalance,
                'inflow' => $totalDebit,   // Debit increases asset
                'outflow' => $totalCredit,  // Credit decreases asset
                'closing_balance' => $closingBalance,
                'transaction_count' => $transactionCount,
            ]);
        }

        $totalOpening = $accountSummaries->sum('opening_balance');
        $totalInflow = $accountSummaries->sum('inflow');
        $totalOutflow = $accountSummaries->sum('outflow');
        $totalClosing = $accountSummaries->sum('closing_balance');

        return view('reports.bank-summary', compact(
            'accountSummaries',
            'totalOpening',
            'totalInflow',
            'totalOutflow',
            'totalClosing',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Purchase Report - Detailed purchase analysis with accounting sync
     */
    public function purchaseReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $supplierId = $request->get('supplier_id');
        $warehouseId = $request->get('warehouse_id');
        $status = $request->get('status');
        $groupBy = $request->get('group_by', 'supplier'); // supplier, warehouse, product

        // Get filter options
        $suppliers = Supplier::where('delete_status', '0')
            ->orderBy('first_name')
            ->get();

        $warehouses = Warehouse::where('delete_status', '0')
            ->orderBy('name')
            ->get();

        // Build purchase query
        $purchaseQuery = Purchase::with(['supplier', 'warehouse', 'purchase_items.product'])
            ->where('delete_status', '0')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if ($supplierId) {
            $purchaseQuery->where('supplier_id', $supplierId);
        }

        if ($warehouseId) {
            $purchaseQuery->where('warehouse_id', $warehouseId);
        }

        if ($status) {
            $purchaseQuery->where('purchase_status', $status);
        }

        $purchases = $purchaseQuery->orderBy('created_at', 'desc')->get();

        // Calculate summary statistics
        $summary = [
            'total_purchases' => $purchases->count(),
            'pending_count' => $purchases->where('purchase_status', 'pending')->count(),
            'partial_count' => $purchases->where('purchase_status', 'partial')->count(),
            'received_count' => $purchases->where('purchase_status', 'received')->count(),
            'total_ordered_qty' => 0,
            'total_received_qty' => 0,
            'total_ordered_value' => 0,
            'total_received_value' => 0,
        ];

        foreach ($purchases as $purchase) {
            foreach ($purchase->purchase_items as $item) {
                $summary['total_ordered_qty'] += (float) $item->quantity;
                $summary['total_received_qty'] += (float) $item->received_quantity;
                $summary['total_ordered_value'] += (float) $item->quantity * (float) $item->price;
                $summary['total_received_value'] += (float) $item->received_quantity * (float) $item->price;
            }
        }

        $summary['pending_value'] = $summary['total_ordered_value'] - $summary['total_received_value'];

        // Build grouped report data based on group_by parameter
        $reportData = collect();

        if ($groupBy === 'supplier') {
            $reportData = $this->groupPurchasesBySupplier($purchases);
        } elseif ($groupBy === 'warehouse') {
            $reportData = $this->groupPurchasesByWarehouse($purchases);
        } elseif ($groupBy === 'product') {
            $reportData = $this->groupPurchasesByProduct($purchases);
        }

        // Get related accounting data (bills linked to suppliers)
        $supplierIds = $purchases->pluck('supplier_id')->unique();
        $relatedBills = Bill::whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['unpaid', 'partially_paid', 'paid'])
            ->whereDate('bill_date', '>=', $dateFrom)
            ->whereDate('bill_date', '<=', $dateTo)
            ->with('supplier')
            ->get();

        $accountingSummary = [
            'total_bills' => $relatedBills->count(),
            'total_billed_amount' => $relatedBills->sum('total_amount'),
            'total_paid_amount' => $relatedBills->sum('paid_amount'),
            'outstanding_amount' => $relatedBills->sum('total_amount') - $relatedBills->sum('paid_amount'),
        ];

        return view('reports.purchase-report', compact(
            'purchases',
            'reportData',
            'summary',
            'accountingSummary',
            'suppliers',
            'warehouses',
            'dateFrom',
            'dateTo',
            'supplierId',
            'warehouseId',
            'status',
            'groupBy'
        ));
    }

    /**
     * Group purchases by supplier
     */
    protected function groupPurchasesBySupplier($purchases)
    {
        $grouped = [];

        foreach ($purchases as $purchase) {
            $supplierName = $purchase->supplier->full_name ?? 'Unknown Supplier';
            $supplierId = $purchase->supplier_id;

            if (!isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [
                    'name' => $supplierName,
                    'purchase_count' => 0,
                    'ordered_qty' => 0,
                    'received_qty' => 0,
                    'ordered_value' => 0,
                    'received_value' => 0,
                    'purchases' => [],
                ];
            }

            $grouped[$supplierId]['purchase_count']++;

            foreach ($purchase->purchase_items as $item) {
                $grouped[$supplierId]['ordered_qty'] += (float) $item->quantity;
                $grouped[$supplierId]['received_qty'] += (float) $item->received_quantity;
                $grouped[$supplierId]['ordered_value'] += (float) $item->quantity * (float) $item->price;
                $grouped[$supplierId]['received_value'] += (float) $item->received_quantity * (float) $item->price;
            }

            $grouped[$supplierId]['purchases'][] = $purchase;
        }

        return collect($grouped)->sortByDesc('ordered_value')->values();
    }

    /**
     * Group purchases by warehouse
     */
    protected function groupPurchasesByWarehouse($purchases)
    {
        $grouped = [];

        foreach ($purchases as $purchase) {
            $warehouseName = $purchase->warehouse->name ?? 'Unknown Warehouse';
            $warehouseId = $purchase->warehouse_id;

            if (!isset($grouped[$warehouseId])) {
                $grouped[$warehouseId] = [
                    'name' => $warehouseName,
                    'purchase_count' => 0,
                    'ordered_qty' => 0,
                    'received_qty' => 0,
                    'ordered_value' => 0,
                    'received_value' => 0,
                    'purchases' => [],
                ];
            }

            $grouped[$warehouseId]['purchase_count']++;

            foreach ($purchase->purchase_items as $item) {
                $grouped[$warehouseId]['ordered_qty'] += (float) $item->quantity;
                $grouped[$warehouseId]['received_qty'] += (float) $item->received_quantity;
                $grouped[$warehouseId]['ordered_value'] += (float) $item->quantity * (float) $item->price;
                $grouped[$warehouseId]['received_value'] += (float) $item->received_quantity * (float) $item->price;
            }

            $grouped[$warehouseId]['purchases'][] = $purchase;
        }

        return collect($grouped)->sortByDesc('ordered_value')->values();
    }

    /**
     * Group purchases by product
     */
    protected function groupPurchasesByProduct($purchases)
    {
        $grouped = [];

        foreach ($purchases as $purchase) {
            foreach ($purchase->purchase_items as $item) {
                $productId = $item->product_id;
                $productName = $item->product->name ?? $item->name;
                $productSku = $item->sku;

                if (!isset($grouped[$productId])) {
                    $grouped[$productId] = [
                        'name' => $productName,
                        'sku' => $productSku,
                        'purchase_count' => 0,
                        'ordered_qty' => 0,
                        'received_qty' => 0,
                        'ordered_value' => 0,
                        'received_value' => 0,
                        'avg_price' => 0,
                        'total_price' => 0,
                        'price_count' => 0,
                    ];
                }

                $grouped[$productId]['purchase_count']++;
                $grouped[$productId]['ordered_qty'] += (float) $item->quantity;
                $grouped[$productId]['received_qty'] += (float) $item->received_quantity;
                $grouped[$productId]['ordered_value'] += (float) $item->quantity * (float) $item->price;
                $grouped[$productId]['received_value'] += (float) $item->received_quantity * (float) $item->price;
                $grouped[$productId]['total_price'] += (float) $item->price;
                $grouped[$productId]['price_count']++;
            }
        }

        // Calculate average price
        foreach ($grouped as &$product) {
            $product['avg_price'] = $product['price_count'] > 0
                ? $product['total_price'] / $product['price_count']
                : 0;
        }

        return collect($grouped)->sortByDesc('ordered_value')->values();
    }

    /**
     * Sales Report - Detailed sales analysis with accounting sync
     */
    public function salesReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $paymentStatus = $request->get('payment_status');
        $groupBy = $request->get('group_by', 'channel'); // channel, product, date

        // Get filter options
        $salesChannels = SalesChannel::where('delete_status', '0')
            ->orderBy('name')
            ->get();

        // Build orders query
        $orderQuery = Order::with(['salesChannel', 'items.product'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        if ($channelId) {
            $orderQuery->where('sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $orderQuery->where('order_status', $orderStatus);
        }

        if ($paymentStatus) {
            $orderQuery->where('payment_status', $paymentStatus);
        }

        $orders = $orderQuery->orderBy('order_date', 'desc')->get();

        // Calculate summary statistics
        $summary = [
            'total_orders' => $orders->count(),
            'pending_count' => $orders->where('order_status', 'pending')->count(),
            'processing_count' => $orders->where('order_status', 'processing')->count(),
            'shipped_count' => $orders->where('order_status', 'shipped')->count(),
            'delivered_count' => $orders->where('order_status', 'delivered')->count(),
            'cancelled_count' => $orders->where('order_status', 'cancelled')->count(),
            'paid_count' => $orders->where('payment_status', 'paid')->count(),
            'total_revenue' => $orders->where('payment_status', 'paid')->sum('total'),
            'total_subtotal' => $orders->where('payment_status', 'paid')->sum('subtotal'),
            'total_shipping' => $orders->where('payment_status', 'paid')->sum('shipping_cost'),
            'total_tax' => $orders->where('payment_status', 'paid')->sum('tax'),
            'total_discount' => $orders->where('payment_status', 'paid')->sum('discount'),
            'total_items_sold' => 0,
            'average_order_value' => 0,
        ];

        // Calculate items sold
        foreach ($orders->where('payment_status', 'paid') as $order) {
            $summary['total_items_sold'] += $order->items->sum('quantity');
        }

        $summary['average_order_value'] = $summary['paid_count'] > 0
            ? $summary['total_revenue'] / $summary['paid_count']
            : 0;

        // Build grouped report data based on group_by parameter
        $reportData = collect();

        if ($groupBy === 'channel') {
            $reportData = $this->groupOrdersByChannel($orders);
        } elseif ($groupBy === 'product') {
            $reportData = $this->groupOrdersByProduct($orders);
        } elseif ($groupBy === 'date') {
            $reportData = $this->groupOrdersByDate($orders);
        }

        // Get related accounting data (payments received in this period)
        $relatedPayments = Payment::where('status', 'posted')
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->with('bill.supplier')
            ->get();

        // Get expense bills for the period
        $relatedBills = Bill::whereIn('status', ['unpaid', 'partially_paid', 'paid'])
            ->whereDate('bill_date', '>=', $dateFrom)
            ->whereDate('bill_date', '<=', $dateTo)
            ->get();

        $accountingSummary = [
            'total_payments_out' => $relatedPayments->sum('amount'),
            'total_bills' => $relatedBills->sum('total_amount'),
            'net_income' => $summary['total_revenue'] - $relatedBills->sum('total_amount'),
            'gross_margin' => $summary['total_revenue'] > 0
                ? (($summary['total_revenue'] - $relatedBills->sum('total_amount')) / $summary['total_revenue']) * 100
                : 0,
        ];

        return view('reports.sales-report', compact(
            'orders',
            'reportData',
            'summary',
            'accountingSummary',
            'salesChannels',
            'dateFrom',
            'dateTo',
            'channelId',
            'orderStatus',
            'paymentStatus',
            'groupBy'
        ));
    }

    /**
     * Group orders by sales channel
     */
    protected function groupOrdersByChannel($orders)
    {
        $grouped = [];

        foreach ($orders as $order) {
            $channelName = $order->salesChannel->name ?? 'Direct Sales';
            $channelId = $order->sales_channel_id ?? 0;

            if (!isset($grouped[$channelId])) {
                $grouped[$channelId] = [
                    'name' => $channelName,
                    'order_count' => 0,
                    'paid_count' => 0,
                    'items_sold' => 0,
                    'total_revenue' => 0,
                    'total_shipping' => 0,
                    'total_tax' => 0,
                    'orders' => [],
                ];
            }

            $grouped[$channelId]['order_count']++;

            if ($order->payment_status === 'paid') {
                $grouped[$channelId]['paid_count']++;
                $grouped[$channelId]['total_revenue'] += (float) $order->total;
                $grouped[$channelId]['total_shipping'] += (float) $order->shipping_cost;
                $grouped[$channelId]['total_tax'] += (float) $order->tax;
                $grouped[$channelId]['items_sold'] += $order->items->sum('quantity');
            }

            $grouped[$channelId]['orders'][] = $order;
        }

        return collect($grouped)->sortByDesc('total_revenue')->values();
    }

    /**
     * Group orders by product
     */
    protected function groupOrdersByProduct($orders)
    {
        $grouped = [];

        foreach ($orders as $order) {
            if ($order->payment_status !== 'paid') {
                continue;
            }

            foreach ($order->items as $item) {
                $productId = $item->product_id ?? $item->sku;
                $productName = $item->product->name ?? $item->title;
                $productSku = $item->sku;

                if (!isset($grouped[$productId])) {
                    $grouped[$productId] = [
                        'name' => $productName,
                        'sku' => $productSku,
                        'order_count' => 0,
                        'quantity_sold' => 0,
                        'total_revenue' => 0,
                        'avg_price' => 0,
                        'total_price' => 0,
                        'price_count' => 0,
                    ];
                }

                $grouped[$productId]['order_count']++;
                $grouped[$productId]['quantity_sold'] += (int) $item->quantity;
                $grouped[$productId]['total_revenue'] += (float) $item->total_price;
                $grouped[$productId]['total_price'] += (float) $item->unit_price;
                $grouped[$productId]['price_count']++;
            }
        }

        // Calculate average price
        foreach ($grouped as &$product) {
            $product['avg_price'] = $product['price_count'] > 0
                ? $product['total_price'] / $product['price_count']
                : 0;
        }

        return collect($grouped)->sortByDesc('total_revenue')->values();
    }

    /**
     * Group orders by date
     */
    protected function groupOrdersByDate($orders)
    {
        $grouped = [];

        foreach ($orders as $order) {
            $date = $order->order_date ? $order->order_date->format('Y-m-d') : 'Unknown';

            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'formatted_date' => $order->order_date ? $order->order_date->format('M d, Y') : 'Unknown',
                    'order_count' => 0,
                    'paid_count' => 0,
                    'items_sold' => 0,
                    'total_revenue' => 0,
                    'orders' => [],
                ];
            }

            $grouped[$date]['order_count']++;

            if ($order->payment_status === 'paid') {
                $grouped[$date]['paid_count']++;
                $grouped[$date]['total_revenue'] += (float) $order->total;
                $grouped[$date]['items_sold'] += $order->items->sum('quantity');
            }

            $grouped[$date]['orders'][] = $order;
        }

        return collect($grouped)->sortByDesc('date')->values();
    }

    /**
     * Inventory Valuation Report
     * Shows current inventory value with accounting reconciliation
     */
    public function inventoryValuation(Request $request)
    {
        $categoryId = $request->get('category_id');
        $warehouseId = $request->get('warehouse_id');
        $groupBy = $request->get('group_by', 'product'); // product, category, warehouse

        // Get filter options
        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        // Build query for product stocks
        $query = ProductStock::with(['product.category', 'warehouse', 'rack'])
            ->where('quantity', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $stocks = $query->get();

        // Calculate inventory values
        $inventoryItems = [];
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($stocks as $stock) {
            $product = $stock->product;
            $avgCost = (float) ($stock->avg_cost ?? 0);
            $quantity = (float) $stock->quantity;
            $value = $quantity * $avgCost;

            $inventoryItems[] = [
                'stock_id' => $stock->id,
                'product_id' => $product->id ?? null,
                'product_name' => $product->name ?? 'Unknown',
                'product_sku' => $product->sku ?? '',
                'category_id' => $product->category_id ?? null,
                'category_name' => $product->category->name ?? 'Uncategorized',
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                'rack_name' => $stock->rack->name ?? 'N/A',
                'quantity' => $quantity,
                'avg_cost' => $avgCost,
                'total_value' => round($value, 2),
            ];

            $totalQuantity += $quantity;
            $totalValue += $value;
        }

        // Group data based on selected grouping
        $groupedData = $this->groupInventoryData($inventoryItems, $groupBy);

        // Get accounting reconciliation
        $inventoryAccountingService = new InventoryAccountingService();
        $reconciliation = $inventoryAccountingService->reconcileInventory();

        // Summary statistics
        $summary = [
            'total_products' => collect($inventoryItems)->pluck('product_id')->unique()->count(),
            'total_quantity' => round($totalQuantity, 2),
            'total_value' => round($totalValue, 2),
            'avg_cost_per_unit' => $totalQuantity > 0 ? round($totalValue / $totalQuantity, 4) : 0,
        ];

        return view('reports.inventory-valuation', compact(
            'inventoryItems',
            'groupedData',
            'summary',
            'reconciliation',
            'categories',
            'warehouses',
            'categoryId',
            'warehouseId',
            'groupBy'
        ));
    }

    /**
     * Group inventory data by category, warehouse, or keep as products
     */
    protected function groupInventoryData($items, $groupBy)
    {
        $grouped = [];

        foreach ($items as $item) {
            switch ($groupBy) {
                case 'category':
                    $key = $item['category_id'] ?? 'uncategorized';
                    $name = $item['category_name'];
                    break;
                case 'warehouse':
                    $key = $item['warehouse_id'];
                    $name = $item['warehouse_name'];
                    break;
                default: // product
                    $key = $item['product_id'];
                    $name = $item['product_name'] . ' (' . $item['product_sku'] . ')';
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $key,
                    'name' => $name,
                    'quantity' => 0,
                    'total_value' => 0,
                    'item_count' => 0,
                    'items' => [],
                ];
            }

            $grouped[$key]['quantity'] += $item['quantity'];
            $grouped[$key]['total_value'] += $item['total_value'];
            $grouped[$key]['item_count']++;
            $grouped[$key]['items'][] = $item;
        }

        // Calculate average cost for each group
        foreach ($grouped as &$group) {
            $group['avg_cost'] = $group['quantity'] > 0
                ? round($group['total_value'] / $group['quantity'], 4)
                : 0;
            $group['total_value'] = round($group['total_value'], 2);
        }

        return collect($grouped)->sortByDesc('total_value')->values();
    }
}
