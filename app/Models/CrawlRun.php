<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlRun extends Model
{
    protected $fillable = [
        'status',
        'pages_crawled',
        'links_checked',
        'broken_count',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function brokenLinks(): HasMany
    {
        return $this->hasMany(CrawlBrokenLink::class);
    }
}
