<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audited Models
    |--------------------------------------------------------------------------
    |
    | Every model listed here gets AuditObserver attached (see
    | AppServiceProvider::boot()), recording create/update/delete events
    | with before/after diffs. Add a model class here to start auditing it —
    | no changes to the model itself are required.
    |
    */
    'audited_models' => [
        \App\Models\Product::class,
        \App\Models\ProductStock::class,
        \App\Models\Order::class,
        \App\Models\OrderItem::class,
        \App\Models\OrderReturn::class,
        \App\Models\Purchase::class,
        \App\Models\PurchaseItem::class,
        \App\Models\Bill::class,
        \App\Models\BillItem::class,
        \App\Models\Payment::class,
        \App\Models\JournalEntry::class,
        \App\Models\JournalEntryLine::class,
        \App\Models\ChartOfAccount::class,
        \App\Models\Warehouse::class,
        \App\Models\Rack::class,
        \App\Models\User::class,
        \App\Models\SalesChannel::class,
        \App\Models\SalesChannelProduct::class,
        \App\Models\Shipping::class,
        \App\Models\ShippingSetting::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Attributes stripped from old_values/new_values before they're saved,
    | on top of whatever each model already declares in its own $hidden.
    |
    */
    'excluded_fields' => [
        'password',
        'remember_token',
        'api_token',
    ],

];
