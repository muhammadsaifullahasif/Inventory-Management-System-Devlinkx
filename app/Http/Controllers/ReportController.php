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
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\ReportExport;
use App\Exports\TrialBalanceExport;
use App\Exports\ExpenseReportExport;
use App\Exports\SupplierLedgerExport;
use App\Exports\BankSummaryExport;
use App\Exports\InventoryValuationExport;
use Maatwebsite\Excel\Facades\Excel;

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
     * Export Trial Balance Report to Excel
     */
    public function exportTrialBalance(Request $request)
    {
        $asOfDate = $request->get('as_of_date', date('Y-m-d'));

        $accounts = $this->journalService->getTrialBalance($asOfDate);

        $totalDebit = $accounts->sum('debit');
        $totalCredit = $accounts->sum('credit');

        $export = new TrialBalanceExport($accounts->toArray(), $totalDebit, $totalCredit);

        return Excel::download($export, 'trial-balance-' . $asOfDate . '.xlsx');
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
     * Export Expense Report to Excel
     */
    public function exportExpenseReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $groupId = $request->get('group_id');

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

        // Build grouped report data
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

        // Convert to collection and sort
        $reportData = collect($reportDataArray)->sortBy('code')->values();
        $reportData = $reportData->map(function ($group) {
            $group['items'] = collect($group['items'])->sortByDesc('total_amount')->values();
            return $group;
        });

        $grandTotal = $expenseItems->sum('total_amount');

        $export = new ExpenseReportExport($reportData, $grandTotal);

        return Excel::download($export, 'expense-report-' . $dateFrom . '-to-' . $dateTo . '.xlsx');
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
     * Export Supplier Ledger Report to Excel
     */
    public function exportSupplierLedger(Request $request)
    {
        $supplierId = $request->get('supplier_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$supplierId) {
            return back()->with('error', 'Please select a supplier');
        }

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

        // Calculate opening balance
        $openingBalance = 0;
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

        // Sort by date
        $transactions = $combined->sortBy([
            ['date', 'asc'],
            ['type', 'asc'],
        ])->values();

        $export = new SupplierLedgerExport($supplier, $transactions, $openingBalance, $dateFrom, $dateTo);

        $filename = 'supplier-ledger-' . str_replace(' ', '-', strtolower($supplier->full_name));
        if ($dateFrom && $dateTo) {
            $filename .= '-' . $dateFrom . '-to-' . $dateTo;
        }

        return Excel::download($export, $filename . '.xlsx');
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
     * Export Bank & Cash Summary Report to Excel
     */
    public function exportBankSummary(Request $request)
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
            // Get transactions within date range
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

            // Opening balance
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

            $accountSummaries->push([
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'opening_balance' => $openingBalance,
                'inflow' => $totalDebit,
                'outflow' => $totalCredit,
                'closing_balance' => $closingBalance,
                'transaction_count' => $transactionCount,
            ]);
        }

        $totalOpening = $accountSummaries->sum('opening_balance');
        $totalInflow = $accountSummaries->sum('inflow');
        $totalOutflow = $accountSummaries->sum('outflow');
        $totalClosing = $accountSummaries->sum('closing_balance');

        $export = new BankSummaryExport($accountSummaries, $totalOpening, $totalInflow, $totalOutflow, $totalClosing);

        return Excel::download($export, 'bank-summary-' . $dateFrom . '-to-' . $dateTo . '.xlsx');
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
     * Export Purchase Report to Excel
     */
    public function exportPurchaseReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $supplierId = $request->get('supplier_id');
        $warehouseId = $request->get('warehouse_id');
        $status = $request->get('status');
        $groupBy = $request->get('group_by', 'supplier');

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
        if ($groupBy === 'supplier') {
            $groupedData = $this->groupPurchasesBySupplier($purchases);
        } elseif ($groupBy === 'warehouse') {
            $groupedData = $this->groupPurchasesByWarehouse($purchases);
        } elseif ($groupBy === 'product') {
            $groupedData = $this->groupPurchasesByProduct($purchases);
        } else {
            $groupedData = $this->groupPurchasesBySupplier($purchases);
        }

        $export = new \App\Exports\PurchaseReportExport($groupedData, $purchases, $summary);

        $filename = 'purchase-report-' . $dateFrom . '-to-' . $dateTo . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    /**
     * Export Single Purchase to Excel
     */
    public function exportSinglePurchase($purchaseId)
    {
        $purchase = Purchase::with(['supplier', 'warehouse', 'purchase_items.product'])->findOrFail($purchaseId);

        $export = new \App\Exports\SinglePurchaseExport($purchase);

        $filename = 'purchase-' . $purchase->purchase_number . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
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

        // Build orders query - get all for summary stats
        $allOrdersQuery = Order::whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        if ($channelId) {
            $allOrdersQuery->where('sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $allOrdersQuery->where('order_status', $orderStatus);
        }

        if ($paymentStatus) {
            $allOrdersQuery->where('payment_status', $paymentStatus);
        }

        $allOrders = $allOrdersQuery->get();

        // Calculate summary statistics from all orders
        $summary = [
            'total_orders' => $allOrders->count(),
            'pending_count' => $allOrders->where('order_status', 'pending')->count(),
            'processing_count' => $allOrders->where('order_status', 'processing')->count(),
            'shipped_count' => $allOrders->where('order_status', 'shipped')->count(),
            'delivered_count' => $allOrders->where('order_status', 'delivered')->count(),
            'cancelled_count' => $allOrders->where('order_status', 'cancelled')->count(),
            'paid_count' => $allOrders->where('payment_status', 'paid')->count(),
            'total_revenue' => $allOrders->where('payment_status', 'paid')->sum('total'),
            'total_subtotal' => $allOrders->where('payment_status', 'paid')->sum('subtotal'),
            'total_shipping' => $allOrders->where('payment_status', 'paid')->sum('shipping_cost'),
            'total_tax' => $allOrders->where('payment_status', 'paid')->sum('tax'),
            'total_discount' => $allOrders->where('payment_status', 'paid')->sum('discount'),
            'total_items_sold' => 0,
            'average_order_value' => 0,
        ];

        // Calculate items sold
        foreach ($allOrders->where('payment_status', 'paid') as $order) {
            $summary['total_items_sold'] += $order->items->sum('quantity');
        }

        $summary['average_order_value'] = $summary['paid_count'] > 0
            ? $summary['total_revenue'] / $summary['paid_count']
            : 0;

        // Build grouped report data based on group_by parameter
        $reportData = collect();

        if ($groupBy === 'channel') {
            $reportData = $this->groupOrdersByChannel($allOrders);
        } elseif ($groupBy === 'product') {
            $reportData = $this->groupOrdersByProduct($allOrders);
        } elseif ($groupBy === 'date') {
            $reportData = $this->groupOrdersByDate($allOrders);
        }

        // Paginated orders query for details table
        $ordersQuery = Order::with(['salesChannel', 'items.product'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        if ($channelId) {
            $ordersQuery->where('sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $ordersQuery->where('order_status', $orderStatus);
        }

        if ($paymentStatus) {
            $ordersQuery->where('payment_status', $paymentStatus);
        }

        $orders = $ordersQuery->orderBy('order_date', 'desc')->paginate(50);

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
     * Export Sales Report to Excel
     */
    public function exportSalesReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $paymentStatus = $request->get('payment_status');

        // Build orders query
        $allOrdersQuery = Order::with(['salesChannel', 'items.product'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        if ($channelId) {
            $allOrdersQuery->where('sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $allOrdersQuery->where('order_status', $orderStatus);
        }

        if ($paymentStatus) {
            $allOrdersQuery->where('payment_status', $paymentStatus);
        }

        $allOrders = $allOrdersQuery->get();

        // Calculate summary statistics
        $summary = [
            'total_orders' => $allOrders->count(),
            'pending_count' => $allOrders->where('order_status', 'pending')->count(),
            'processing_count' => $allOrders->where('order_status', 'processing')->count(),
            'shipped_count' => $allOrders->where('order_status', 'shipped')->count(),
            'delivered_count' => $allOrders->where('order_status', 'delivered')->count(),
            'cancelled_count' => $allOrders->where('order_status', 'cancelled')->count(),
            'paid_count' => $allOrders->where('payment_status', 'paid')->count(),
            'total_revenue' => $allOrders->where('payment_status', 'paid')->sum('total'),
            'total_subtotal' => $allOrders->where('payment_status', 'paid')->sum('subtotal'),
            'total_shipping' => $allOrders->where('payment_status', 'paid')->sum('shipping_cost'),
            'total_tax' => $allOrders->where('payment_status', 'paid')->sum('tax'),
            'total_discount' => $allOrders->where('payment_status', 'paid')->sum('discount'),
            'total_items_sold' => 0,
            'average_order_value' => 0,
        ];

        // Calculate items sold
        foreach ($allOrders->where('payment_status', 'paid') as $order) {
            $summary['total_items_sold'] += $order->items->sum('quantity');
        }

        $summary['average_order_value'] = $summary['paid_count'] > 0
            ? $summary['total_revenue'] / $summary['paid_count']
            : 0;

        // Group by channel for export
        $groupedData = $this->groupOrdersByChannel($allOrders);

        $export = new \App\Exports\SalesReportExport($groupedData, $summary);

        $filename = 'sales-report-' . $dateFrom . '-to-' . $dateTo . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
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

        $allStocks = $query->get();

        // Calculate inventory values for all data (needed for summary and grouping)
        $inventoryItems = [];
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($allStocks as $stock) {
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

        // Paginate grouped data
        $groupedPerPage = 25;
        $groupedCurrentPage = (int) request()->get('grouped_page', 1);
        $groupedOffset = ($groupedCurrentPage - 1) * $groupedPerPage;
        $groupedDataArray = $groupedData->toArray();
        $groupedDataPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($groupedDataArray, $groupedOffset, $groupedPerPage),
            count($groupedDataArray),
            $groupedPerPage,
            $groupedCurrentPage,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'grouped_page']
        );

        // Paginate inventory items for detailed list
        $perPage = 50;
        $currentPage = (int) request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $inventoryItemsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($inventoryItems, $offset, $perPage),
            count($inventoryItems),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

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
            'inventoryItemsPaginated',
            'groupedData',
            'groupedDataPaginated',
            'summary',
            'reconciliation',
            'categories',
            'warehouses',
            'categoryId',
            'warehouseId',
            'groupBy'
        ));
    }

    public function exportInventoryValuation(Request $request)
    {
        $categoryId = $request->get('category_id');
        $warehouseId = $request->get('warehouse_id');
        $groupBy = $request->get('group_by', 'product');

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

        $allStocks = $query->get();

        // Calculate inventory values
        $inventoryItems = [];
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($allStocks as $stock) {
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

        // Group data
        $groupedData = $this->groupInventoryData($inventoryItems, $groupBy);

        // Summary statistics
        $summary = [
            'total_products' => collect($inventoryItems)->pluck('product_id')->unique()->count(),
            'total_quantity' => round($totalQuantity, 2),
            'total_value' => round($totalValue, 2),
            'avg_cost_per_unit' => $totalQuantity > 0 ? round($totalValue / $totalQuantity, 4) : 0,
        ];

        $export = new InventoryValuationExport(
            $groupedData->toArray(),
            $inventoryItems,
            $summary,
            $groupBy
        );

        $filename = 'inventory-valuation-' . date('Y-m-d') . '.xlsx';

        return Excel::download($export, $filename);
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

    /**
     * Shipping Checklist Report
     * Shows orders ready for shipping with product details and warehouse stock
     */
    public function shippingChecklist(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-d'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $fulfillmentStatus = $request->get('fulfillment_status', 'unfulfilled');
        $order_status = $request->get('order_status', 'processing');

        // Get filter options
        $salesChannels = SalesChannel::where('delete_status', '0')
            ->orderBy('name')
            ->get();

        // Build orders query - get orders that need to be shipped
        $orderQuery = Order::with([
                'salesChannel',
                'items.product.product_stocks.warehouse',
                'items.product.product_stocks.rack',
                'items.product.product_meta'
            ])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->where('payment_status', 'paid')
            ->when($order_status !== 'all', function ($query) use ($order_status) {
                if ($order_status == 'fulfilled') {
                    $query->where('fulfillment_status', 'fulfilled');
                } else {
                    $query->where('fulfillment_status', 'unfulfilled');
                }
                // $query->where('fulfillment_status', $order_status);
            })
            ->whereNotIn('order_status', ['cancelled', 'refunded']);

        if ($channelId) {
            $orderQuery->where('sales_channel_id', $channelId);
        }

        // if ($fulfillmentStatus === 'unfulfilled') {
        //     $orderQuery->where('fulfillment_status', 'unfulfilled');
        // } elseif ($fulfillmentStatus === 'fulfilled') {
        //     $orderQuery->where('fulfillment_status', 'fulfilled');
        // }
        // 'all' shows everything

        $orders = $orderQuery->orderBy('order_date', 'asc')->get();

        // Build checklist items - group by order, handle bundles with components
        $checklistItems = [];

        foreach ($orders as $order) {
            // Process items - handle bundles specially
            foreach ($order->items as $item) {
                // For bundle summary items, include the bundle and its components
                if ($item->is_bundle_summary) {
                    $product = $item->product;

                    // Get bundle product details
                    $productMeta = $product ? $product->product_meta : [];
                    $weight = $productMeta['weight'] ?? null;
                    $weightUnit = $productMeta['weight_unit'] ?? 'lbs';
                    $length = $productMeta['length'] ?? null;
                    $width = $productMeta['width'] ?? null;
                    $height = $productMeta['height'] ?? null;
                    $dimensionUnit = $productMeta['dimension_unit'] ?? 'in';

                    // Get bundle image
                    $imageUrl = $product ? $product->getImageUrl() : null;

                    // Get bundle components from order items
                    $components = $order->items->filter(function ($i) use ($item) {
                        return $i->bundle_product_id == $item->product_id && !$i->is_bundle_summary;
                    });

                    // Build components data with their stock info
                    $componentsData = [];
                    foreach ($components as $component) {
                        $compProduct = $component->product;
                        $compMeta = $compProduct ? $compProduct->product_meta : [];

                        // Get component warehouse stock details
                        $compStocks = [];
                        $compTotalStock = 0;
                        if ($compProduct) {
                            foreach ($compProduct->product_stocks as $stock) {
                                $compStocks[] = [
                                    'warehouse' => $stock->warehouse->name ?? 'N/A',
                                    'rack' => $stock->rack->name ?? 'N/A',
                                    'quantity' => (int) $stock->quantity,
                                ];
                                $compTotalStock += (int) $stock->quantity;
                            }
                        }

                        $componentsData[] = [
                            'product_name' => $component->title ?? ($compProduct->name ?? 'Unknown'),
                            'sku' => $component->sku ?? ($compProduct->sku ?? ''),
                            'weight' => $compMeta['weight'] ?? null,
                            'weight_unit' => $compMeta['weight_unit'] ?? 'lbs',
                            'length' => $compMeta['length'] ?? null,
                            'width' => $compMeta['width'] ?? null,
                            'height' => $compMeta['height'] ?? null,
                            'dimension_unit' => $compMeta['dimension_unit'] ?? 'in',
                            'quantity_ordered' => (int) $component->quantity,
                            'warehouse_stocks' => $compStocks,
                            'total_stock' => $compTotalStock,
                        ];
                    }

                    $checklistItems[] = [
                        'order' => $order,
                        'item' => $item,
                        'ebay_order_id' => $order->ebay_order_id ?: $order->order_number,
                        'image_url' => $imageUrl,
                        'product_name' => $item->bundle_name ?? ($item->title ?? ($product->name ?? 'Unknown Bundle')),
                        'sku' => $item->sku ?? ($product->sku ?? ''),
                        'weight' => $weight,
                        'weight_unit' => $weightUnit,
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                        'dimension_unit' => $dimensionUnit,
                        'sales_channel' => $order->salesChannel->name ?? 'Direct',
                        'quantity_ordered' => (int) $item->quantity,
                        'is_bundle' => true,
                        'components' => $componentsData,
                        'warehouse_stocks' => [], // Bundles use component stocks
                        'total_stock' => 0, // Will be calculated from components
                    ];

                } elseif (!$item->bundle_product_id) {
                    // Regular product (not a bundle component)
                    $product = $item->product;

                    // Get product details
                    $productMeta = $product ? $product->product_meta : [];
                    $weight = $productMeta['weight'] ?? null;
                    $weightUnit = $productMeta['weight_unit'] ?? 'lbs';
                    $length = $productMeta['length'] ?? null;
                    $width = $productMeta['width'] ?? null;
                    $height = $productMeta['height'] ?? null;
                    $dimensionUnit = $productMeta['dimension_unit'] ?? 'in';

                    // Get warehouse stock details
                    $warehouseStocks = [];
                    $totalStock = 0;
                    if ($product) {
                        foreach ($product->product_stocks as $stock) {
                            $warehouseStocks[] = [
                                'warehouse' => $stock->warehouse->name ?? 'N/A',
                                'rack' => $stock->rack->name ?? 'N/A',
                                'quantity' => (int) $stock->quantity,
                            ];
                            $totalStock += (int) $stock->quantity;
                        }
                    }

                    // Get product image
                    $imageUrl = $product ? $product->getImageUrl() : null;

                    $checklistItems[] = [
                        'order' => $order,
                        'item' => $item,
                        'ebay_order_id' => $order->ebay_order_id ?: $order->order_number,
                        'image_url' => $imageUrl,
                        'product_name' => $item->title ?? ($product->name ?? 'Unknown Product'),
                        'sku' => $item->sku ?? ($product->sku ?? ''),
                        'weight' => $weight,
                        'weight_unit' => $weightUnit,
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                        'dimension_unit' => $dimensionUnit,
                        'sales_channel' => $order->salesChannel->name ?? 'Direct',
                        'quantity_ordered' => (int) $item->quantity,
                        'is_bundle' => false,
                        'components' => [],
                        'warehouse_stocks' => $warehouseStocks,
                        'total_stock' => $totalStock,
                    ];
                }
                // Skip bundle components - they're included with their parent bundle
            }
        }

        // Convert to collection and paginate
        $checklistCollection = collect($checklistItems);

        // Summary statistics
        $summary = [
            'total_orders' => $orders->count(),
            'total_items' => $checklistCollection->count(),
            'total_quantity' => $checklistCollection->sum('quantity_ordered'),
        ];

        // Paginate the checklist items
        $perPage = 50;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $checklistCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $checklistItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $checklistCollection->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        // Append query parameters to pagination links
        $checklistItems->appends($request->all());

        return view('reports.shipping-checklist', compact(
            'checklistItems',
            'summary',
            'salesChannels',
            'dateFrom',
            'dateTo',
            'channelId',
            // 'fulfillmentStatus',
            'order_status'
        ));
    }

    /**
     * Shipping Checklist PDF
     * Generate PDF with page breaks to keep rows intact
     */
    public function shippingChecklistPdf(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-d'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $order_status = $request->get('order_status', 'processing');

        // Build orders query - get orders that need to be shipped
        $orderQuery = Order::with([
                'salesChannel',
                'items.product.product_stocks.warehouse',
                'items.product.product_stocks.rack',
                'items.product.product_meta'
            ])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->where('payment_status', 'paid')
            ->when($order_status !== 'all', function ($query) use ($order_status) {
                if ($order_status == 'fulfilled') {
                    $query->where('fulfillment_status', 'fulfilled');
                } else {
                    $query->where('fulfillment_status', 'unfulfilled');
                }
            })
            ->whereNotIn('order_status', ['cancelled', 'refunded']);

        if ($channelId) {
            $orderQuery->where('sales_channel_id', $channelId);
        }

        $orders = $orderQuery->orderBy('order_date', 'asc')->get();

        // Build checklist items - group by order, handle bundles with components
        $checklistItems = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->is_bundle_summary) {
                    $product = $item->product;
                    $productMeta = $product ? $product->product_meta : [];
                    $weight = $productMeta['weight'] ?? null;
                    $weightUnit = $productMeta['weight_unit'] ?? 'lbs';
                    $length = $productMeta['length'] ?? null;
                    $width = $productMeta['width'] ?? null;
                    $height = $productMeta['height'] ?? null;
                    $dimensionUnit = $productMeta['dimension_unit'] ?? 'in';
                    $imageUrl = $product ? $product->getImageUrl() : null;

                    $components = $order->items->filter(function ($i) use ($item) {
                        return $i->bundle_product_id == $item->product_id && !$i->is_bundle_summary;
                    });

                    $componentsData = [];
                    foreach ($components as $component) {
                        $compProduct = $component->product;
                        $compMeta = $compProduct ? $compProduct->product_meta : [];

                        $compStocks = [];
                        $compTotalStock = 0;
                        if ($compProduct) {
                            foreach ($compProduct->product_stocks as $stock) {
                                $compStocks[] = [
                                    'warehouse' => $stock->warehouse->name ?? 'N/A',
                                    'rack' => $stock->rack->name ?? 'N/A',
                                    'quantity' => (int) $stock->quantity,
                                ];
                                $compTotalStock += (int) $stock->quantity;
                            }
                        }

                        $componentsData[] = [
                            'product_name' => $component->title ?? ($compProduct->name ?? 'Unknown'),
                            'sku' => $component->sku ?? ($compProduct->sku ?? ''),
                            'weight' => $compMeta['weight'] ?? null,
                            'weight_unit' => $compMeta['weight_unit'] ?? 'lbs',
                            'length' => $compMeta['length'] ?? null,
                            'width' => $compMeta['width'] ?? null,
                            'height' => $compMeta['height'] ?? null,
                            'dimension_unit' => $compMeta['dimension_unit'] ?? 'in',
                            'quantity_ordered' => (int) $component->quantity,
                            'warehouse_stocks' => $compStocks,
                            'total_stock' => $compTotalStock,
                        ];
                    }

                    $checklistItems[] = [
                        'order' => $order,
                        'item' => $item,
                        'ebay_order_id' => $order->ebay_order_id ?: $order->order_number,
                        'image_url' => $imageUrl,
                        'product_name' => $item->bundle_name ?? ($item->title ?? ($product->name ?? 'Unknown Bundle')),
                        'sku' => $item->sku ?? ($product->sku ?? ''),
                        'weight' => $weight,
                        'weight_unit' => $weightUnit,
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                        'dimension_unit' => $dimensionUnit,
                        'sales_channel' => $order->salesChannel->name ?? 'Direct',
                        'quantity_ordered' => (int) $item->quantity,
                        'is_bundle' => true,
                        'components' => $componentsData,
                        'warehouse_stocks' => [],
                        'total_stock' => 0,
                    ];

                } elseif (!$item->bundle_product_id) {
                    $product = $item->product;
                    $productMeta = $product ? $product->product_meta : [];
                    $weight = $productMeta['weight'] ?? null;
                    $weightUnit = $productMeta['weight_unit'] ?? 'lbs';
                    $length = $productMeta['length'] ?? null;
                    $width = $productMeta['width'] ?? null;
                    $height = $productMeta['height'] ?? null;
                    $dimensionUnit = $productMeta['dimension_unit'] ?? 'in';

                    $warehouseStocks = [];
                    $totalStock = 0;
                    if ($product) {
                        foreach ($product->product_stocks as $stock) {
                            $warehouseStocks[] = [
                                'warehouse' => $stock->warehouse->name ?? 'N/A',
                                'rack' => $stock->rack->name ?? 'N/A',
                                'quantity' => (int) $stock->quantity,
                            ];
                            $totalStock += (int) $stock->quantity;
                        }
                    }

                    $imageUrl = $product ? $product->getImageUrl() : null;

                    $checklistItems[] = [
                        'order' => $order,
                        'item' => $item,
                        'ebay_order_id' => $order->ebay_order_id ?: $order->order_number,
                        'image_url' => $imageUrl,
                        'product_name' => $item->title ?? ($product->name ?? 'Unknown Product'),
                        'sku' => $item->sku ?? ($product->sku ?? ''),
                        'weight' => $weight,
                        'weight_unit' => $weightUnit,
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                        'dimension_unit' => $dimensionUnit,
                        'sales_channel' => $order->salesChannel->name ?? 'Direct',
                        'quantity_ordered' => (int) $item->quantity,
                        'is_bundle' => false,
                        'components' => [],
                        'warehouse_stocks' => $warehouseStocks,
                        'total_stock' => $totalStock,
                    ];
                }
            }
        }

        // Summary statistics
        $summary = [
            'total_orders' => $orders->count(),
            'total_items' => count($checklistItems),
            'total_quantity' => collect($checklistItems)->sum('quantity_ordered'),
        ];

        $pdf = Pdf::loadView('reports.shipping-checklist-pdf', compact(
            'checklistItems',
            'summary',
            'dateFrom',
            'dateTo'
        ))
        ->setPaper('a4', 'landscape')
        ->setOption('isRemoteEnabled', true);

        $filename = 'shipping_checklist_' . $dateFrom . '_to_' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Stock Movement
     */
    public function stockMovement()
    {
        return view('reports.stock-movement');
    }

    /**
     * Out of Stock Items Report
     * Shows products with zero or low stock levels
     */
    public function outOfStock(Request $request)
    {
        $categoryId = $request->get('category_id');
        $warehouseId = $request->get('warehouse_id');
        $threshold = (int) $request->get('threshold', 0); // Show items at or below this quantity
        $includeInactive = $request->get('include_inactive', false);

        // Get filter options
        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::where('delete_status', '0')->orderBy('name')->get();

        // Build query for products
        $query = Product::with(['category', 'product_stocks.warehouse', 'product_stocks.rack'])
            ->where('delete_status', '0');

        if (!$includeInactive) {
            $query->where('active_status', '1');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get();

        // Process products to find out of stock items
        $outOfStockItems = [];

        foreach ($products as $product) {
            // Filter stocks by warehouse if specified
            $stocks = $product->product_stocks;
            if ($warehouseId) {
                $stocks = $stocks->where('warehouse_id', $warehouseId);
            }

            $totalStock = $stocks->sum('quantity');

            // Include if stock is at or below threshold
            if ($totalStock <= $threshold) {
                // Get stock breakdown by warehouse
                $warehouseBreakdown = [];
                foreach ($product->product_stocks as $stock) {
                    if (!$warehouseId || $stock->warehouse_id == $warehouseId) {
                        $warehouseBreakdown[] = [
                            'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                            'rack_name' => $stock->rack->name ?? 'N/A',
                            'quantity' => (float) $stock->quantity,
                        ];
                    }
                }

                // Get last order date for this product
                $OrderItem = OrderItem::where('product_id', $product->id)
                    ->whereHas('order', function ($q) {
                        $q->whereIn('payment_status', ['paid']);
                    });

                // ✅ 1. Total Quantity
                $totalSold = (clone $OrderItem)->sum('quantity');

                // ✅ 2. Last Order Item
                $lastOrderItem = (clone $OrderItem)->orderBy('created_at', 'desc')
                    ->first();

                // Get last purchase date for this product
                $lastPurchaseItem = PurchaseItem::where('product_id', $product->id)
                    ->whereHas('purchase', function ($q) {
                        $q->where('delete_status', '0');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                $outOfStockItems[] = [
                    'product_id' => $product->id,
                    'product_image' => $product->getImageUrl(), 
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'category_name' => $product->category->name ?? 'Uncategorized',
                    'total_stock' => $totalStock,
                    'warehouse_breakdown' => $warehouseBreakdown,
                    'last_purchase_date' => $lastPurchaseItem?->created_at,
                    'last_purchase_quantity' => $lastPurchaseItem?->received_quantity, 
                    'last_order_date' => $lastOrderItem?->created_at,
                    'sold_quantity' => $totalSold, 
                    'price' => $product->price,
                    'is_active' => $product->active_status == '1',
                ];
            }
        }

        // Sort by stock level (lowest first), then by name
        $outOfStockCollection = collect($outOfStockItems)
            ->sortBy([['total_stock', 'asc'], ['product_name', 'asc']])
            ->values();

        // Summary statistics
        $summary = [
            'total_out_of_stock' => $outOfStockCollection->where('total_stock', 0)->count(),
            'total_low_stock' => $outOfStockCollection->where('total_stock', '>', 0)->count(),
            'total_items' => $outOfStockCollection->count(),
            'categories_affected' => $outOfStockCollection->pluck('category_name')->unique()->count(),
        ];

        // Paginate the out of stock items
        $perPage = 50;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $outOfStockCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $outOfStockItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $outOfStockCollection->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        // Append query parameters to pagination links
        $outOfStockItems->appends($request->all());

        return view('reports.out-of-stock', compact(
            'outOfStockItems',
            'summary',
            'categories',
            'warehouses',
            'categoryId',
            'warehouseId',
            'threshold',
            'includeInactive'
        ));
    }

    /**
     * Slow Moving Items Report
     * Shows products with low sales velocity relative to stock
     */
    public function slowMovingItems(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-d', strtotime(now()->startOfMonth())));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $categoryId = $request->get('category_id');
        $warehouseId = $request->get('warehouse_id');
        $minStock = (int) $request->get('min_stock', 1); // Minimum stock to be considered
        $maxSales = (int) $request->get('max_sales', 5); // Maximum sales to be considered slow

        // Get filter options
        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::where('delete_status', '0')->orderBy('name')->get();

        // Get products with their stock
        $query = Product::with(['category', 'product_stocks.warehouse'])
            ->where('delete_status', '0')
            ->where('active_status', '1');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get();

        // Get sales data for the period
        $salesData = OrderItem::select(
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('MAX(orders.order_date) as last_sale_date')
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('product_id');

        // Process products to find slow moving items
        $slowMovingItems = [];
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);

        foreach ($products as $product) {
            // Calculate total stock
            $stocks = $product->product_stocks;
            if ($warehouseId) {
                $stocks = $stocks->where('warehouse_id', $warehouseId);
            }
            $totalStock = $stocks->sum('quantity');

            // Skip if below minimum stock threshold
            if ($totalStock < $minStock) {
                continue;
            }

            // Get sales data for this product
            $sales = $salesData->get($product->id);
            $totalSold = $sales?->total_sold ?? 0;
            $orderCount = $sales?->order_count ?? 0;
            $lastSaleDate = $sales?->last_sale_date;

            // Skip if sales exceed maximum threshold
            if ($totalSold > $maxSales) {
                continue;
            }

            // Calculate metrics
            $dailySalesRate = $totalSold / $daysDiff;
            $daysOfStock = $dailySalesRate > 0 ? $totalStock / $dailySalesRate : null;
            $turnoverRate = $totalStock > 0 ? $totalSold / $totalStock : 0;

            // Calculate inventory value
            $avgCost = $stocks->avg('avg_cost') ?? 0;
            $inventoryValue = $totalStock * $avgCost;

            // Get last order date for this product
            $OrderItem = OrderItem::where('product_id', $product->id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('payment_status', ['paid']);
                });

            // ✅ 1. Total Quantity
            $totalSold = (clone $OrderItem)->sum('quantity');

            // ✅ 2. Last Order Item
            $lastOrderItem = (clone $OrderItem)->orderBy('created_at', 'desc')
                ->first();

            // Get last purchase date for this product
            $lastPurchaseItem = PurchaseItem::where('product_id', $product->id)
                ->whereHas('purchase', function ($q) {
                    $q->where('delete_status', '0');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $slowMovingItems[] = [
                'product_id' => $product->id,
                'product_image' => $product->getImageUrl(), 
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'category_name' => $product->category->name ?? 'Uncategorized',
                'total_stock' => $totalStock,
                'total_sold' => $totalSold,
                'order_count' => $orderCount,
                'last_sale_date' => $lastSaleDate,
                'daily_sales_rate' => round($dailySalesRate, 4),
                'days_of_stock' => $daysOfStock ? round($daysOfStock, 0) : null,
                'turnover_rate' => round($turnoverRate, 4),
                'avg_cost' => $avgCost,
                'inventory_value' => round($inventoryValue, 2),
                'price' => $product->price,
                'last_purchase_date' => $lastPurchaseItem?->created_at,
                'last_purchase_quantity' => $lastPurchaseItem?->received_quantity, 
                'last_order_date' => $lastOrderItem?->created_at,
                'sold_quantity' => $totalSold, 
            ];
        }

        // Sort by turnover rate (lowest first - most slow moving)
        $slowMovingCollection = collect($slowMovingItems)
            ->sortBy([['turnover_rate', 'asc'], ['inventory_value', 'desc']])
            ->values();

        // Summary statistics
        $summary = [
            'total_items' => $slowMovingCollection->count(),
            'total_stock_value' => $slowMovingCollection->sum('inventory_value'),
            'zero_sales_items' => $slowMovingCollection->where('total_sold', 0)->count(),
            'avg_turnover_rate' => $slowMovingCollection->avg('turnover_rate'),
            'period_days' => (int) $daysDiff,
        ];

        // Paginate the slow moving items
        $perPage = 50;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $slowMovingCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $slowMovingItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $slowMovingCollection->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        // Append query parameters to pagination links
        $slowMovingItems->appends($request->all());

        return view('reports.slow-moving-items', compact(
            'slowMovingItems',
            'summary',
            'categories',
            'warehouses',
            'dateFrom',
            'dateTo',
            'categoryId',
            'warehouseId',
            'minStock',
            'maxSales'
        ));
    }

    /**
     * Frequently Ordered Items Report
     * Shows products with highest order frequency
     */
    public function frequentlyOrderedItems(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $categoryId = $request->get('category_id');
        $channelId = $request->get('channel_id');
        $limit = (int) $request->get('limit', 50);
        $groupBy = $request->get('group_by', 'product'); // product, category, channel

        // Get filter options
        $categories = Category::orderBy('name')->get();
        $salesChannels = SalesChannel::where('delete_status', '0')->orderBy('name')->get();

        // Build base query for order items
        $query = OrderItem::select(
                'order_items.product_id',
                'order_items.sku',
                'order_items.title',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('AVG(order_items.unit_price) as avg_unit_price'),
                DB::raw('MIN(orders.order_date) as first_order_date'),
                DB::raw('MAX(orders.order_date) as last_order_date')
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.order_status', ['cancelled', 'refunded'])
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->whereNull('order_items.bundle_product_id') // Exclude bundle components
            ->where('order_items.is_bundle_summary', false); // Exclude bundle summaries too if you want individual products only

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($categoryId) {
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $query->groupBy('order_items.product_id', 'order_items.sku', 'order_items.title');

        $orderItemsData = $query->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();

        // Get product details
        $productIds = $orderItemsData->pluck('product_id')->filter()->unique();
        $products = Product::with(['category', 'product_stocks'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        // Build report data
        $frequentItems = [];
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);

        foreach ($orderItemsData as $item) {
            $product = $products->get($item->product_id);
            $currentStock = $product ? $product->product_stocks->sum('quantity') : 0;

            // Calculate metrics
            $dailySalesRate = $item->total_quantity / $daysDiff;
            $daysOfStock = $dailySalesRate > 0 ? $currentStock / $dailySalesRate : null;

            // Get last order date for this product
            $OrderItem = OrderItem::where('product_id', $item->product_id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('payment_status', ['paid']);
                });

            // ✅ 1. Total Quantity
            $totalSold = (clone $OrderItem)->sum('quantity');

            // ✅ 2. Last Order Item
            $lastOrderItem = (clone $OrderItem)->orderBy('created_at', 'desc')
                ->first();

            // Get last purchase date for this product
            $lastPurchaseItem = PurchaseItem::where('product_id', $item->product_id)
                ->whereHas('purchase', function ($q) {
                    $q->where('delete_status', '0');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $frequentItems[] = [
                'product_id' => $item->product_id,
                'product_image' => $item->product?->getImageUrl(), 
                'product_name' => $product?->name ?? $item->title ?? 'Unknown Product',
                'product_sku' => $product?->sku ?? $item->sku ?? '',
                'category_name' => $product?->category?->name ?? 'Uncategorized',
                'total_quantity' => (int) $item->total_quantity,
                'order_count' => (int) $item->order_count,
                'total_revenue' => round((float) $item->total_revenue, 2),
                'avg_unit_price' => round((float) $item->avg_unit_price, 2),
                'first_order_date' => $item->first_order_date,
                'last_order_date' => $item->last_order_date,
                'current_stock' => $currentStock,
                'daily_sales_rate' => round($dailySalesRate, 2),
                'days_of_stock' => $daysOfStock ? round($daysOfStock, 0) : null,
                'avg_per_order' => $item->order_count > 0 ? round($item->total_quantity / $item->order_count, 2) : 0,
                'last_purchase_date' => $lastPurchaseItem?->created_at,
                'last_purchase_quantity' => $lastPurchaseItem?->received_quantity,  
            ];
        }

        $frequentItemsCollection = collect($frequentItems);

        // Group data if requested
        $groupedData = collect();
        if ($groupBy === 'category') {
            $groupedData = $this->groupFrequentItemsByCategory($frequentItemsCollection);
        } elseif ($groupBy === 'channel') {
            // Re-query with channel grouping
            $groupedData = $this->getFrequentItemsByChannel($dateFrom, $dateTo, $categoryId, $limit);
        }

        // Summary statistics
        $summary = [
            'total_items' => $frequentItemsCollection->count(),
            'total_quantity_sold' => $frequentItemsCollection->sum('total_quantity'),
            'total_revenue' => $frequentItemsCollection->sum('total_revenue'),
            'total_orders' => Order::where('payment_status', 'paid')
                ->whereNotIn('order_status', ['cancelled', 'refunded'])
                ->whereDate('order_date', '>=', $dateFrom)
                ->whereDate('order_date', '<=', $dateTo)
                ->when($channelId, fn($q) => $q->where('sales_channel_id', $channelId))
                ->count(),
            'period_days' => (int) $daysDiff,
            'avg_daily_items' => round($frequentItemsCollection->sum('total_quantity') / $daysDiff, 2),
        ];

        // Paginate the frequent items
        $perPage = 50;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $frequentItemsCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $frequentItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $frequentItemsCollection->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        // Append query parameters to pagination links
        $frequentItems->appends($request->all());

        return view('reports.frequently-ordered-items', compact(
            'frequentItems',
            'groupedData',
            'summary',
            'categories',
            'salesChannels',
            'dateFrom',
            'dateTo',
            'categoryId',
            'channelId',
            'limit',
            'groupBy'
        ));
    }

    /**
     * Group frequent items by category
     */
    protected function groupFrequentItemsByCategory($items)
    {
        $grouped = [];

        foreach ($items as $item) {
            $categoryName = $item['category_name'];

            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'name' => $categoryName,
                    'total_quantity' => 0,
                    'total_revenue' => 0,
                    'order_count' => 0,
                    'item_count' => 0,
                    'items' => [],
                ];
            }

            $grouped[$categoryName]['total_quantity'] += $item['total_quantity'];
            $grouped[$categoryName]['total_revenue'] += $item['total_revenue'];
            $grouped[$categoryName]['order_count'] += $item['order_count'];
            $grouped[$categoryName]['item_count']++;
            $grouped[$categoryName]['items'][] = $item;
        }

        return collect($grouped)->sortByDesc('total_quantity')->values();
    }

    /**
     * Get frequent items grouped by sales channel
     */
    protected function getFrequentItemsByChannel($dateFrom, $dateTo, $categoryId, $limit)
    {
        $query = OrderItem::select(
                'orders.sales_channel_id',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.product_id) as unique_products')
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.order_status', ['cancelled', 'refunded'])
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->whereNull('order_items.bundle_product_id');

        if ($categoryId) {
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $channelData = $query->groupBy('orders.sales_channel_id')
            ->orderBy('total_quantity', 'desc')
            ->get();

        // Get channel names
        $channelIds = $channelData->pluck('sales_channel_id')->filter();
        $channels = SalesChannel::whereIn('id', $channelIds)->get()->keyBy('id');

        $result = [];
        foreach ($channelData as $data) {
            $channel = $channels->get($data->sales_channel_id);
            $result[] = [
                'name' => $channel?->name ?? 'Direct Sales',
                'total_quantity' => (int) $data->total_quantity,
                'order_count' => (int) $data->order_count,
                'total_revenue' => round((float) $data->total_revenue, 2),
                'unique_products' => (int) $data->unique_products,
            ];
        }

        return collect($result);
    }

    /**
     * Export Slow Moving Items Report to Excel
     */
    public function exportSlowMovingItems(Request $request)
    {
        // Get the visible columns from request
        $visibleColumns = $request->input('columns', []);

        if (empty($visibleColumns)) {
            $visibleColumns = ['id', 'product', 'sku', 'category', 'last_purchase_quantity', 'last_purchase', 'last_order', 'sold_quantity', 'stock', 'orders', 'daily_rate', 'days_of_stock', 'turnover', 'stock_value'];
        }

        // Define all available columns with their mappings
        $columns = [
            'id' => ['label' => '#', 'field' => '#'],
            'image' => ['label' => 'Image', 'field' => 'product_image'],
            'product' => ['label' => 'Product Name', 'field' => 'product_name'],
            'sku' => ['label' => 'SKU', 'field' => 'product_sku'],
            'category' => ['label' => 'Category', 'field' => 'category_name'],
            'last_purchase_quantity' => ['label' => 'Last Purchase Qty', 'field' => 'last_purchase_quantity', 'format' => 'number'],
            'last_purchase' => ['label' => 'Last Purchase Date', 'field' => 'last_purchase_date', 'format' => 'date'],
            'last_order' => ['label' => 'Last Sale Date', 'field' => 'last_sale_date', 'format' => 'date'],
            'sold_quantity' => ['label' => 'Sold Quantity', 'field' => 'total_sold', 'format' => 'number'],
            'stock' => ['label' => 'Current Stock', 'field' => 'total_stock', 'format' => 'decimal'],
            'orders' => ['label' => 'Order Count', 'field' => 'order_count', 'format' => 'number'],
            'daily_rate' => ['label' => 'Daily Sales Rate', 'field' => 'daily_sales_rate', 'format' => 'decimal'],
            'days_of_stock' => ['label' => 'Days of Stock', 'field' => 'days_of_stock', 'format' => 'number'],
            'turnover' => ['label' => 'Turnover Rate', 'field' => 'turnover_rate', 'format' => 'decimal'],
            'stock_value' => ['label' => 'Stock Value', 'field' => 'inventory_value', 'format' => 'currency'],
        ];

        // Get report data using the same logic as the view
        $dateFrom = $request->input('date_from', now()->subDays(90)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $categoryId = $request->input('category_id');
        $warehouseId = $request->input('warehouse_id');
        $minStock = $request->input('min_stock', 1);
        $maxSales = $request->input('max_sales', 5);

        $data = $this->getSlowMovingItemsData($dateFrom, $dateTo, $categoryId, $warehouseId, $minStock, $maxSales);

        $export = new ReportExport($data->toArray(), $columns, $visibleColumns, 'Slow Moving Items Report');

        return Excel::download($export, 'slow-moving-items-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export Out of Stock Report to Excel
     */
    public function exportOutOfStock(Request $request)
    {
        $visibleColumns = $request->input('columns', []);

        if (empty($visibleColumns)) {
            $visibleColumns = ['id', 'product', 'sku', 'category', 'last_purchase_quantity', 'last_purchase', 'last_order', 'sold_quantity', 'stock', 'price', 'status'];
        }

        $columns = [
            'id' => ['label' => '#', 'field' => '#'],
            'image' => ['label' => 'Image', 'field' => 'product_image'],
            'product' => ['label' => 'Product Name', 'field' => 'product_name'],
            'sku' => ['label' => 'SKU', 'field' => 'product_sku'],
            'category' => ['label' => 'Category', 'field' => 'category_name'],
            'last_purchase_quantity' => ['label' => 'Last Purchase Qty', 'field' => 'last_purchase_quantity', 'format' => 'number'],
            'last_purchase' => ['label' => 'Last Purchase Date', 'field' => 'last_purchase_date', 'format' => 'date'],
            'last_order' => ['label' => 'Last Order Date', 'field' => 'last_order_date', 'format' => 'date'],
            'sold_quantity' => ['label' => 'Sold Quantity', 'field' => 'sold_quantity', 'format' => 'number'],
            'stock' => ['label' => 'Total Stock', 'field' => 'total_stock', 'format' => 'decimal'],
            'warehouse' => ['label' => 'Warehouse Details', 'field' => 'warehouse_details'],
            'price' => ['label' => 'Price', 'field' => 'price', 'format' => 'currency'],
            'status' => ['label' => 'Status', 'field' => 'stock_status'],
        ];

        $categoryId = $request->input('category_id');
        $warehouseId = $request->input('warehouse_id');
        $threshold = $request->input('threshold', 0);
        $includeInactive = $request->boolean('include_inactive', false);

        $data = $this->getOutOfStockData($categoryId, $warehouseId, $threshold, $includeInactive);

        $export = new ReportExport($data->toArray(), $columns, $visibleColumns, 'Out of Stock Report');

        return Excel::download($export, 'out-of-stock-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export Frequently Ordered Items Report to Excel
     */
    public function exportFrequentlyOrderedItems(Request $request)
    {
        $visibleColumns = $request->input('columns', []);

        if (empty($visibleColumns)) {
            $visibleColumns = ['id', 'product', 'sku', 'category', 'last_purchase_quantity', 'last_purchase', 'last_order', 'sold_quantity', 'stock', 'orders', 'revenue', 'average_price', 'average_order', 'days_of_stock'];
        }

        $columns = [
            'id' => ['label' => '#', 'field' => '#'],
            'image' => ['label' => 'Image', 'field' => 'product_image'],
            'product' => ['label' => 'Product Name', 'field' => 'product_name'],
            'sku' => ['label' => 'SKU', 'field' => 'product_sku'],
            'category' => ['label' => 'Category', 'field' => 'category_name'],
            'last_purchase_quantity' => ['label' => 'Last Purchase Qty', 'field' => 'last_purchase_quantity', 'format' => 'number'],
            'last_purchase' => ['label' => 'Last Purchase Date', 'field' => 'last_purchase_date', 'format' => 'date'],
            'last_order' => ['label' => 'Last Order Date', 'field' => 'last_order_date', 'format' => 'date'],
            'sold_quantity' => ['label' => 'Quantity Sold', 'field' => 'total_quantity', 'format' => 'number'],
            'stock' => ['label' => 'Current Stock', 'field' => 'current_stock', 'format' => 'decimal'],
            'orders' => ['label' => 'Order Count', 'field' => 'order_count', 'format' => 'number'],
            'revenue' => ['label' => 'Total Revenue', 'field' => 'total_revenue', 'format' => 'currency'],
            'average_price' => ['label' => 'Avg Unit Price', 'field' => 'avg_unit_price', 'format' => 'currency'],
            'average_order' => ['label' => 'Avg Per Order', 'field' => 'avg_per_order', 'format' => 'decimal'],
            'days_of_stock' => ['label' => 'Days of Stock', 'field' => 'days_of_stock', 'format' => 'number'],
        ];

        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $categoryId = $request->input('category_id');
        $channelId = $request->input('channel_id');
        $limit = $request->input('limit', 50);

        $data = $this->getFrequentlyOrderedItemsData($dateFrom, $dateTo, $categoryId, $channelId, $limit);

        $export = new ReportExport($data->toArray(), $columns, $visibleColumns, 'Frequently Ordered Items Report');

        return Excel::download($export, 'frequently-ordered-items-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Get slow moving items data (extracted for reuse - matches slowMovingItems view method)
     */
    protected function getSlowMovingItemsData($dateFrom, $dateTo, $categoryId, $warehouseId, $minStock, $maxSales)
    {
        // Get products with their stock
        $query = Product::with(['category', 'product_stocks.warehouse'])
            ->where('delete_status', '0')
            ->where('active_status', '1');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get();

        // Get sales data for the period
        $salesData = OrderItem::select(
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('MAX(orders.order_date) as last_sale_date')
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('product_id');

        $slowMovingItems = [];
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);

        foreach ($products as $product) {
            $stocks = $product->product_stocks;
            if ($warehouseId) {
                $stocks = $stocks->where('warehouse_id', $warehouseId);
            }
            $totalStock = $stocks->sum('quantity');

            if ($totalStock < $minStock) {
                continue;
            }

            $sales = $salesData->get($product->id);
            $totalSold = $sales?->total_sold ?? 0;
            $orderCount = $sales?->order_count ?? 0;
            $lastSaleDate = $sales?->last_sale_date;

            if ($totalSold > $maxSales) {
                continue;
            }

            $dailySalesRate = $totalSold / $daysDiff;
            $daysOfStock = $dailySalesRate > 0 ? $totalStock / $dailySalesRate : null;
            $turnoverRate = $totalStock > 0 ? $totalSold / $totalStock : 0;

            $avgCost = $stocks->avg('avg_cost') ?? 0;
            $inventoryValue = $totalStock * $avgCost;

            // Get last order item
            $lastOrderItem = OrderItem::where('product_id', $product->id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('payment_status', ['paid']);
                })
                ->orderBy('created_at', 'desc')
                ->first();

            // Get last purchase item
            $lastPurchaseItem = PurchaseItem::where('product_id', $product->id)
                ->whereHas('purchase', function ($q) {
                    $q->where('delete_status', '0');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $slowMovingItems[] = [
                'product_id' => $product->id,
                'product_image' => $product->getImageUrl(),
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'category_name' => $product->category->name ?? 'Uncategorized',
                'total_stock' => $totalStock,
                'total_sold' => $totalSold,
                'order_count' => $orderCount,
                'last_sale_date' => $lastSaleDate,
                'daily_sales_rate' => round($dailySalesRate, 4),
                'days_of_stock' => $daysOfStock ? round($daysOfStock, 0) : null,
                'turnover_rate' => round($turnoverRate, 4),
                'avg_cost' => $avgCost,
                'inventory_value' => round($inventoryValue, 2),
                'price' => $product->price,
                'last_purchase_date' => $lastPurchaseItem?->created_at,
                'last_purchase_quantity' => $lastPurchaseItem?->received_quantity ?? 0,
                'last_order_date' => $lastOrderItem?->created_at,
                'sold_quantity' => $totalSold,
            ];
        }

        return collect($slowMovingItems)
            ->sortBy([['turnover_rate', 'asc'], ['inventory_value', 'desc']])
            ->values();
    }

    /**
     * Get out of stock data (extracted for reuse - matches outOfStock view method)
     */
    protected function getOutOfStockData($categoryId, $warehouseId, $threshold, $includeInactive)
    {
        $query = Product::with(['category', 'product_stocks.warehouse', 'product_stocks.rack'])
            ->where('delete_status', '0');

        if (!$includeInactive) {
            $query->where('active_status', '1');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get();
        $productIds = $products->pluck('id')->toArray();

        // Eager load order data - total sold per product
        $orderData = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('product_id', $productIds)
            ->whereHas('order', function ($q) {
                $q->whereIn('payment_status', ['paid']);
            })
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Eager load last order item per product - simpler approach
        $lastOrderItems = collect();
        if (!empty($productIds)) {
            $rawItems = DB::select("
                SELECT oi.*
                FROM order_items oi
                INNER JOIN (
                    SELECT product_id, MAX(created_at) as max_date
                    FROM order_items
                    WHERE product_id IN (" . implode(',', $productIds) . ")
                    GROUP BY product_id
                ) latest ON oi.product_id = latest.product_id AND oi.created_at = latest.max_date
                WHERE EXISTS (
                    SELECT 1 FROM orders o
                    WHERE oi.order_id = o.id
                    AND o.payment_status IN ('paid')
                )
            ");
            foreach ($rawItems as $item) {
                $lastOrderItems->put($item->product_id, $item);
            }
        }

        // Eager load last purchase item per product - simpler approach
        $lastPurchaseItems = collect();
        if (!empty($productIds)) {
            $rawItems = DB::select("
                SELECT pi.*
                FROM purchase_items pi
                INNER JOIN (
                    SELECT product_id, MAX(created_at) as max_date
                    FROM purchase_items
                    WHERE product_id IN (" . implode(',', $productIds) . ")
                    GROUP BY product_id
                ) latest ON pi.product_id = latest.product_id AND pi.created_at = latest.max_date
                WHERE EXISTS (
                    SELECT 1 FROM purchases p
                    WHERE pi.purchase_id = p.id
                    AND p.delete_status = '0'
                )
            ");
            foreach ($rawItems as $item) {
                $lastPurchaseItems->put($item->product_id, $item);
            }
        }

        $outOfStockItems = [];

        foreach ($products as $product) {
            $stocks = $product->product_stocks;
            if ($warehouseId) {
                $stocks = $stocks->where('warehouse_id', $warehouseId);
            }

            $totalStock = $stocks->sum('quantity');

            if ($totalStock <= $threshold) {
                // Get stock breakdown by warehouse
                $warehouseBreakdown = [];
                foreach ($product->product_stocks as $stock) {
                    if (!$warehouseId || $stock->warehouse_id == $warehouseId) {
                        $warehouseBreakdown[] = [
                            'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                            'rack_name' => $stock->rack->name ?? 'N/A',
                            'quantity' => (float) $stock->quantity,
                        ];
                    }
                }

                // Use pre-loaded data
                $totalSold = $orderData->get($product->id)?->total_sold ?? 0;
                $lastOrderItem = $lastOrderItems->get($product->id);
                $lastPurchaseItem = $lastPurchaseItems->get($product->id);

                $warehouseDetails = collect($warehouseBreakdown)->map(function ($wh) {
                    return $wh['warehouse_name'] . ': ' . $wh['quantity'];
                })->implode(', ');

                $outOfStockItems[] = [
                    'product_id' => $product->id,
                    'product_image' => $product->getImageUrl(),
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'category_name' => $product->category->name ?? 'Uncategorized',
                    'total_stock' => $totalStock,
                    'warehouse_breakdown' => $warehouseBreakdown,
                    'warehouse_details' => $warehouseDetails,
                    'last_purchase_date' => $lastPurchaseItem?->created_at,
                    'last_purchase_quantity' => $lastPurchaseItem?->received_quantity ?? 0,
                    'last_order_date' => $lastOrderItem?->created_at,
                    'sold_quantity' => $totalSold,
                    'price' => $product->price,
                    'is_active' => $product->active_status == '1',
                    'stock_status' => $totalStock == 0 ? 'Out of Stock' : 'Low Stock',
                ];
            }
        }

        return collect($outOfStockItems)
            ->sortBy([['total_stock', 'asc'], ['product_name', 'asc']])
            ->values();
    }

    /**
     * Get frequently ordered items data (extracted for reuse - matches frequentlyOrderedItems view method)
     */
    protected function getFrequentlyOrderedItemsData($dateFrom, $dateTo, $categoryId, $channelId, $limit)
    {
        $query = OrderItem::select(
                'order_items.product_id',
                'order_items.sku',
                'order_items.title',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('AVG(order_items.unit_price) as avg_unit_price'),
                DB::raw('MIN(orders.order_date) as first_order_date'),
                DB::raw('MAX(orders.order_date) as last_order_date')
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.order_status', ['cancelled', 'refunded'])
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->whereNull('order_items.bundle_product_id')
            ->where('order_items.is_bundle_summary', false);

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($categoryId) {
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $query->groupBy('order_items.product_id', 'order_items.sku', 'order_items.title');

        $orderItemsData = $query->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();

        // Get product details
        $productIds = $orderItemsData->pluck('product_id')->filter()->unique();
        $products = Product::with(['category', 'product_stocks'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $frequentItems = [];
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);

        foreach ($orderItemsData as $item) {
            $product = $products->get($item->product_id);
            $currentStock = $product ? $product->product_stocks->sum('quantity') : 0;

            $dailySalesRate = $item->total_quantity / $daysDiff;
            $daysOfStock = $dailySalesRate > 0 ? $currentStock / $dailySalesRate : null;

            // Get last order item
            $lastOrderItem = OrderItem::where('product_id', $item->product_id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('payment_status', ['paid']);
                })
                ->orderBy('created_at', 'desc')
                ->first();

            // Get last purchase item
            $lastPurchaseItem = PurchaseItem::where('product_id', $item->product_id)
                ->whereHas('purchase', function ($q) {
                    $q->where('delete_status', '0');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $frequentItems[] = [
                'product_id' => $item->product_id,
                'product_image' => $product?->getImageUrl(),
                'product_name' => $product?->name ?? $item->title ?? 'Unknown Product',
                'product_sku' => $product?->sku ?? $item->sku ?? '',
                'category_name' => $product?->category?->name ?? 'Uncategorized',
                'total_quantity' => (int) $item->total_quantity,
                'order_count' => (int) $item->order_count,
                'total_revenue' => round((float) $item->total_revenue, 2),
                'avg_unit_price' => round((float) $item->avg_unit_price, 2),
                'first_order_date' => $item->first_order_date,
                'last_order_date' => $item->last_order_date,
                'current_stock' => $currentStock,
                'daily_sales_rate' => round($dailySalesRate, 2),
                'days_of_stock' => $daysOfStock ? round($daysOfStock, 0) : null,
                'avg_per_order' => $item->order_count > 0 ? round($item->total_quantity / $item->order_count, 2) : 0,
                'last_purchase_date' => $lastPurchaseItem?->created_at,
                'last_purchase_quantity' => $lastPurchaseItem?->received_quantity ?? 0,
            ];
        }

        return collect($frequentItems);
    }

    /**
     * COGS (Cost of Goods Sold) Report
     */
    public function cogsReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product'); // product, channel, date, order

        // Get filter options
        $salesChannels = SalesChannel::where('delete_status', '0')->orderBy('name')->get();
        $products = Product::where('delete_status', '0')->orderBy('name')->get();

        // Build order items query
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where(function ($q) {
                // Items with COGS recorded, OR items belonging to a cancelled/refunded
                // order (inventory may have been restored, wiping inventory_updated) -
                // keep these visible so the report doesn't silently drop refunded sales.
                $q->where('order_items.inventory_updated', true)
                    ->orWhereIn('orders.order_status', ['cancelled', 'refunded'])
                    ->orWhere('orders.payment_status', 'refunded');
            })
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary - refunded/cancelled orders, and bundle component lines
        // (already rolled into the bundle summary line's cost), contribute $0 to totals
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) ? 0 : $item->quantity;
            }),
            'total_cogs' => $orderItems->sum(function ($item) {
                return ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
            'total_revenue' => $orderItems->sum(function ($item) {
                return $this->isOrderItemRefunded($item) ? 0 : $item->total_price;
            }),
            'items_with_cogs' => $orderItems->filter(function ($item) {
                return !$this->isOrderItemRefunded($item) && !$this->isBundleComponentItem($item) && $item->cost_at_sale > 0;
            })->count(),
            'items_without_cogs' => $orderItems->filter(function ($item) {
                return !$this->isOrderItemRefunded($item) && !$this->isBundleComponentItem($item) && $item->cost_at_sale <= 0;
            })->count(),
            'refunded_items_count' => $orderItems->filter(function ($item) {
                return $this->isOrderItemRefunded($item);
            })->sum('quantity'),
            'refunded_orders_count' => $orderItems->filter(function ($item) {
                return $this->isOrderItemRefunded($item);
            })->pluck('order_id')->unique()->count(),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;

        // Build grouped report data
        $reportDataCollection = collect();

        if ($groupBy === 'product') {
            $reportDataCollection = $this->groupCogsByProduct($orderItems);
        } elseif ($groupBy === 'channel') {
            $reportDataCollection = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportDataCollection = $this->groupCogsByDate($orderItems);
        } elseif ($groupBy === 'order') {
            $reportDataCollection = $this->groupCogsByOrder($orderItems);
        }

        // Paginate grouped data with custom page name
        $perPage = 50;
        $currentPage = request()->get('grouped_page', 1);
        $reportData = new \Illuminate\Pagination\LengthAwarePaginator(
            $reportDataCollection->forPage($currentPage, $perPage),
            $reportDataCollection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'grouped_page']
        );

        // Paginated order items for details with custom page name
        $paginatedQuery = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where(function ($q) {
                $q->where('order_items.inventory_updated', true)
                    ->orWhereIn('orders.order_status', ['cancelled', 'refunded'])
                    ->orWhere('orders.payment_status', 'refunded');
            })
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $paginatedQuery->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $paginatedQuery->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $paginatedQuery->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $paginatedQuery->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $paginatedItems = $paginatedQuery->with(['order.salesChannel', 'product'])
            ->orderBy('orders.order_date', 'desc')
            ->paginate(50, ['*'], 'items_page');

        return view('reports.cogs-report', compact(
            'paginatedItems',
            'reportData',
            'summary',
            'salesChannels',
            'products',
            'dateFrom',
            'dateTo',
            'channelId',
            'orderStatus',
            'productId',
            'sku',
            'groupBy'
        ));
    }

    /**
     * Whether an order item belongs to a cancelled/refunded order and should
     * contribute $0 to COGS/revenue/profit totals.
     */
    protected function isOrderItemRefunded($item): bool
    {
        return in_array($item->order_status, ['cancelled', 'refunded'])
            || $item->payment_status === 'refunded';
    }

    /**
     * Bundle component lines carry their own cost_at_sale, but that cost is
     * already rolled up into the bundle summary line's cost_at_sale (see
     * OrderItem::updateInventory). Counting components too would double the
     * COGS for any order containing a bundle.
     */
    protected function isBundleComponentItem($item): bool
    {
        return !is_null($item->bundle_product_id) && !$item->is_bundle_summary;
    }

    /**
     * Group COGS by product
     */
    protected function groupCogsByProduct($orderItems)
    {
        $grouped = [];

        foreach ($orderItems as $item) {
            $productId = $item->product_id ?? $item->sku;
            $productName = $item->product->name ?? $item->title;
            $productSku = $item->sku;

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'name' => $productName,
                    'sku' => $productSku,
                    'quantity_sold' => 0,
                    'total_cogs' => 0,
                    'total_revenue' => 0,
                    'avg_cost' => 0,
                    'avg_price' => 0,
                ];
            }

            if ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) {
                continue; // Refunded/cancelled, or a bundle component whose cost is already rolled into the summary line - $0 contribution
            }

            $itemCogs = ($item->cost_at_sale ?? 0) * $item->quantity;

            $grouped[$productId]['quantity_sold'] += (int) $item->quantity;
            $grouped[$productId]['total_cogs'] += $itemCogs;
            $grouped[$productId]['total_revenue'] += (float) $item->total_price;
        }

        // Calculate averages and margins
        foreach ($grouped as &$product) {
            $product['avg_cost'] = $product['quantity_sold'] > 0
                ? $product['total_cogs'] / $product['quantity_sold']
                : 0;
            $product['avg_price'] = $product['quantity_sold'] > 0
                ? $product['total_revenue'] / $product['quantity_sold']
                : 0;
            $product['gross_profit'] = $product['total_revenue'] - $product['total_cogs'];
            $product['gross_margin'] = $product['total_revenue'] > 0
                ? ($product['gross_profit'] / $product['total_revenue']) * 100
                : 0;
        }

        return collect($grouped)->sortByDesc('total_cogs')->values();
    }

    /**
     * Group COGS by channel
     */
    protected function groupCogsByChannel($orderItems)
    {
        $grouped = [];

        foreach ($orderItems as $item) {
            $channelName = $item->order->salesChannel->name ?? 'Direct Sales';
            $channelId = $item->order->sales_channel_id ?? 0;

            if (!isset($grouped[$channelId])) {
                $grouped[$channelId] = [
                    'name' => $channelName,
                    'items_sold' => 0,
                    'total_cogs' => 0,
                    'total_revenue' => 0,
                ];
            }

            if ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) {
                continue; // Refunded/cancelled, or a bundle component whose cost is already rolled into the summary line - $0 contribution
            }

            $itemCogs = ($item->cost_at_sale ?? 0) * $item->quantity;

            $grouped[$channelId]['items_sold'] += (int) $item->quantity;
            $grouped[$channelId]['total_cogs'] += $itemCogs;
            $grouped[$channelId]['total_revenue'] += (float) $item->total_price;
        }

        // Calculate margins
        foreach ($grouped as &$channel) {
            $channel['gross_profit'] = $channel['total_revenue'] - $channel['total_cogs'];
            $channel['gross_margin'] = $channel['total_revenue'] > 0
                ? ($channel['gross_profit'] / $channel['total_revenue']) * 100
                : 0;
        }

        return collect($grouped)->sortByDesc('total_cogs')->values();
    }

    /**
     * Group COGS by date
     */
    protected function groupCogsByDate($orderItems)
    {
        $grouped = [];

        foreach ($orderItems as $item) {
            $date = $item->order->order_date ? $item->order->order_date->format('Y-m-d') : 'Unknown';
            $formattedDate = $item->order->order_date ? $item->order->order_date->format('M d, Y') : 'Unknown';

            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'formatted_date' => $formattedDate,
                    'items_sold' => 0,
                    'total_cogs' => 0,
                    'total_revenue' => 0,
                ];
            }

            if ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) {
                continue; // Refunded/cancelled, or a bundle component whose cost is already rolled into the summary line - $0 contribution
            }

            $itemCogs = ($item->cost_at_sale ?? 0) * $item->quantity;

            $grouped[$date]['items_sold'] += (int) $item->quantity;
            $grouped[$date]['total_cogs'] += $itemCogs;
            $grouped[$date]['total_revenue'] += (float) $item->total_price;
        }

        // Calculate margins
        foreach ($grouped as &$day) {
            $day['gross_profit'] = $day['total_revenue'] - $day['total_cogs'];
            $day['gross_margin'] = $day['total_revenue'] > 0
                ? ($day['gross_profit'] / $day['total_revenue']) * 100
                : 0;
        }

        return collect($grouped)->sortByDesc('date')->values();
    }

    /**
     * Group COGS by order
     */
    protected function groupCogsByOrder($orderItems)
    {
        $grouped = [];

        foreach ($orderItems as $item) {
            $orderId = $item->order_id;
            $orderNumber = $item->order->order_number;
            $orderDate = $item->order->order_date;

            $isRefunded = $this->isOrderItemRefunded($item);
            $isBundleComponent = $this->isBundleComponentItem($item);

            if (!isset($grouped[$orderId])) {
                $grouped[$orderId] = [
                    'order_number' => $orderNumber,
                    'order_date' => $orderDate,
                    'formatted_date' => $orderDate ? $orderDate->format('M d, Y') : 'Unknown',
                    'channel' => $item->order->salesChannel->name ?? 'Direct Sales',
                    'items_count' => 0,
                    'total_cogs' => 0,
                    'total_revenue' => 0,
                    'is_refunded' => $isRefunded,
                ];
            }

            // Items count stays informational even for refunded orders; $ contribution is zeroed.
            // Bundle components are skipped entirely - their cost/qty is already represented by the summary line.
            if ($isBundleComponent) {
                continue;
            }

            $grouped[$orderId]['items_count'] += (int) $item->quantity;

            if (!$isRefunded) {
                $itemCogs = ($item->cost_at_sale ?? 0) * $item->quantity;
                $grouped[$orderId]['total_cogs'] += $itemCogs;
                $grouped[$orderId]['total_revenue'] += (float) $item->total_price;
            }
        }

        // Calculate margins
        foreach ($grouped as &$order) {
            $order['gross_profit'] = $order['total_revenue'] - $order['total_cogs'];
            $order['gross_margin'] = $order['total_revenue'] > 0
                ? ($order['gross_profit'] / $order['total_revenue']) * 100
                : 0;
        }

        return collect($grouped)->sortByDesc('order_date')->values();
    }

    /**
     * Export COGS Report to Excel
     */
    public function exportCogsReport(Request $request)
    {
        // Get same filters
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product');

        // Build query
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where(function ($q) {
                $q->where('order_items.inventory_updated', true)
                    ->orWhereIn('orders.order_status', ['cancelled', 'refunded'])
                    ->orWhere('orders.payment_status', 'refunded');
            })
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary - refunded/cancelled orders, and bundle component lines
        // (already rolled into the bundle summary line's cost), contribute $0 to totals
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) ? 0 : $item->quantity;
            }),
            'total_cogs' => $orderItems->sum(function ($item) {
                return ($this->isOrderItemRefunded($item) || $this->isBundleComponentItem($item)) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
            'total_revenue' => $orderItems->sum(function ($item) {
                return $this->isOrderItemRefunded($item) ? 0 : $item->total_price;
            }),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;

        // Group data
        if ($groupBy === 'product') {
            $reportData = $this->groupCogsByProduct($orderItems);
        } elseif ($groupBy === 'channel') {
            $reportData = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportData = $this->groupCogsByDate($orderItems);
        } else {
            $reportData = $this->groupCogsByOrder($orderItems);
        }

        $export = new \App\Exports\CogsReportExport($reportData->toArray(), $summary, $groupBy);

        return Excel::download($export, 'cogs-report-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Gross Profit Report
     */
    public function grossProfitReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product'); // product, channel, date, order

        // Get filter options
        $salesChannels = SalesChannel::where('delete_status', '0')->orderBy('name')->get();
        $products = Product::where('delete_status', '0')->orderBy('name')->get();

        // Build order items query - only paid orders for profit calculation
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where('orders.payment_status', 'paid')
            ->where('order_items.inventory_updated', true)
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : $item->quantity;
            }),
            'total_revenue' => $orderItems->sum('total_price'),
            'total_cogs' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;
        $summary['avg_profit_per_item'] = $summary['total_items_sold'] > 0
            ? $summary['gross_profit'] / $summary['total_items_sold']
            : 0;

        // Build grouped report data
        $reportDataCollection = collect();

        if ($groupBy === 'product') {
            $reportDataCollection = $this->groupCogsByProduct($orderItems); // Reuse COGS grouping
        } elseif ($groupBy === 'channel') {
            $reportDataCollection = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportDataCollection = $this->groupCogsByDate($orderItems);
        } elseif ($groupBy === 'order') {
            $reportDataCollection = $this->groupCogsByOrder($orderItems);
        }

        // Sort by gross profit instead of COGS
        $reportDataCollection = $reportDataCollection->sortByDesc(function ($item) {
            return $item['gross_profit'] ?? 0;
        })->values();

        // Paginate grouped data with custom page name
        $perPage = 50;
        $currentPage = request()->get('grouped_page', 1);
        $reportData = new \Illuminate\Pagination\LengthAwarePaginator(
            $reportDataCollection->forPage($currentPage, $perPage),
            $reportDataCollection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'grouped_page']
        );

        // Paginated order items for details with custom page name
        $paginatedQuery = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where('orders.payment_status', 'paid')
            ->where('order_items.inventory_updated', true)
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $paginatedQuery->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $paginatedQuery->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $paginatedQuery->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $paginatedQuery->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $paginatedItems = $paginatedQuery->with(['order.salesChannel', 'product'])
            ->orderBy('orders.order_date', 'desc')
            ->paginate(50, ['*'], 'items_page');

        return view('reports.gross-profit-report', compact(
            'paginatedItems',
            'reportData',
            'summary',
            'salesChannels',
            'products',
            'dateFrom',
            'dateTo',
            'channelId',
            'orderStatus',
            'productId',
            'sku',
            'groupBy'
        ));
    }

    /**
     * Export Gross Profit Report to Excel
     */
    public function exportGrossProfitReport(Request $request)
    {
        // Get same filters
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product');

        // Build query
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where('orders.payment_status', 'paid')
            ->where('order_items.inventory_updated', true)
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : $item->quantity;
            }),
            'total_revenue' => $orderItems->sum('total_price'),
            'total_cogs' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;

        // Group data
        if ($groupBy === 'product') {
            $reportData = $this->groupCogsByProduct($orderItems);
        } elseif ($groupBy === 'channel') {
            $reportData = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportData = $this->groupCogsByDate($orderItems);
        } else {
            $reportData = $this->groupCogsByOrder($orderItems);
        }

        // Sort by gross profit
        $reportData = $reportData->sortByDesc(function ($item) {
            return $item['gross_profit'] ?? 0;
        })->values();

        $export = new \App\Exports\GrossProfitReportExport($reportData->toArray(), $summary, $groupBy);

        return Excel::download($export, 'gross-profit-report-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Comparison Report (COGS vs Gross Profit)
     */
    public function comparisonReport(Request $request)
    {
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product');

        // Get filter options
        $salesChannels = SalesChannel::where('delete_status', '0')->orderBy('name')->get();
        $products = Product::where('delete_status', '0')->orderBy('name')->get();

        // Build order items query
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where('order_items.inventory_updated', true)
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary (all orders)
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : $item->quantity;
            }),
            'total_revenue' => $orderItems->sum('total_price'),
            'total_cogs' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;

        // Calculate paid orders summary
        $paidItems = $orderItems->filter(function ($item) {
            return $item->order->payment_status === 'paid';
        });

        $paidSummary = [
            'total_items_sold' => $paidItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : $item->quantity;
            }),
            'total_revenue' => $paidItems->sum('total_price'),
            'total_cogs' => $paidItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
        ];

        $paidSummary['gross_profit'] = $paidSummary['total_revenue'] - $paidSummary['total_cogs'];
        $paidSummary['gross_margin'] = $paidSummary['total_revenue'] > 0
            ? ($paidSummary['gross_profit'] / $paidSummary['total_revenue']) * 100
            : 0;

        // Build grouped comparison data
        $reportDataCollection = collect();

        if ($groupBy === 'product') {
            $reportDataCollection = $this->groupCogsByProduct($orderItems);
        } elseif ($groupBy === 'channel') {
            $reportDataCollection = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportDataCollection = $this->groupCogsByDate($orderItems);
        } elseif ($groupBy === 'order') {
            $reportDataCollection = $this->groupCogsByOrder($orderItems);
        }

        // Paginate grouped data with custom page name
        $perPage = 50;
        $currentPage = request()->get('grouped_page', 1);
        $reportData = new \Illuminate\Pagination\LengthAwarePaginator(
            $reportDataCollection->forPage($currentPage, $perPage),
            $reportDataCollection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'grouped_page']
        );

        return view('reports.comparison-report', compact(
            'reportData',
            'summary',
            'paidSummary',
            'salesChannels',
            'products',
            'dateFrom',
            'dateTo',
            'channelId',
            'orderStatus',
            'productId',
            'sku',
            'groupBy'
        ));
    }

    /**
     * Export Comparison Report to Excel
     */
    public function exportComparisonReport(Request $request)
    {
        // Get same filters
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $channelId = $request->get('channel_id');
        $orderStatus = $request->get('order_status');
        $productId = $request->get('product_id');
        $sku = $request->get('sku');
        $groupBy = $request->get('group_by', 'product');

        // Build query
        $query = OrderItem::select(
                'order_items.*',
                'orders.order_number',
                'orders.order_date',
                'orders.sales_channel_id',
                'orders.payment_status',
                'orders.order_status'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.order_date', '>=', $dateFrom)
            ->whereDate('orders.order_date', '<=', $dateTo)
            ->where('order_items.inventory_updated', true)
            // Bundle component lines are excluded - the bundle summary line already
            // carries the combined cost/revenue for the whole bundle sale.
            ->where(function ($q) {
                $q->whereNull('order_items.bundle_product_id')
                    ->orWhere('order_items.is_bundle_summary', true);
            });

        if ($channelId) {
            $query->where('orders.sales_channel_id', $channelId);
        }

        if ($orderStatus) {
            $query->where('orders.order_status', $orderStatus);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        if ($sku) {
            $query->where('order_items.sku', 'like', '%' . $sku . '%');
        }

        $orderItems = $query->with(['order.salesChannel', 'product'])->get();

        // Calculate summary
        $summary = [
            'total_items_sold' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : $item->quantity;
            }),
            'total_revenue' => $orderItems->sum('total_price'),
            'total_cogs' => $orderItems->sum(function ($item) {
                return $this->isBundleComponentItem($item) ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
            }),
        ];

        $summary['gross_profit'] = $summary['total_revenue'] - $summary['total_cogs'];
        $summary['gross_margin'] = $summary['total_revenue'] > 0
            ? ($summary['gross_profit'] / $summary['total_revenue']) * 100
            : 0;

        // Group data
        if ($groupBy === 'product') {
            $reportData = $this->groupCogsByProduct($orderItems);
        } elseif ($groupBy === 'channel') {
            $reportData = $this->groupCogsByChannel($orderItems);
        } elseif ($groupBy === 'date') {
            $reportData = $this->groupCogsByDate($orderItems);
        } else {
            $reportData = $this->groupCogsByOrder($orderItems);
        }

        $export = new \App\Exports\ComparisonReportExport($reportData->toArray(), $summary, $groupBy);

        return Excel::download($export, 'comparison-report-' . now()->format('Y-m-d') . '.xlsx');
    }
}
