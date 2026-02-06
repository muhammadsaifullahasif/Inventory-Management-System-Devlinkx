<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference_type',
        'reference_id',
        'narration',
        'is_posted',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'is_posted' => 'boolean',
    ];

    // ==================== Relationships ====================

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reference model (Bill or Payment)
     */
    public function getReference()
    {
        if ($this->reference_type === 'bill') {
            return Bill::find($this->reference_id);
        } elseif ($this->reference_type === 'payment') {
            return Payment::find($this->reference_id);
        }

        return null;
    }

    // ==================== Scopes ====================

    public function scopePosted($query)
    {
        return $query->where('is_posted', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('reference_type', $type);
    }

    // ==================== Accessors ====================

    public function getTotalDebitAttribute(): float
    {
        return $this->lines()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->lines()->sum('credit');
    }

    // ==================== Helper Methods ====================

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    public static function generateEntryNumber(): string
    {
        $prefix = 'JE-';
        $year = date('Y');
        $month = date('m');

        $lastEntry = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = intval(substr($lastEntry->entry_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_RIGHT);
    }
}
