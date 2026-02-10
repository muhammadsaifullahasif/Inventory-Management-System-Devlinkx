<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

class JournalService
{
    /**
     * Get account balance as of a specific data
     */
    public function getAccountBalance(int $accountId, ?string $asOfDate = null): float
    {
        $account = ChartOfAccount::findOrFail($accountId);
        return $account->getCalculatedBalance($asOfDate);
    }

    /**
     * Get all transactions for a specific account
     */
    public function getAccountTransactions(int $accountId, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->with(['journalEntry'])
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
            ->select('journal_entry_lines.*');

        return $query->get();
    }

    /**
     * Get opening balance for an account before a specific date
     */
    public function getOpeningBalance(int $accountId, ?string $beforeDate = null): float
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $query = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($beforeDate) {
                $q->where('is_posted', true);
                if ($beforeDate) {
                    $q->where('entry_date', '<', $beforeDate);
                }
            });

        $totals = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        $debit = $totals->total_debit ?? 0;
        $credit = $totals->total_credit ?? 0;

        if (in_array($account->nature, ['asset', 'expense'])) {
            return $account->opening_balance + ($debit - $credit);
        }

        return $account->opening_balance + ($credit - $debit);
    }

    /**
     * Get trail balance data
     */
    public function getTrialBalance(?string $asOfDate = null): Collection
    {
        $accounts = ChartOfAccount::where('type', 'account')
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($asOfDate) {
            $balance = $account->getCalculatedBalance($asOfDate);

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'nature' => $account->nature,
                'group' => $account->parent?->name ?? '',
                'debit' => $balance > 0 && in_array($account->nature, ['asset', 'expense']) ? $balance : ($balance < 0 && in_array($account->nature, ['liability', 'equity', 'revenue']) ? abs($balance) : 0),
                'credit' => $balance > 0 && in_array($account->nature,['liability', 'equity', 'revenue']) ? $balance : ($balance < 0 && in_array($account->nature, ['asset', 'expense']) ? abs($balance) : 0),
            ];
        })->filter(function ($item) {
            return $item['debit'] != 0 || $item['credit'] != 0;
        });
    }
}
