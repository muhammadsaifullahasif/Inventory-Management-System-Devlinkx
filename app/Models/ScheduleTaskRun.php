<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleTaskRun extends Model
{
    protected $fillable = [
        'command',
        'status',
        'last_ran_at',
        'runtime_ms',
    ];

    protected $casts = [
        'last_ran_at' => 'datetime',
    ];
}
