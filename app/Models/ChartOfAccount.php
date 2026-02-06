<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'nature',
        'type',
        'is_system',
        'is_active',
        'description',
        'is_bank_cash',
        'account_number',
        'bank_name',
        'branch',
        'iban',
        'opening_balance',
        'current_balance',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_bank_cash' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    // ==================== Relationships ====================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class, 'expense_account_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_account_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'payable_account_id');
    }

    // ==================== Scopes ====================

    public function scopeGroups($query)
    {
        return $query->where('type', 'group');
    }

    public function scopeAccounts($query)
    {
        return $query->where('type', 'account');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByNature($query, string $nature)
    {
        return $query->where('nature', $nature);
    }

    public function scopeBankCash($query)
    {
        return $query->where('is_bank_cash', true)->where('type', 'account');
    }

    public function scopeExpenseAccounts($query)
    {
        return $query->where('nature', 'expense')->where('type', 'account');
    }

    public function scopeAssetAccounts($query)
    {
        return $query->where('nature', 'asset')->where('type', 'account');
    }

    public function scopePayableAccounts($query)
    {
        return $query->where('nature', 'liability')->where('type', 'account');
    }

    // ==================== Helper Methods ====================

    public function isGroup():bool
    {
        return $this->type === 'group';
    }

    public function isAccount():bool
    {
        return $this->type === 'account';
    }

    public function isBankOrCash():bool
    {
        return $this->is_bank_cash;
    }

    public function canDelete():bool
    {
        if ($this->is_system) {
            return false;
        }

        if ($this->journalLines()->exists()) {
            return false;
        }

        if ($this->isGroup() && $this->children()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get the debit/credit type for increase/decrease based on account nature
     */
    public function getEntryType(bool $isIncrease): string
    {
        $rules = [
            'asset'     => ['increase' => 'debit', 'decrease' => 'credit'],
            'liability' => ['increase' => 'credit', 'decrease' => 'debit'],
            'equity'    => ['increase' => 'credit', 'decrease' => 'debit'],
            'revenue'   => ['increase' => 'credit', 'decrease' => 'debit'],
            'expense'   => ['increase' => 'debit', 'decrease' => 'credit'],
        ];

        return $rules[$this->nature][$isIncrease ? 'increase' : 'decrease'];
    }

    /**
     * Update balance for bank/cash accounts
     */
    public function updateBalance(float $amount, string $type): void
    {
        if (!$this->is_bank_cash) {
            return;
        }

        if ($type === 'debit') {
            $this->current_balance += $amount;
        } else {
            $this->current_balance -= $amount;
        }

        $this->save();
    }

    /**
     * Calculate current balance from journal entries
     */
    public function getCalculatedBalance(?string $asOfDate = null): float
    {
        $query = $this->journalLines();

        if ($asOfDate) {
            $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('entry_date', '<=', $asOfDate);
            });
        }

        $totals = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        $debit = $totals->total_debit ?? 0;
        $credit = $totals->total_credit ?? 0;

        // For asset and expense: balance = debit - credit
        // For liability, equity, revenue: balance = credit - debit
        if (in_array($this->nature, ['asset', 'expense'])) {
            return $this->opening_balance + ($debit - $credit);
        }

        return $this->opening_balance + ($credit - $debit);
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Get full name with bank details for bank accounts
     */
    public function getBankDisplayNameAttribute(): string
    {
        if ($this->is_bank_cash && $this->bank_name) {
            return "{$this->name} ({$this->bank_name})";
        }

        return $this->name;
    }
}
