<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'parent_id',
        'active_status',
        'delete_status',
    ];

    public function parent_category()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }
}
