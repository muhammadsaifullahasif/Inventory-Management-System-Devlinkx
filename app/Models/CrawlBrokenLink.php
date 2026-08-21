<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlBrokenLink extends Model
{
    protected $fillable = [
        'crawl_run_id',
        'page_url',
        'link_url',
        'status_code',
        'error_message',
    ];

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class);
    }
}
