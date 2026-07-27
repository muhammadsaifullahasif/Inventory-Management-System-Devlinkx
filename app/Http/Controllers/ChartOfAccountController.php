<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class ChartOfAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:chart-of-accounts-view')->only(['index', 'show']);
        $this->middleware('permission:chart-of-accounts-add')->only(['create', 'store']);
        $this->middleware('permission:chart-of-accounts-edit')->only(['edit', 'update']);
        $this->middleware('permission:chart-of-accounts-delete')->only(['destroy']);
    }

    /**
     * Display the chart of accounts in tree structure
     */
    public function index(Request $request)
    {
        $query = ChartOfAccount::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Nature filter
        if ($request->filled('nature')) {
            $query->where('nature', $request->nature);
        }

        // Get all accounts grouped by parent for tree view
        if ($request->filled('search') || $request->filled('nature')) {
            // Flat list for search results
            $accounts = $query->with('parent')->orderBy('code')->get();
            $isFiltered = true;
        } else {
            // Tree structure for normal view
            $accounts = ChartOfAccount::whereNull('parent_id')
                ->with(['children' => function ($q) {
                    $q->orderBy('code');
                }])
                ->orderBy('code')
                ->get();
            $isFiltered = true;
        }

        // Get groups for dropdown (when adding new account)
        $groups = ChartOfAccount::where('type', 'group')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('chart-of-accounts.index', compact('accounts', 'groups', 'isFiltered'));
    }

    /**
     * Show form to create new account
     */
    public function create(Request $request)
    {
        $groups = ChartOfAccount::where('type', 'group')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $selectedGroup = $request->get('group_id');

        return view('chart-of-accounts.create', compact('groups', 'selectedGroup'));
    }

    /**
     * Store new account
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|exists:chart_of_accounts,id',
            'code' => 'required|string|max:20|unique:chart_of_accounts,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_bank_cash' => 'nullable|boolean',
            'bank_name' => 'nullable|required_if:is_bank_cash,1|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'branch' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Get parent to inherit nature
            $parent = ChartOfAccount::findOrFail($validated['parent_id']);

            $account = ChartOfAccount::create([
                'parent_id' => $validated['parent_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'nature' => $parent->nature,
                'type' => 'account',
                'is_system' => false,
                'is_active' => true,
                'description' => $validated['description'] ?? null,
                'is_bank_cash' => $request->boolean('is_bank_cash'),
                'bank_name' => $validated['bank_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'branch' => $validated['branch'] ?? null,
                'iban' => $validated['iban'] ?? null,
                'opening_balance' => $validated['opening_balance'] ?? 0,
                'current_balance' => $validated['opening_balance'] ?? 0,
            ]);

            DB::commit();

            return redirect()
                ->route('chart-of-accounts.index')
                ->with('success', 'Account created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to create account: ' . $e->getMessage());
        }
    }

    /**
     * Show account details
     */
    public function show(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->load(['parent', 'children', 'journalLines.journalEntry']);

        // Get recent transactions - for groups, include all child account transactions
        if ($chartOfAccount->isGroup()) {
            // Get all child account IDs (including nested children)
            $childAccountIds = $this->getAllChildAccountIds($chartOfAccount);

            $recentTransactions = JournalEntryLine::whereIn('account_id', $childAccountIds)
                ->with(['journalEntry', 'account'])
                ->whereHas('journalEntry', function ($q) {
                    $q->where('is_posted', true);
                })
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get();

            $totalTransactionCount = JournalEntryLine::whereIn('account_id', $childAccountIds)
                ->whereHas('journalEntry', function ($q) {
                    $q->where('is_posted', true);
                })
                ->count();
        } else {
            $recentTransactions = $chartOfAccount->journalLines()
                ->with('journalEntry')
                ->whereHas('journalEntry', function ($q) {
                    $q->where('is_posted', true);
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $totalTransactionCount = $chartOfAccount->journalLines()
                ->whereHas('journalEntry', function ($q) {
                    $q->where('is_posted', true);
                })
                ->count();
        }

        $balance = $chartOfAccount->getCalculatedBalance();

        return view('chart-of-accounts.show', compact('chartOfAccount', 'recentTransactions', 'balance', 'totalTransactionCount'));
    }

    /**
     * Get all child account IDs recursively (for groups)
     */
    private function getAllChildAccountIds(ChartOfAccount $account): array
    {
        $ids = [];

        foreach ($account->children as $child) {
            if ($child->isGroup()) {
                // Recursively get nested children
                $ids = array_merge($ids, $this->getAllChildAccountIds($child));
            } else {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Show form to edit account
     */
    public function edit(ChartOfAccount $chartOfAccount)
    {
        // System accounts cannot be edited (except description)
        $groups = ChartOfAccount::where('type', 'group')
            ->where('is_active', false)
            ->orderBy('code')
            ->get();

        return view('chart-of-accounts.edit', compact('chartOfAccount', 'groups'));
    }

    /**
     * Update account
     */
    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        // Different validation for system vs non-system accounts
        if ($chartOfAccount->is_system) {
            $validated = $request->validate([
                'description' => 'nullable|string|max:500',
                'is_active' => 'nullable|boolean',
            ]);

            $chartOfAccount->update([
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        } else {
            $validated = $request->validate([
                'parent_id' => 'required|exists:chart_of_accounts,id',
                'code' => 'required|string|max:20|unique:chart_of_accounts,code,' . $chartOfAccount->id,
                'name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'is_active' => 'nullable|boolean',
                'is_bank_cash' => 'nullable|boolean',
                'bank_name' => 'nullable|required_if:is_bank_cash,1|string|max:100',
                'account_number' => 'nullable|string|max:50',
                'branch' => 'nullable|string|max:100',
                'iban' => 'nullable|string|max:50',
                'opening_balance' => 'nullable|numeric|min:0',
            ]);

            try {
                DB::beginTransaction();

                $parent = ChartOfAccount::findOrFail($validated['parent_id']);

                // Calculate balance difference if opening balance changed
                $balanceDiff = ($validated['opening_balance'] ?? 0) - $chartOfAccount->opening_balance;

                $chartOfAccount->update([
                    'parent_id' => $validated['parent_id'],
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'nature' => $parent->nature,
                    'description' => $validated['description'] ?? null,
                    'is_active' => $request->boolean('is_active', true),
                    'is_bank_cash' => $request->boolean('is_bank_cash'),
                    'bank_name' => $validated['bank_name'] ?? null,
                    'account_number' => $validated['account_number'] ?? null,
                    'branch' => $validated['branch'] ?? null,
                    'iban' => $validated['iban'] ?? null,
                    'opening_balance' => $validated['opening_balance'] ?? 0,
                    'current_balance' => $chartOfAccount->current_balance + $balanceDiff,
                ]);

                DB::commit();

            } catch(\Exception $e) {
                DB::rollBack();
                return back()
                    ->withInput()
                    ->with('error', 'Failed to update account: ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('chart-of-accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Delete account
     */
    public function destroy(ChartOfAccount $chartOfAccount)
    {
        if (!$chartOfAccount->canDelete()) {
            return back()->with('error', 'This account cannot be deleted. It may be a system account or has transactions.');
        }

        try {
            $chartOfAccount->delete();
            return redirect()
                ->route('chart-of-accounts.index')
                ->with('success', 'Account deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete account: ' . $e->getMessage());
        }
    }

    /**
     * Get accounts by group (for AJAX dropdown)
     */
    public function getByGroup(ChartOfAccount $group)
    {
        $accounts = ChartOfAccount::where('parent_id', $group->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($accounts);
    }

    /**
     * Get all expense accounts grouped (for AJAX)
     */
    public function getExpenseAccounts()
    {
        $groups = ChartOfAccount::where('type', 'group')
            ->where('nature', 'expense')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
            ->orderBy('code')
            ->get();

        return response()->json($groups);
    }

    /**
     * Get all bank/cash accounts (for AJAX)
     */
    public function getBankCashAccounts()
    {
        $accounts = ChartOfAccount::where('is_bank_cash', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'bank_name', 'current_balance']);

        return response()->json($accounts);
    }

    /**
     * Quick add account (AJAX) - for adding from bill/payment forms
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|exists:chart_of_accounts,id',
            'name' => 'required|string|max:100',
        ]);

        try {
            $parent = ChartOfAccount::findOrFail($validated['parent_id']);

            // Generate next code
            $lastCode = ChartOfAccount::where('parent_id', $parent->id)
                ->orderBy('code', 'desc')
                ->first();

            if ($lastCode) {
                $newCode = str_pad((int)$lastCode->code + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newCode = $parent->code . '01';
            }

            $account = ChartOfAccount::create([
                'parent_id' => $parent->id,
                'code' => $newCode,
                'name' => $validated['name'],
                'nature' => $parent->nature,
                'type' => 'account',
                'is_system' => false,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'account' => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next available code for a parent group (AJAX)
     */
    public function getNextCode(ChartOfAccount $parent)
    {
        // Get the last child account under this parent, ordered by code descending
        $lastChild = ChartOfAccount::where('parent_id', $parent->id)
            ->orderBy('code', 'DESC')
            ->first();

        if ($lastChild) {
            // Increment the last code
            $lastCode = $lastChild->code;

            // Handle numeric codes (e.g., 5001 → 5002)
            if (is_numeric($lastCode)) {
                $newCode = str_pad((int) $lastCode + 1, strlen($lastCode), '0', STR_PAD_LEFT);
            } else {
                // Handle alphanumeric - extract trailing number
                preg_match('/^(.*?)(\d+)$/', $lastCode, $matches);
                if (isset($matches[2])) {
                    $prefix = $matches[1];
                    $number = (int) $matches[2] + 1;
                    $newCode = $prefix . str_pad($number, strlen($matches[2]), '0', STR_PAD_LEFT);
                } else {
                    // Fallback: parent code + 01
                    $newCode = $parent->code . '01';
                }
            }
        } else {
            // No children yet — use parent code + "01"
            $newCode = $parent->code . '01';
        }

        return response()->json([
            'success' => true,
            'code' => $newCode,
            'nature' => $parent->nature,
        ]);
    }
}
