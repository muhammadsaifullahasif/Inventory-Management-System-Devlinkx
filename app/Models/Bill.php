<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_number',
        'bill_date',
        'due_date',
        'supplier_id',
        'total_amount',
        'paid_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    // ==================== Relationships ====================

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(journalEntry::class, 'reference_id')->where('reference_type', 'bill');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== Scopes ====================

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', 'partially_paid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePayable($query)
    {
        return $query->whereIn('status', ['unpaid', 'partially_paid']);
    }

    public function scopePosted($query)
    {
        return $query->whereIn('status', ['unpaid', 'partially_paid', 'paid']);
    }

    public function scopeOverdue($query)
    {
        return $query->payable()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    // ==================== Accessors ====================

    public function getRemainingAmountAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    // ==================== Helper Methods ====================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['unpaid', 'partially_paid']);
    }

    public function isOverdue(): bool
    {
        return $this->isPayable() && $this->due_date && $this->due_date->isPast();
    }

    public function canEdit(): bool
    {
        return $this->isDraft() || ($this->status === 'unpaid' && $this->payments()->count() === 0);
    }

    public function canDelete(): bool
    {
        return $this->isDraft() || ($this->status === 'unpaid' && $this->payments()->count() === 0);
    }

    public function updateStatus(): void
    {
        if ($this->status === 'draft') {
            return;
        }

        if ($this->paid_amount >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partially_paid';
        } else {
            $this->status = 'unpaid';
        }

        $this->save();
    }

    public function calculateTotal(): float
    {
        return $this->items()->sum('amount');
    }

    public static function generateBillNumber(): string
    {
        $prefix = 'BILL-';
        $year = date('Y');
        $month = date('m');

        $lastBill = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBill) {
            $lastNumber = intval(substr($lastBill->bill_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
