<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $fillable = [
        'name',
        'warehouse_id',
        'is_default',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rack_stock()
    {
        return $this->hasMany(ProductStock::class, 'rack_id');
    }
}
