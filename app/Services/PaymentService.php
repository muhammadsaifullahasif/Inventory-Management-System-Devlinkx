<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    /**
     * Create a new payment
     */
    public function createPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $bill = Bill::findOrFail($data['bill_id']);

            // Validate payment amount
            $remainingAmount = $bill->total_amount - $bill->paid_amount;
            if ($data['amount'] > $remainingAmount) {
                throw new \Exception("Payment amount ({$data['amount']}) exceeds remaining balance ({$remainingAmount}).");
            }

            // Create payment
            $payment = Payment::create([
                'payment_number' => Payment::generatePaymentNumber(),
                'payment_date' => $data['payment_date'],
                'bill_id' => $data['bill_id'],
                'payment_account_id' => $data['payment_account_id'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'] ?? 'posted',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Update bill paid amount and status
            $bill->paid_amount += $payment->amount;
            $bill->updateStatus();

            // Update bank/cash balance
            $paymentAccount = ChartOfAccount::findOrFail($data['payment_account_id']);
            if ($paymentAccount->is_bank_cash) {
                $paymentAccount->updateBalance($payment->amount, 'credit'); // Money going out 
            }

            // Create journal entry if posted
            if ($payment->status === 'posted') {
                $this->postJournalEntry($payment, $bill);
            }

            return $payment->fresh(['bill.supplier', 'paymentAccount']);
        });
    }

    /**
     * Delete a payment and reverse its effects
     */
    public function deletePayment(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $bill = $payment->bill;

            // Reverse bank/cash balance
            $paymentAccount = $payment->paymentAccount;
            if ($paymentAccount->is_bank_cash) {
                $paymentAccount->updateBalance($payment->amount, 'debit'); // Money coming back
            }

            // Reverse bill paid amount
            $bill->paid_amount -= $payment->amount;
            $bill->updateStatus();

            // Delete journal entry
            if ($payment->journalEntry) {
                $payment->journalEntry->lines()->delete();
                $payment->journalEntry->delete();
            }

            return $payment->delete();
        });
    }

    /**
     * Create journal entry for payment
     * Debit: Accounts Payable (clear liability)
     * Credit: Bank/Cash (money going out)
     */
    protected function postJournalEntry(Payment $payment, Bill $bill): JournalEntry
    {
        $bill->load('supplier');

        // Get payable account
        $payableAccountId = $this->getPayableAccountId($bill->supplier);

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $payment->payment_date,
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'narration' => "Payment #{$payment->payment_number} for Bill #{$bill->bill_number}",
            'is_posted' => true,
            'created_by' => Auth::id(),
        ]);

        // Debit: Accounts Payable
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $payableAccountId,
            'description' => "Payment to {$bill->supplier->full_name}",
            'debit' => $payment->amount,
            'credit' => 0,
        ]);

        // Credit: Bank/Cash
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $payment->payment_account_id,
            'account_id' => $payment->payment_account_id,
            'description' => "Payment via {$payment->paymentAccount->name}",
            'debit' => 0,
            'credit' => $payment->amount,
        ]);

        return $journalEntry;
    }

    /**
     * Get payable account ID for supplier
     */
    protected function getPayableAccountId($supplier): int
    {
        if ($supplier->payable_account_id) {
            return $supplier->payable_account_id;
        }

        $defaultPayable = ChartOfAccount::where('code', '2001')->first();

        if (!$defaultPayable) {
            throw new \Exception('Default Accounts Payable account not found.');
        }

        return $defaultPayable;
    }
}
