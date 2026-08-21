<?php

namespace App\Services;

use App\Models\CrawlBrokenLink;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Crawls same-host pages breadth-first starting from APP_URL, following
 * internal HTML links and spot-checking everything else (external links,
 * internal assets) once. Only ever issues GET requests, so it never touches
 * the app's POST/DELETE-guarded destructive actions.
 *
 * Note: this app requires auth on nearly every route, so an anonymous crawl
 * mostly exercises the login page + any explicitly public/webhook routes.
 * Deeper coverage would need an authenticated crawl session (not built here).
 */
class BrokenLinkCrawlerService
{
    protected const ASSET_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
        'css', 'js', 'json', 'xml', 'csv',
        'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx',
        'woff', 'woff2', 'ttf', 'eot', 'mp4', 'mp3',
    ];

    protected const EXTRA_CHECK_CAP = 300;

    public function crawl(int $maxPages = 100): CrawlRun
    {
        $run = CrawlRun::create(['status' => 'running', 'started_at' => now()]);

        $baseUrl = rtrim((string) config('app.url'), '/');
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        $toVisit = [['url' => $baseUrl, 'referrer' => $baseUrl]];
        $queued = [$this->normalize($baseUrl) => true];
        $visited = [];
        $checkedLinks = [];

        $pagesCrawled = 0;
        $linksChecked = 0;
        $brokenCount = 0;

        try {
            while (!empty($toVisit) && $pagesCrawled < $maxPages) {
                $item = array_shift($toVisit);
                $url = $item['url'];
                $key = $this->normalize($url);

                if (isset($visited[$key])) {
                    continue;
                }
                $visited[$key] = true;

                [$ok, $statusCode, $error, $body] = $this->fetch($url);
                $pagesCrawled++;

                if (!$ok) {
                    CrawlBrokenLink::create([
                        'crawl_run_id' => $run->id,
                        'page_url' => $item['referrer'],
                        'link_url' => $url,
                        'status_code' => $statusCode,
                        'error_message' => $error,
                    ]);
                    $brokenCount++;
                    continue;
                }

                foreach ($this->extractLinks($body, $url) as $link) {
                    $linkHost = parse_url($link, PHP_URL_HOST);
                    $linkKey = $this->normalize($link);

                    $isSameHost = $linkHost === null || $linkHost === $baseHost;
                    $isHtmlish = !$this->hasAssetExtension($link);

                    if ($isSameHost && $isHtmlish) {
                        if (!isset($queued[$linkKey]) && count($queued) < $maxPages) {
                            $queued[$linkKey] = true;
                            $toVisit[] = ['url' => $link, 'referrer' => $url];
                        }
                        continue;
                    }

                    // One-off spot check for external links / internal assets.
                    if (isset($checkedLinks[$linkKey]) || $linksChecked >= self::EXTRA_CHECK_CAP) {
                        continue;
                    }
                    $checkedLinks[$linkKey] = true;
                    $linksChecked++;

                    [$linkOk, $linkStatus, $linkError] = $this->fetch($link, quick: true);

                    if (!$linkOk) {
                        CrawlBrokenLink::create([
                            'crawl_run_id' => $run->id,
                            'page_url' => $url,
                            'link_url' => $link,
                            'status_code' => $linkStatus,
                            'error_message' => $linkError,
                        ]);
                        $brokenCount++;
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'pages_crawled' => $pagesCrawled,
                'links_checked' => $linksChecked,
                'broken_count' => $brokenCount,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'pages_crawled' => $pagesCrawled,
                'links_checked' => $linksChecked,
                'broken_count' => $brokenCount,
            ]);
        }

        return $run;
    }

    /**
     * @return array{0: bool, 1: ?int, 2: ?string, 3: ?string} [ok, statusCode, errorMessage, body]
     */
    protected function fetch(string $url, bool $quick = false): array
    {
        try {
            $response = Http::timeout($quick ? 5 : 8)
                ->withUserAgent('Laravel Broken Link Crawler')
                ->get($url);

            if ($response->status() >= 400) {
                return [false, $response->status(), "HTTP status {$response->status()}", null];
            }

            return [true, $response->status(), null, $quick ? null : $response->body()];
        } catch (Throwable $e) {
            return [false, null, $e->getMessage(), null];
        }
    }

    protected function extractLinks(?string $html, string $pageUrl): array
    {
        if (!$html) {
            return [];
        }

        $links = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }

            $resolved = $this->resolveUrl($href, $pageUrl);

            if ($resolved) {
                $links[] = $resolved;
            }
        }

        return array_unique($links);
    }

    protected function resolveUrl(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $this->normalize($href, forOutput: true);
        }

        $baseParts = parse_url($base);
        if (!$baseParts || !isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $origin = $baseParts['scheme'] . '://' . $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $this->normalize($baseParts['scheme'] . ':' . $href, forOutput: true);
        }

        if (str_starts_with($href, '/')) {
            return $this->normalize($origin . $href, forOutput: true);
        }

        // Relative to the current page's directory.
        $basePath = $baseParts['path'] ?? '/';
        $dir = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';

        return $this->normalize($origin . $dir . $href, forOutput: true);
    }

    protected function hasAssetExtension(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::ASSET_EXTENSIONS, true);
    }

    /**
     * Strip the fragment so #anchors on the same page don't count as
     * separate URLs, and lightly clean up trailing slashes for de-dupe.
     */
    protected function normalize(string $url, bool $forOutput = false): string
    {
        $url = explode('#', $url, 2)[0];

        return $forOutput ? $url : rtrim($url, '/');
    }
}
