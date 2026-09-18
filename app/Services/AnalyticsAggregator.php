<?php

namespace App\Services;

use App\Models\Visitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsAggregator
{
    /**
     * Run all aggregation and pruning tasks.
     *
     * @return array<string, array<string, int>>
     */
    public function runAll(bool $dryRun = false): array
    {
        $results = [
            'visitor_page_views' => $this->aggregateAndPrunePageViews(null, $dryRun),
            'visitors'           => $this->aggregateAndPruneVisitors(null, $dryRun),
            'article_views'      => $this->archiveAndPruneArticleCountryViews(null, $dryRun),
            'temporary_tables'   => $this->pruneTemporaryTables($dryRun),
        ];

        Log::info('Analytics aggregation & pruning completed', $results);

        return $results;
    }

    /**
     * Aggregate completed days from `visitor_page_views` into `daily_traffic_summaries`
     * and `daily_page_summaries`, then chunk-delete the raw rows.
     *
     * @return array{summarized_days: int, summarized_pages: int, pruned_rows: int}
     */
    public function aggregateAndPrunePageViews(?int $retentionHours = null, bool $dryRun = false): array
    {
        $hours = $retentionHours ?? (int) config('analytics.page_views_retention_hours', 24);
        $chunkSize = (int) config('analytics.chunk_size', 5000);

        // Raw page views bucket by 'day' (DATE). We aggregate all finished days older than 24h
        // or strictly prior to today, keeping today's active views raw.
        $cutoffDay = min(now()->subHours($hours)->toDateString(), now()->toDateString());

        // Count pending raw rows
        $pendingCount = DB::table('visitor_page_views')
            ->where('day', '<', $cutoffDay)
            ->count();

        if ($pendingCount === 0) {
            return ['summarized_days' => 0, 'summarized_pages' => 0, 'pruned_rows' => 0];
        }

        if ($dryRun) {
            return ['summarized_days' => 0, 'summarized_pages' => 0, 'pruned_rows' => $pendingCount];
        }

        $now = now();

        // 1. Rollup daily traffic totals per (day, host)
        DB::statement(
            'INSERT INTO daily_traffic_summaries (day, host, views, uniques, created_at, updated_at)
             SELECT day, host, SUM(hits) AS views, COUNT(DISTINCT visitor_token) AS uniques, ?, ?
             FROM visitor_page_views
             WHERE day < ?
             GROUP BY day, host
             ON DUPLICATE KEY UPDATE
                 views = VALUES(views),
                 uniques = VALUES(uniques),
                 updated_at = VALUES(updated_at)',
            [$now, $now, $cutoffDay]
        );

        // 2. Rollup per-page daily totals per (day, host, path)
        DB::statement(
            'INSERT INTO daily_page_summaries (day, host, path, views, uniques, created_at, updated_at)
             SELECT day, host, path, SUM(hits) AS views, COUNT(DISTINCT visitor_token) AS uniques, ?, ?
             FROM visitor_page_views
             WHERE day < ?
             GROUP BY day, host, path
             ON DUPLICATE KEY UPDATE
                 views = VALUES(views),
                 uniques = VALUES(uniques),
                 updated_at = VALUES(updated_at)',
            [$now, $now, $cutoffDay]
        );

        // 3. Prune the raw rows in chunks to prevent database lockups
        $pruned = $this->chunkDelete('visitor_page_views', 'day < ?', [$cutoffDay], $chunkSize);

        $summarizedDays = DB::table('daily_traffic_summaries')->where('day', '<', $cutoffDay)->count();
        $summarizedPages = DB::table('daily_page_summaries')->where('day', '<', $cutoffDay)->count();

        return [
            'summarized_days'  => $summarizedDays,
            'summarized_pages' => $summarizedPages,
            'pruned_rows'      => $pruned,
        ];
    }

    /**
     * Aggregate old records from `visitors` into `daily_country_summaries`,
     * `daily_referrer_summaries`, and `daily_device_summaries`, then chunk-delete.
     *
     * @return array{countries_aggregated: int, referrers_aggregated: int, devices_aggregated: int, pruned_rows: int}
     */
    public function aggregateAndPruneVisitors(?int $retentionHours = null, bool $dryRun = false): array
    {
        $hours = $retentionHours ?? (int) config('analytics.visitors_retention_hours', 48);
        $chunkSize = (int) config('analytics.chunk_size', 5000);
        $cutoff = now()->subHours($hours);

        $pendingCount = DB::table('visitors')
            ->where('updated_at', '<', $cutoff)
            ->count();

        if ($pendingCount === 0) {
            return [
                'countries_aggregated' => 0,
                'referrers_aggregated' => 0,
                'devices_aggregated'   => 0,
                'pruned_rows'          => 0,
            ];
        }

        if ($dryRun) {
            return [
                'countries_aggregated' => 0,
                'referrers_aggregated' => 0,
                'devices_aggregated'   => 0,
                'pruned_rows'          => $pendingCount,
            ];
        }

        $now = now();

        // 1. Rollup Countries
        DB::statement(
            "INSERT INTO daily_country_summaries (day, host, country_code, country_name, visitors, hits, created_at, updated_at)
             SELECT DATE(updated_at) AS day, host,
                    COALESCE(NULLIF(country_code, ''), 'XX') AS country_code,
                    MAX(COALESCE(NULLIF(country_name, ''), 'Unknown')) AS country_name,
                    COUNT(*) AS visitors,
                    SUM(hits) AS hits,
                    ?, ?
             FROM visitors
             WHERE updated_at < ?
             GROUP BY DATE(updated_at), host, country_code
             ON DUPLICATE KEY UPDATE
                 visitors = visitors + VALUES(visitors),
                 hits = hits + VALUES(hits),
                 updated_at = VALUES(updated_at)",
            [$now, $now, $cutoff]
        );

        // 2. Rollup Referrers in cursor/batches
        $referrersAggregated = 0;
        DB::table('visitors')
            ->where('updated_at', '<', $cutoff)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($now, &$referrersAggregated) {
                $grouped = [];
                foreach ($rows as $r) {
                    $refHost = parse_url($r->referrer, PHP_URL_HOST) ?: $r->referrer;
                    $refHost = substr(strtolower($refHost), 0, 150);
                    $targetPage = substr($r->page ?: '/', 0, 150);
                    $day = Carbon::parse($r->updated_at)->toDateString();
                    $host = (string) ($r->host ?? '');
                    $key = "{$day}|{$host}|{$refHost}|{$targetPage}";

                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'day'           => $day,
                            'host'          => $host,
                            'referrer_host' => $refHost,
                            'referrer_url'  => substr($r->referrer, 0, 255),
                            'target_page'   => $targetPage,
                            'visitors'      => 0,
                            'hits'          => 0,
                        ];
                    }
                    $grouped[$key]['visitors']++;
                    $grouped[$key]['hits'] += (int) $r->hits;
                }

                foreach ($grouped as $entry) {
                    DB::statement(
                        'INSERT INTO daily_referrer_summaries (day, host, referrer_host, referrer_url, target_page, visitors, hits, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             visitors = visitors + VALUES(visitors),
                             hits = hits + VALUES(hits),
                             updated_at = VALUES(updated_at)',
                        [
                            $entry['day'],
                            $entry['host'],
                            $entry['referrer_host'],
                            $entry['referrer_url'],
                            $entry['target_page'],
                            $entry['visitors'],
                            $entry['hits'],
                            $now,
                            $now,
                        ]
                    );
                    $referrersAggregated++;
                }
            });

        // 3. Rollup Devices and Browsers
        $devicesAggregated = 0;
        DB::table('visitors')
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($now, &$devicesAggregated) {
                $grouped = [];
                foreach ($rows as $r) {
                    $parsed = UserAgentParser::parse($r->user_agent);
                    $day = Carbon::parse($r->updated_at)->toDateString();
                    $host = (string) ($r->host ?? '');
                    $device = substr($parsed['device'], 0, 50);
                    $browser = substr($parsed['browser'], 0, 50);
                    $isBot = $parsed['is_bot'] ? 1 : 0;
                    $key = "{$day}|{$host}|{$device}|{$browser}|{$isBot}";

                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'day'      => $day,
                            'host'     => $host,
                            'device'   => $device,
                            'browser'  => $browser,
                            'is_bot'   => $isBot,
                            'visitors' => 0,
                        ];
                    }
                    $grouped[$key]['visitors']++;
                }

                foreach ($grouped as $entry) {
                    DB::statement(
                        'INSERT INTO daily_device_summaries (day, host, device, browser, is_bot, visitors, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             visitors = visitors + VALUES(visitors),
                             updated_at = VALUES(updated_at)',
                        [
                            $entry['day'],
                            $entry['host'],
                            $entry['device'],
                            $entry['browser'],
                            $entry['is_bot'],
                            $entry['visitors'],
                            $now,
                            $now,
                        ]
                    );
                    $devicesAggregated++;
                }
            });

        // 4. Prune old raw visitor records in chunks
        $pruned = $this->chunkDelete('visitors', 'updated_at < ?', [$cutoff], $chunkSize);

        return [
            'countries_aggregated' => $pendingCount,
            'referrers_aggregated' => $referrersAggregated,
            'devices_aggregated'   => $devicesAggregated,
            'pruned_rows'          => $pruned,
        ];
    }

    /**
     * Archive inactive article country view records older than X days and prune them.
     *
     * @return array{archived_rows: int, pruned_rows: int}
     */
    public function archiveAndPruneArticleCountryViews(?int $inactivityDays = null, bool $dryRun = false): array
    {
        $days = $inactivityDays ?? (int) config('analytics.article_views_inactivity_days', 90);
        $chunkSize = (int) config('analytics.chunk_size', 5000);
        $cutoff = now()->subDays($days);

        $pendingCount = DB::table('article_country_views')
            ->where(function ($q) use ($cutoff) {
                $q->where('last_viewed_at', '<', $cutoff)
                  ->orWhere(function ($sub) use ($cutoff) {
                      $sub->whereNull('last_viewed_at')
                          ->where('updated_at', '<', $cutoff);
                  });
            })
            ->count();

        if ($pendingCount === 0) {
            return ['archived_rows' => 0, 'pruned_rows' => 0];
        }

        if ($dryRun) {
            return ['archived_rows' => $pendingCount, 'pruned_rows' => $pendingCount];
        }

        $now = now();

        // 1. Copy to archive table with upsert
        DB::statement(
            'INSERT INTO article_country_view_summaries (post_id, country_code, views, last_viewed_at, archived_at)
             SELECT post_id, country_code, views, last_viewed_at, ?
             FROM article_country_views
             WHERE last_viewed_at < ? OR (last_viewed_at IS NULL AND updated_at < ?)
             ON DUPLICATE KEY UPDATE
                 views = views + VALUES(views),
                 last_viewed_at = COALESCE(VALUES(last_viewed_at), article_country_view_summaries.last_viewed_at),
                 archived_at = VALUES(archived_at)',
            [$now, $cutoff, $cutoff]
        );

        // 2. Prune inactive rows in chunks
        $pruned = $this->chunkDelete(
            'article_country_views',
            'last_viewed_at < ? OR (last_viewed_at IS NULL AND updated_at < ?)',
            [$cutoff, $cutoff],
            $chunkSize
        );

        return [
            'archived_rows' => $pendingCount,
            'pruned_rows'   => $pruned,
        ];
    }

    /**
     * Clean up temporary impression & interaction tables that are only used for short windows.
     *
     * @return array{guest_impressions_pruned: int, feed_impressions_pruned: int, post_interactions_pruned: int}
     */
    public function pruneTemporaryTables(bool $dryRun = false): array
    {
        $chunkSize = (int) config('analytics.chunk_size', 5000);

        $guestHours = (int) config('analytics.guest_impressions_retention_hours', 24);
        $feedDays   = (int) config('analytics.feed_impressions_retention_days', 14);
        $postDays   = (int) config('analytics.post_interactions_retention_days', 30);

        $guestCutoff = now()->subHours($guestHours);
        $feedCutoff  = now()->subDays($feedDays);
        $postCutoff  = now()->subDays($postDays);

        if ($dryRun) {
            return [
                'guest_impressions_pruned' => DB::table('guest_feed_impressions')->where('seen_at', '<', $guestCutoff)->count(),
                'feed_impressions_pruned'  => DB::table('feed_impressions')->where('seen_at', '<', $feedCutoff)->count(),
                'post_interactions_pruned' => DB::table('post_interactions')->where('created_at', '<', $postCutoff)->count(),
            ];
        }

        $guestPruned = $this->chunkDelete('guest_feed_impressions', 'seen_at < ?', [$guestCutoff], $chunkSize);
        $feedPruned  = $this->chunkDelete('feed_impressions', 'seen_at < ?', [$feedCutoff], $chunkSize);
        $postPruned  = $this->chunkDelete('post_interactions', 'created_at < ?', [$postCutoff], $chunkSize);

        return [
            'guest_impressions_pruned' => $guestPruned,
            'feed_impressions_pruned'  => $feedPruned,
            'post_interactions_pruned' => $postPruned,
        ];
    }

    /**
     * Delete rows in small batches using raw SQL LIMIT to avoid table locks.
     */
    private function chunkDelete(string $table, string $whereCondition, array $bindings, int $chunkSize = 5000): int
    {
        $totalDeleted = 0;

        do {
            $deleted = DB::delete(
                "DELETE FROM {$table} WHERE {$whereCondition} LIMIT {$chunkSize}",
                $bindings
            );
            $totalDeleted += $deleted;
        } while ($deleted >= $chunkSize);

        return $totalDeleted;
    }
}
