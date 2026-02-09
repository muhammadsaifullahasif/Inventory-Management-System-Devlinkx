<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Services\BillService;
use App\Models\ChartOfAccount;
use App\Http\Requests\StoreBillRequest;

class BillController extends Controller
{
    protected BillService $billService;

    public function __construct(BillService $billService)
    {
        $this->billService = $billService;
        $this->middleware('permission:bills-view')->only(['index', 'show']);
        $this->middleware('permission:bills-add')->only(['create', 'store']);
        $this->middleware('permission:bills-edit')->only(['edit', 'update']);
        $this->middleware('permission:bills-delete')->only(['destroy']);
        $this->middleware('permission:bills-post')->only(['post']);
    }

    /**
     * Display list of bills
     */
    public function index(Request $request)
    {
        $query = Bill::with(['supplier', 'items']);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Supplier filter
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('bill_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('bill_date', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->orderBy('bill_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->paginate(15)
                        ->withQueryString();

        $suppliers = Supplier::orderBy('name')->get();
        $statistics = $this->billService->getStatistics();

        return view('bills.index', compact('bills', 'suppliers', 'statistics'));
    }

    /**
     * Show create bill form
     */
    public function create()
    {
        $suppliers = Supplier::where('delete_status', '1')
                            ->orderBy('name')
                            ->get();
        
        $expenseGroups = ChartOfAccount::where('type', 'group')
            ->where('nature', 'expense')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
            ->orderBy('code')
            ->get();

        return view('bills.create', compact('suppliers', 'expenseGroups'));
    }

    /**
     * Store new bill
     */
    public function store(StoreBillRequest $request)
    {
        try {
            $bill = $this->billService->createBill($request->validated());

            $message = $bill->status === 'draft'
                ? 'Bill saved as draft successfully.'
                : 'Bill created and posted successfully.';

            // Handle different submit actions
            if ($request->input('action') === 'save_new') {
                return redirect()
                    ->route('bills.create')
                    ->with('success', $message);
            }

            return redirect()
                ->route('bills.show', $bill)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to create bill: ' . $e->getMessage());
        }
    }

    /**
     * Show bill details
     */
    public function show(Bill $bill)
    {
        $bill->load(['supplier', 'items.expenseAccount', 'payments.paymentAccount', 'journalEntry.lines.account', 'createdBy']);

        return view('bills.show', compact('bill'));
    }

    /**
     * Show edit bill form
     */
    public function edit(Bill $bill)
    {
        if (!$bill->canEdit()) {
            return redirect()
                ->route('bills.show', $bill)
                ->with('error', 'This bill cannot be edited because it has payments.');
        }

        $bill->load(['items.expenseAccount']);

        $suppliers = Supplier::where('delete_status', '1')
                            ->orderBy('name')
                            ->get();

        $expenseGroups = ChartOfAccount::where('type', 'group')
            ->where('nature', 'expense')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
            ->orderBy('code')
            ->get();

        return view('bills.edit', compact('bill', 'suppliers', 'expenseGroups'));
    }

    /**
     * Update bill
     */
    public function update(StoreBillRequest $request, Bill $bill)
    {
        if (!$bill->canEdit()) {
            return redirect()
                ->route('bills.show', $bill)
                ->with('error', 'This bill cannot be edited.');
        }

        try {
            $bill = $this->billService->updateBill($bill, $request->validated());

            return redirect()
                ->route('bills.show', $bill)
                ->with('success', 'Bill updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to update bill: ' . $e->getMessage());
        }
    }

    /**
     * Delete bill
     */
    public function destroy(Bill $bill)
    {
        if (!$bill->canDelete()) {
            return back()->with('error', 'This bill cannot be deleted because it has payments.');
        }

        try {
            $this->billService->deleteBill($bill);

            return redirect()
                ->route('bills.index')
                ->with('success', 'Bill deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete bill: ' . $e->getMessage());
        }
    }

    /**
     * Post a draft bill
     */
    public function post(Bill $bill)
    {
        if ($bill->status !== 'draft') {
            return back()->with('error', 'Only draft bills can be posted.');
        }

        try {
            $this->billService->postBill($bill);

            return redirect()
                ->route('bills.show', $bill)
                ->with('success', 'Bill posted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to post bill: ' . $e->getMessage());
        }
    }

    /**
     * Get expense accounts by group (AJAX)
     */
    public function getExpenseAccountsByGroup(ChartOfAccount $group)
    {
        $accounts = ChartOfAccount::where('parent_id', $group->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($accounts);
    }
}
