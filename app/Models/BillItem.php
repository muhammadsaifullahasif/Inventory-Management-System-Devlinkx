<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'expense_account_id',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ==================== Relationships ====================
    
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }
}
