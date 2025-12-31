<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'rack_id',
        'quantity',
        'active_status',
        'delete_status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }
}
