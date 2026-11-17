<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSavedReply extends Model
{
    protected $fillable = [
        'title',
        'body',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Fill {buyer_name}, {item_title} and {order_number} placeholders.
     * Unknown placeholders are left untouched.
     */
    public function render(array $vars = []): string
    {
        $replace = [];
        foreach (['buyer_name', 'item_title', 'order_number'] as $key) {
            if (isset($vars[$key])) {
                $replace['{' . $key . '}'] = (string) $vars[$key];
            }
        }

        return strtr($this->body, $replace);
    }
}
