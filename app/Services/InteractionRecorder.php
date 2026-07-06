<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostInteraction;
use App\Models\UserAffinity;
use Illuminate\Support\Facades\DB;

/**
 * Records a user/post interaction and atomically updates the rolling affinity
 * scores it implies. One call covers both the raw event log row and every
 * dimension (category/tag/author/type/language/country) that the post belongs to.
 *
 * Designed to be cheap enough to call inline from the request lifecycle —
 * for very high volumes the raw write can be queued, but affinity updates
 * are intentionally synchronous so the next /api/feed call reflects them.
 */
class InteractionRecorder
{
    /**
     * Record a single event. $context lets callers pass `dwell_ms` for dwell
     * events or override the base weight (e.g. for batch impression catch-up).
     *
     * @param  array{dwell_ms?: int|null, weight?: float|null} $context
     */
    public function record(int $userId, int $postId, string $event, array $context = []): void
    {
        $eventWeights = config('feed.event_weights');
        if (!array_key_exists($event, $eventWeights)) {
            return; // unknown event — silently ignore so a typo never throws on prod
        }

        $baseWeight = $context['weight'] ?? $eventWeights[$event];
        $dwellMs    = $context['dwell_ms'] ?? null;

        // Dwell scales with time spent: 0→1 over 0–30s.
        if ($event === 'dwell' && $dwellMs) {
            $factor = min(1.0, max(0.0, $dwellMs / 30000));
            $baseWeight = $baseWeight * $factor;
            if ($baseWeight <= 0.01) {
                return; // negligibly short dwell — don't pollute the log
            }
        }

        $post = Post::select('id', 'user_id', 'category_id', 'type', 'language')
            ->with(['tags:id'])
            ->find($postId);
        if (!$post) return;

        DB::transaction(function () use ($userId, $post, $event, $baseWeight, $dwellMs) {
            PostInteraction::create([
                'user_id'    => $userId,
                'post_id'    => $post->id,
                'event'      => $event,
                'weight'     => $baseWeight,
                'dwell_ms'   => $dwellMs,
                'created_at' => now(),
            ]);

            // Skip affinity update for impressions only when weight is near-zero.
            if (abs($baseWeight) < 0.01) return;

            $this->bumpAffinity($userId, UserAffinity::DIM_AUTHOR, (string) $post->user_id, $baseWeight);
            if ($post->category_id) {
                $this->bumpAffinity($userId, UserAffinity::DIM_CATEGORY, (string) $post->category_id, $baseWeight);
            }
            if ($post->type) {
                $this->bumpAffinity($userId, UserAffinity::DIM_TYPE, $post->type, $baseWeight * 0.6);
            }
            if ($post->language) {
                $this->bumpAffinity($userId, UserAffinity::DIM_LANGUAGE, $post->language, $baseWeight * 0.4);
            }
            foreach ($post->tags as $tag) {
                // Tag signal is split across the post's tags so a 10-tag post
                // doesn't dominate a 1-tag post for the same weight.
                $perTag = $baseWeight / max(1, count($post->tags));
                $this->bumpAffinity($userId, UserAffinity::DIM_TAG, (string) $tag->id, $perTag);
            }
        });
    }

    /**
     * Bulk impression recording — used by the IntersectionObserver batch in JS.
     * Skips posts already impressed within the TTL window so a user scrolling
     * up and down doesn't inflate the affinity score.
     */
    public function recordImpressions(int $userId, array $postIds): void
    {
        if (empty($postIds)) return;

        $ttlDays = config('feed.impression_ttl_days');
        $since   = now()->subDays($ttlDays);

        $alreadySeen = DB::table('feed_impressions')
            ->where('user_id', $userId)
            ->whereIn('post_id', $postIds)
            ->where('seen_at', '>=', $since)
            ->pluck('post_id')
            ->all();
        $seenSet = array_flip($alreadySeen);

        $fresh = array_filter($postIds, fn ($id) => !isset($seenSet[$id]));

        // views_count bumps on EVERY impression batch received from the client.
        // The client already de-dupes within a page session (reportedImpressions
        // Set in home.js), so the natural cadence is "one view per post per
        // page-load that the card was actually scrolled into view". The TTL
        // dedup below only applies to the affinity signal — we don't want one
        // user's repeated scrolling to spam the personalization scores.
        // FeedRanker excludes posts authored by $userId, so authors never
        // self-view via their own feed.
        DB::table('posts')->whereIn('id', array_values($postIds))->increment('views_count');

        if (empty($fresh)) {
            // Just bump shown_count on the dupes so we know the user re-encountered them.
            DB::table('feed_impressions')
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->increment('shown_count');
            return;
        }

        $now = now();
        $rows = [];
        foreach ($fresh as $pid) {
            $rows[] = [
                'user_id'     => $userId,
                'post_id'     => $pid,
                'seen_at'     => $now,
                'shown_count' => 1,
            ];
        }
        // upsert keeps the primary-key uniqueness guarantee
        DB::table('feed_impressions')->upsert(
            $rows,
            ['user_id', 'post_id'],
            ['seen_at', 'shown_count']
        );

        // Record the (weak) impression signal for affinity. Done one by one
        // because each post hits multiple dimensions — the per-row cost is
        // dominated by the affinity bumps, not the insert.
        foreach ($fresh as $pid) {
            $this->record($userId, $pid, 'impression');
        }
    }

    /**
     * Atomically additive upsert on user_affinity. Uses raw SQL because Eloquent's
     * composite-PK support is awkward, and we want the increment to be one round
     * trip rather than read-modify-write.
     */
    private function bumpAffinity(int $userId, string $dimension, string $value, float $delta): void
    {
        DB::statement(
            'INSERT INTO user_affinity (user_id, dimension, dimension_value, score, events, updated_at)
             VALUES (?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE score = score + VALUES(score), events = events + 1, updated_at = VALUES(updated_at)',
            [$userId, $dimension, $value, $delta, now()]
        );
    }
}
