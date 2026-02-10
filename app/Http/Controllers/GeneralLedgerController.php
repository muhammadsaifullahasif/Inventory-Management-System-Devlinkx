<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
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
            ->with(['children', function ($q) {
                $q->where('is_active', true)->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        $selectedAccountId = $request->get('account_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $account = null;
        $transactions = collect();
        $openingBalance = 0;
        $runningBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;

        if ($selectedAccountId) {
            $account = ChartOfAccount::findOrFail($selectedAccountId);

            // Get opening balance (before date_from)
            $openingBalance = $this->journalService->getOpeningBalance($selectedAccountId, $dateFrom);
            $runningBalance = $openingBalance;

            // Get transactions
            $transactions = $this->journalService->getAccountTransactions($selectedAccountId, $dateFrom, $dateTo);

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
            'dateTo'
        ));
    }
}
