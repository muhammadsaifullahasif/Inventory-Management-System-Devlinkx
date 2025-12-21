<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function parent_category()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }
}
