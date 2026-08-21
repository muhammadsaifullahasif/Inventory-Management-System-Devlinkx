<?php

namespace App\Jobs;

use App\Services\BrokenLinkCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BrokenLinkCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 1700;

    public function __construct(protected int $maxPages = 100) {}

    public function handle(BrokenLinkCrawlerService $service): void
    {
        $service->crawl($this->maxPages);
    }
}
