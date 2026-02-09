<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class BillService
{
    /**
     * Create a new bill
     */
    public function createBill(array $data): Bill
    {
        return DB::transaction(function () use ($data) {
            // Generate bill number
            $billNumber = Bill::generateBillNumber();

            // Create bill
            $bill = Bill::create([
                'bill_number' => $billNumber,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'total_amount' => 0,
                'paid_amount' => 0,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Create bill items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $bill->items()->create([
                    'expense_account_id' => $item['expense_account_id'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
                $totalAmount += $item['amount'];
            }

            // Update total amount
            $bill->update(['total_amount' => $totalAmount]);

            // If status is not draft, create journal entry
            if ($bill->status !== 'draft') {
                $this->pstJournalEntry($bill);
            }

            return $bill->fresh(['items', 'supplier']);
        });
    }

    /**
     * Update an existing bill
     */
    public function updateBill(Bill $bill, array $data) : Bill
    {
        if (!$bill->canEdit()) {
            throw new \Exception('This bill cannot be edited.');
        }

        return DB::transaction(function () use ($bill, $data) {
            // If bill was posted, reverse the journal entry first
            if ($bill->journalEntry) {
                $this->reverseJournalEntry($bill);
            }

            // Update bill
            $bill->update([
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'status' => $data['status'] ?? $bill->status,
                'notes' => $data['notes'] ?? null,
            ]);

            // Delete existing items and create new ones
            $bill->items()->delete();

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $bill->items()->create([
                    'expense_account_id' => $item['expense_account_id'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
                $totalAmount += $item['amount'];
            }

            // Update total amount
            $bill->update(['total_amount' => $totalAmount]);

            // If status is not draft, create journal entry
            if ($bill->status !== 'draft') {
                $this->postJournalEntry($bill);
            }

            return $bill->fresh(['items', 'supplier']);
        });
    }

    /**
     * Delete a bill
     */
    public function deleteBill(Bill $bill): bool
    {
        if (!$bill->canDelete()) {
            throw new \Exception('This bill cannot be deleted.');
        }

        return DB::transaction(function () use ($bill) {
            // Reverse journal entry if exists
            if ($bill->journalEntry) {
                $bill->journalEntry->lines()->delete();
                $bill->journalEntry->delete();
            }

            // Delete items
            $bill->items()->delete();

            // Delete bill
            return $bill->delete();
        });
    }

    /**
     * Post a draft bill (change status from draft to unpaid)
     */
    public function postBill(Bill $bill): Bill
    {
        if ($bill->status !== 'draft') {
            throw new \Exception('Only draft bills can be posted.');
        }

        return DB::transaction(function () use ($bill) {
            $bill->update(['status' => 'unpaid']);
            $this->postJournalEntry($bill);
            return $bill->fresh();
        });
    }

    /**
     * Create journal entry for bill
     * Debit: Expense Accounts
     * Credit: Accounts Payable
     */
    protected function postJournalEntry(Bill $bill): JournalEntry
    {
        $bill->load(['items.expenseAccount', 'supplier']);

        // Get or create payable account for supplier
        $payableAccountId = $this->getPayableAccountId($bill->supplier);

        // Crete journal entry
        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $bill->bill_date,
            'reference_type' => 'bill',
            'reference_id' => $bill->id,
            'narration' => "Bill #{$bill->bill_number} - {$bill->supplier->name}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // Debit each expense account
        foreach ($bill->items as $item) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $item->expense_account_id,
                'description' => $item->description,
                'debit' => $item->amount,
                'credit' => 0,
            ]);
        }

        // Credit Accounts Payable
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $payableAccountId,
            'description' => "Payable to {$bill->supplier->name}",
            'debit' => 0,
            'credit' => $bill->total_amount,
        ]);

        return $journalEntry;
    }

    /**
     * Reverse journal entry for bill
     */
    protected function reverseJournalEntry(Bill $bill): void
    {
        if ($bill->journalEntry) {
            $bill->journalEntry->lines()->delete();
            $bill->journalEntry->delete();
        }
    }

    /**
     * Get payable account ID for supplier
     */
    protected function getPayableAccountId($supplier): int
    {
        // If supplier has a specific payable account, use it
        if ($supplier->payable_account_id) {
            return $supplier->payable_account_id;
        }

        // Otherwise use the default Trade Payables account
        $defaultPayable = ChartOfAccount::where('code', '2001')->first();

        if (!$defaultPayable) {
            throw new \Exception('Default Accounts Payable account not found. Please run the seeder.');
        }

        return $defaultPayable->id;
    }

    /**
     * Get bill statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_bills' => Bill::count(),
            'draft_bills' => Bill::draft()->count(),
            'unpaid_bills' => Bill::unpaid()->count(),
            'partially_paid_bills' => Bill::partiallyPaid()->count(),
            'paid_bills' => Bill::paid()->count(),
            'total_payable' => Bill::payable()->sum(DB::raw('total_amount - paid_amount')),
            'overdue_bills' => Bill::overdue()->count(),
            'overdue_amount' => Bill::overdue()->sum(DB::raw('total_amount - paid_amount')),
        ];
    }
}