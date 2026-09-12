<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'event',
        'auditable_type',
        'auditable_id',
        'actor_type',
        'actor_id',
        'actor_label',
        'ip_address',
        'user_agent',
        'url',
        'http_method',
        'old_values',
        'new_values',
        'context',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
