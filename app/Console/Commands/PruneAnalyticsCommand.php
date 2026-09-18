<?php

namespace App\Console\Commands;

use App\Services\AnalyticsAggregator;
use Illuminate\Console\Command;

class PruneAnalyticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:prune
                            {--dry-run : Preview aggregation and pruning counts without modifying data}
                            {--hours= : Override page views retention window in hours (default from config)}
                            {--visitors-hours= : Override visitors retention window in hours (default from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate statistical tables into lean summary tables and prune expired raw records';

    public function handle(AnalyticsAggregator $aggregator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pvHours = $this->option('hours') ? (int) $this->option('hours') : null;
        $visHours = $this->option('visitors-hours') ? (int) $this->option('visitors-hours') : null;

        $this->info($dryRun ? '🔍 [DRY RUN] Inspecting analytics data for aggregation and pruning...' : '🚀 Starting analytics aggregation and cleanup...');

        // 1. visitor_page_views
        $this->line('Processing visitor_page_views...');
        $pv = $aggregator->aggregateAndPrunePageViews($pvHours, $dryRun);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Daily Traffic Rollups', $pv['summarized_days']],
                ['Daily Page Rollups', $pv['summarized_pages']],
                ['Raw Page Views Pruned', $pv['pruned_rows']],
            ]
        );

        // 2. visitors
        $this->line('Processing visitors...');
        $vis = $aggregator->aggregateAndPruneVisitors($visHours, $dryRun);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Country Summaries Aggregated', $vis['countries_aggregated']],
                ['Referrer Summaries Aggregated', $vis['referrers_aggregated']],
                ['Device Summaries Aggregated', $vis['devices_aggregated']],
                ['Raw Visitors Pruned', $vis['pruned_rows']],
            ]
        );

        // 3. article_country_views
        $this->line('Processing article_country_views...');
        $art = $aggregator->archiveAndPruneArticleCountryViews(null, $dryRun);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Inactive Article Country Views Archived', $art['archived_rows']],
                ['Raw Article Country Views Pruned', $art['pruned_rows']],
            ]
        );

        // 4. Temporary tables (guest impressions, user impressions, interactions)
        $this->line('Cleaning temporary impression & interaction tables...');
        $temp = $aggregator->pruneTemporaryTables($dryRun);
        $this->table(
            ['Table', 'Rows Pruned'],
            [
                ['guest_feed_impressions', $temp['guest_impressions_pruned']],
                ['feed_impressions', $temp['feed_impressions_pruned']],
                ['post_interactions', $temp['post_interactions_pruned']],
            ]
        );

        $totalPruned = $pv['pruned_rows'] + $vis['pruned_rows'] + $art['pruned_rows']
            + $temp['guest_impressions_pruned'] + $temp['feed_impressions_pruned'] + $temp['post_interactions_pruned'];

        $this->info($dryRun
            ? "✨ [DRY RUN] Inspection complete. Total candidate rows for pruning: {$totalPruned}."
            : "✅ Analytics aggregation & cleanup complete! Total {$totalPruned} raw rows pruned across all tables."
        );

        return self::SUCCESS;
    }
}
