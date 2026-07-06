<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds user_affinity from scratch using the raw signals we already store:
 *   - post_likes
 *   - comments
 *   - post_shares
 *   - post_interactions  (impression / click / dwell — when present)
 *   - follows            (followed authors get a baseline author affinity)
 *
 * Time-decayed via an exponential half-life so older signals fade. Safe to run
 * periodically (e.g. nightly) without losing recent fresh interactions —
 * InteractionRecorder writes deltas; this resets and rebuilds the totals.
 *
 * Also used by the artisan backfill command to seed the table on first deploy.
 */
class AffinityCalculator
{
    public function rebuildForUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            DB::table('user_affinity')->where('user_id', $userId)->delete();

            $this->ingestLikes($userId);
            $this->ingestComments($userId);
            $this->ingestShares($userId);
            $this->ingestInteractions($userId);
            $this->ingestFollows($userId);
        });
    }

    public function rebuildAll(): int
    {
        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $id) {
            $this->rebuildForUser($id);
        }
        return count($userIds);
    }

    /** like / comment / share / impression / click / dwell live in different tables
     *  but the math is identical — fan out to each post's dimensions with a
     *  time-decayed signal weight. */
    private function ingestLikes(int $userId): void
    {
        $rows = DB::table('post_likes')
            ->select('post_id', 'created_at')
            ->where('user_id', $userId)
            ->get();
        foreach ($rows as $r) {
            $this->fanOut($userId, $r->post_id, config('feed.event_weights.like'), $r->created_at);
        }
    }

    private function ingestComments(int $userId): void
    {
        $rows = DB::table('comments')
            ->select('post_id', 'created_at')
            ->where('user_id', $userId)
            ->get();
        foreach ($rows as $r) {
            $this->fanOut($userId, $r->post_id, config('feed.event_weights.comment'), $r->created_at);
        }
    }

    private function ingestShares(int $userId): void
    {
        $rows = DB::table('post_shares')
            ->select('post_id', 'created_at')
            ->where('user_id', $userId)
            ->get();
        foreach ($rows as $r) {
            $this->fanOut($userId, $r->post_id, config('feed.event_weights.share'), $r->created_at);
        }
    }

    private function ingestInteractions(int $userId): void
    {
        // post_interactions already has the per-event weight applied
        $rows = DB::table('post_interactions')
            ->select('post_id', 'weight', 'event', 'created_at')
            ->where('user_id', $userId)
            // Skip the structured ones we've already covered to avoid double-counting
            ->whereNotIn('event', ['like', 'unlike', 'comment', 'share'])
            ->get();
        foreach ($rows as $r) {
            $this->fanOut($userId, $r->post_id, (float) $r->weight, $r->created_at);
        }
    }

    private function ingestFollows(int $userId): void
    {
        // A follow is a strong "I want more from this author" signal.
        // Treat it as a flat author affinity bump (no decay — follows are sticky).
        $followed = DB::table('follows')
            ->where('follower_id', $userId)
            ->pluck('following_id');
        foreach ($followed as $authorId) {
            $this->upsert($userId, 'author', (string) $authorId, 3.0);
        }
    }

    private function fanOut(int $userId, int $postId, float $weight, $createdAt): void
    {
        $post = Post::select('id', 'user_id', 'category_id', 'type', 'language')
            ->with(['tags:id'])
            ->find($postId);
        if (!$post) return;

        $decayed = $weight * $this->decay($createdAt);
        if (abs($decayed) < 0.005) return;

        $this->upsert($userId, 'author',   (string) $post->user_id, $decayed);
        if ($post->category_id) $this->upsert($userId, 'category', (string) $post->category_id, $decayed);
        if ($post->type)        $this->upsert($userId, 'type',     $post->type,                  $decayed * 0.6);
        if ($post->language)    $this->upsert($userId, 'language', $post->language,              $decayed * 0.4);

        $tags = $post->tags;
        if ($tags->count()) {
            $perTag = $decayed / $tags->count();
            foreach ($tags as $tag) {
                $this->upsert($userId, 'tag', (string) $tag->id, $perTag);
            }
        }
    }

    private function decay($createdAt): float
    {
        if (!$createdAt) return 1.0;
        $ts = is_string($createdAt) ? strtotime($createdAt) : $createdAt->getTimestamp();
        $days = max(0, (time() - $ts) / 86400);
        $halfLife = (float) config('feed.affinity_half_life_days');
        return pow(0.5, $days / $halfLife);
    }

    private function upsert(int $userId, string $dim, string $val, float $delta): void
    {
        DB::statement(
            'INSERT INTO user_affinity (user_id, dimension, dimension_value, score, events, updated_at)
             VALUES (?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE score = score + VALUES(score), events = events + 1, updated_at = VALUES(updated_at)',
            [$userId, $dim, $val, $delta, now()]
        );
    }
}
