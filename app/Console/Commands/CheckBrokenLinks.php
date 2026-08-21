<?php

namespace App\Console\Commands;

use App\Services\BrokenLinkCrawlerService;
use Illuminate\Console\Command;

class CheckBrokenLinks extends Command
{
    protected $signature = 'crawler:check-broken-links {--limit=100 : Max number of same-host pages to crawl}';

    protected $description = 'Crawl the app for broken internal/external links';

    public function handle(BrokenLinkCrawlerService $service): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Crawling up to {$limit} pages...");

        $run = $service->crawl($limit);

        $this->info("Crawl {$run->status}: {$run->pages_crawled} page(s) crawled, {$run->links_checked} link(s) checked, {$run->broken_count} broken.");

        return $run->status === 'failed' ? 1 : 0;
    }
}
