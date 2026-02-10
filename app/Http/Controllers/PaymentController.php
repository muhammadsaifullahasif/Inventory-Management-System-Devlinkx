<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->middleware('permission:payments-view')->only(['index', 'show']);
        $this->middleware('permission:payments-add')->only(['create', 'store']);
        $this->middleware('permission:payments-delete')->only(['destroy']);
    }

    /**
     * Display list of payments
     */
    public function index(Request $request)
    {
        $query = Payment::with(['bill.supplier', 'paymentAccount']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('bill', function ($bq) use ($search) {
                        $bq->where('bill_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('bill.supplier', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Account filter
        if ($request->filled('payment_account_id')) {
            $query->where('payment_account_id', $request->payment_account_id);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        $payments = $query->orderBy('payment_date', 'DESC')
                        ->orderBy('id', 'DESC')
                        ->paginate(15)
                        -withQueryString();

        $bankCashAccounts = ChartOfAccount::where('is_bank_cash', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Statistics
        $totalPayments = Payment::posted()->sum('amount');
        $monthlyPayments = Payment::posted()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        return view('payments.index', compact('payments', 'bankCashAccounts', 'totalPayments', 'monthlyPayments'));
    }

    /**
     * Show create payment form
     */
    public function create(Request $request)
    {
        // Get payable bills
        $bills = Bill::payable()
            ->with('supplier')
            ->orderBy('bill_date', 'DESC')
            ->get();

        // Bank/Cash accounts
        $bankCashAccounts = ChartOfAccount::where('is_bank_cash', true)
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('name')
            ->get();

        // Pre-select bill if passed via query string
        $selectedBillId = $request->get('bill_id');
        $selectedBill = null;
        if ($selectedBillId) {
            $selectedBill = Bill::with('supplier')->find($selectedBillId);
        }

        return view('payments.create', compact('bills', 'bankCashAccounts', 'selectedBillId', 'selectedBill'));
    }

    /**
     * Store new payment
     */
    public function store(StorePaymentRequest $request)
    {
        try {
            $payment = $this->paymentService->createPayment($request->validated());

            $message = 'Payment recorded successfully.';

            if ($request->input('action') === 'save_new') {
                return redirect()
                    ->route('payments.create')
                    ->with('success', $message);
            }

            return redirect()
                ->route('payments.show', $payment)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * Show payment details
     */
    public function show(Payment $payment)
    {
        $payment->load([
            'bill.supplier',
            'bill.items.expenseAccount',
            'paymentAccount',
            'journalEntry.lines.account',
            'createdBy',
        ]);

        return view('payments.show', compact('payment'));
    }

    /**
     * Delete payment
     */
    public function destroy(Payment $payment)
    {
        try {
            $this->paymentService->deletePayment($payment);

            return redirect()
                ->route('payments.index')
                ->with('success', 'Payment deleted and reversed successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete payment: ' . $e->getMessage());
        }
    }

    /**
     * Get bill details (AJAX)
     */
    public function getBillDetails(Bill $bill)
    {
        $bill->load('supplier');

        return response()->json([
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_name' => $bill->supplier->full_name,
            'bill_date' => $bill->bill_date->format('M d, Y'),
            'due_date' => $bill->due_date?->format('M d, Y'),
            'total_amount' => $bill->total_amount,
            'paid_amount' => $bill->paid_amount,
            'remaining_amount' => $bill->remaining_amount,
            'status' => $bill->status,
            'is_overdue' => $bill->isOverdue(),
        ]);
    }
}
