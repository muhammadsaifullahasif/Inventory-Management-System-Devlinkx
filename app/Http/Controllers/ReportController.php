<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Services\JournalService;
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
    public function trailBalance(Request $request)
    {
        $asOfDate = $request->get('as_of_date', date('Y-m-d'));

        $accounts = $this->journalService->getTrailBalance($asOfDate);

        $totalDebit = $accounts->sum('debit');
        $totalCredit = $accounts->sum('credit');

        return view('reports.trail-balance', compact(
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
                -pluck('id');
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
        $reportData = collect();
        foreach ($expenseItems as $item) {
            $account = $accounts->get($item->expense_account_id);
            if (!$account) continue;

            $groupName = $account->parent?->name ?? 'Ungrouped';
            $groupCode = $account->parent?->code ?? '0000';

            if (!$reportData->has($groupName)) {
                $reportData[$groupName] = [
                    'code' => $groupCode,
                    'name' => $groupName,
                    'total' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$groupName]['total'] += $item->total_amount;
            $reportData[$groupName]['items']->push([
                'code' => $account->code,
                'name' => $account->name,
                'bill_count' => $item->bill_count,
                'total_amount' => $item->total_amount,
            ]);
        }

        // Sort groups by code, items by total descending
        $reportData = $reportData->sortBy('code')->values();
        foreach ($reportData as &$group) {
            $group['items'] = $group['items']->sortByDesc('total_amount')->values();
        }

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
            $billIds = Bill::where('supplier_id', $supplier_id)
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
                $billsBefore = Bill::where('supplier_id', $supplier_id)
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
}
