<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'payment_date',
        'bill_id',
        'payment_account_id',
        'amount',
        'payment_method',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    // ==================== Relationships ====================

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->hasOne(JournalEntry::class, 'reference_id')
            ->where('reference_type', 'payment');
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

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    // ==================== Helper Methods ====================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY-';
        $year = date('Y');
        $month = date('m');

        $lastPayment = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = intval(substr($lastPayment->payment_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
