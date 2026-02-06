<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    // ==================== Relationships ====================
    
    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
