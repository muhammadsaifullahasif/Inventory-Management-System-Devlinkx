<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Services\JournalService;

class GeneralLedgerController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
        $this->middleware('permission:general-ledger-view');
    }

    /**
     * Show general ledger - account selection
     */
    public function index(Request $request)
    {
        $groups = ChartOfAccount::where('type', 'group')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        $selectedAccountId = $request->get('account_id', 'all');
        $dateFrom = $request->get('date_from', date('Y-m-01'));
        $dateTo = $request->get('date_to', date('Y-m-d'));

        $account = null;
        $transactions = collect();
        $openingBalance = 0;
        $runningBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $showAll = ($selectedAccountId === 'all' || empty($selectedAccountId));

        if ($showAll) {
            // All accounts — get all posted transactions in date range
            $transactions = JournalEntryLine::with(['journalEntry', 'account'])
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('is_posted', true)
                        ->where('entry_date', '>=', $dateFrom)
                        ->where('entry_date', '<=', $dateTo);
                })
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entry_lines.id')
                ->select('journal_entry_lines.*')
                ->get();

            $totalDebit = $transactions->sum('debit');
            $totalCredit = $transactions->sum('credit');
        } else {
            // Single account mode
            $account = ChartOfAccount::findOrFail($selectedAccountId);

            // Check if this is a group account - if so, get all child account IDs
            $accountIds = [$selectedAccountId];
            $isGroupAccount = $account->type === 'group';

            if ($isGroupAccount) {
                $childIds = $account->children()->pluck('id')->toArray();
                $accountIds = array_merge($accountIds, $childIds);
            }

            // Get opening balance (before date_from)
            $openingBalance = $this->getOpeningBalanceForAccounts($accountIds, $dateFrom, $account->nature);
            $runningBalance = $openingBalance;

            // Get transactions for all account IDs
            $transactions = $this->getTransactionsForAccounts($accountIds, $dateFrom, $dateTo);

            // Calculate running balances
            $transactions = $transactions->map(function ($line) use ($account, &$runningBalance, &$totalDebit, &$totalCredit) {
                $totalDebit += $line->debit;
                $totalCredit += $line->credit;

                if (in_array($account->nature, ['asset', 'expense'])) {
                    $runningBalance += ($line->debit - $line->credit);
                } else {
                    $runningBalance += ($line->credit - $line->debit);
                }

                $line->running_balance = $runningBalance;
                return $line;
            });
        }

        return view('general-ledger.index', compact(
            'groups',
            'account',
            'transactions',
            'openingBalance',
            'totalDebit',
            'totalCredit',
            'runningBalance',
            'selectedAccountId',
            'dateFrom',
            'dateTo',
            'showAll'
        ));
    }

    /**
     * Get transactions for multiple account IDs
     */
    protected function getTransactionsForAccounts(array $accountIds, ?string $dateFrom, ?string $dateTo)
    {
        return JournalEntryLine::whereIn('account_id', $accountIds)
            ->with(['journalEntry', 'account'])
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('is_posted', true);
                if ($dateFrom) {
                    $q->where('entry_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->where('entry_date', '<=', $dateTo);
                }
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')
            ->get();
    }

    /**
     * Get opening balance for multiple account IDs before a specific date
     */
    protected function getOpeningBalanceForAccounts(array $accountIds, ?string $beforeDate, string $nature): float
    {
        $query = JournalEntryLine::whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', function ($q) use ($beforeDate) {
                $q->where('is_posted', true);
                if ($beforeDate) {
                    $q->where('entry_date', '<', $beforeDate);
                }
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        // For asset/expense accounts: balance = debits - credits
        // For liability/equity/revenue: balance = credits - debits
        if (in_array($nature, ['asset', 'expense'])) {
            return $totalDebit - $totalCredit;
        }

        return $totalCredit - $totalDebit;
    }
}
