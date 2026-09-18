<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Raw Page Views Retention (Hours)
     |--------------------------------------------------------------------------
     | How many hours of raw `visitor_page_views` records to keep. Default: 24.
     | Records older than this threshold are aggregated into `daily_traffic_summaries`
     | and `daily_page_summaries` before being pruned.
     */
    'page_views_retention_hours' => (int) env('ANALYTICS_PAGE_VIEWS_RETENTION_HOURS', 24),

    /*
     |--------------------------------------------------------------------------
     | Raw Visitors Retention (Hours)
     |--------------------------------------------------------------------------
     | How many hours of raw `visitors` records (IPs, user agents, live session
     | rows) to keep. Default: 48 hours. Older visitor records are aggregated into
     | `daily_country_summaries`, `daily_referrer_summaries`, and
     | `daily_device_summaries` before being safely pruned.
     */
    'visitors_retention_hours' => (int) env('ANALYTICS_VISITORS_RETENTION_HOURS', 48),

    /*
     |--------------------------------------------------------------------------
     | Article Country Views Inactivity Threshold (Days)
     |--------------------------------------------------------------------------
     | Articles with no views from a country for this many days are archived into
     | `article_country_view_summaries` and deleted from `article_country_views`.
     */
    'article_views_inactivity_days' => (int) env('ANALYTICS_ARTICLE_INACTIVITY_DAYS', 90),

    /*
     |--------------------------------------------------------------------------
     | Guest Feed Impressions Retention (Hours)
     |--------------------------------------------------------------------------
     | Temporary guest rotating feed impression hashes older than this are pruned.
     */
    'guest_impressions_retention_hours' => (int) env('ANALYTICS_GUEST_IMPRESSIONS_HOURS', 24),

    /*
     |--------------------------------------------------------------------------
     | Feed Impressions Retention (Days)
     |--------------------------------------------------------------------------
     | User feed impression history beyond this window is pruned.
     */
    'feed_impressions_retention_days' => (int) env('ANALYTICS_FEED_IMPRESSIONS_DAYS', 14),

    /*
     |--------------------------------------------------------------------------
     | Post Interactions Retention (Days)
     |--------------------------------------------------------------------------
     | Raw scroll/click/dwell interaction events older than this are pruned.
     */
    'post_interactions_retention_days' => (int) env('ANALYTICS_POST_INTERACTIONS_DAYS', 30),

    /*
     |--------------------------------------------------------------------------
     | Pruning Chunk Size
     |--------------------------------------------------------------------------
     | Maximum number of rows to delete per query to prevent MySQL table locking.
     */
    'chunk_size' => (int) env('ANALYTICS_CHUNK_SIZE', 5000),
];
