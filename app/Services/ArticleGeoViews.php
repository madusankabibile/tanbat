<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records and reads per-country article view counts (article_country_views).
 *
 * Recording is a single atomic INSERT … ON DUPLICATE KEY UPDATE so it stays
 * cheap on the article-render path and never double-inserts under concurrency.
 * Views from IPs we can't geolocate (localhost / private / API miss) are simply
 * not attributed to any country — the global posts.views_count still covers them.
 */
class ArticleGeoViews
{
    public function __construct(private GeoLocator $geo) {}

    /**
     * Attribute one view of an article to the current request's country.
     * No-op (returns null) when the country can't be resolved.
     */
    public function record(int $postId, Request $request): ?string
    {
        $country = $this->geo->country($request);
        if (!$country) {
            return null;
        }
        $this->increment($postId, $country);
        return $country;
    }

    /** Atomically bump the (post, country) counter, inserting the row if new. */
    public function increment(int $postId, string $countryCode): void
    {
        $now = now();
        try {
            DB::insert(
                'INSERT INTO article_country_views
                    (post_id, country_code, views, last_viewed_at, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    views = views + 1,
                    last_viewed_at = VALUES(last_viewed_at),
                    updated_at = VALUES(updated_at)',
                [$postId, $countryCode, $now, $now, $now]
            );
        } catch (\Throwable $e) {
            // View accounting must never break rendering an article.
        }
    }
}
