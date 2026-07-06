<?php

namespace App\Http\Controllers;

use App\Models\BookDetail;
use App\Services\PinterestPoster;
use App\Services\RedditPoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Soft scheduler — driven by the layout's JS heartbeat from authenticated
 * users' browsers. Provides an alternative trigger for the bot:post-*
 * commands when the host's system cron isn't reliably firing.
 *
 * The original Laravel cron schedule still lives in App\Console\Kernel; this
 * is just a parallel driver. Each ping runs AT MOST one due bot so the
 * request stays fast. Concurrent pings from multiple users are coalesced
 * via a short cache lock.
 */
class TaskRunnerController extends Controller
{
    /** Cadence per bot, in seconds. Mirrors App\Console\Kernel. */
    private array $jobs = [
        'meme'  => ['cmd' => 'bot:post-meme',  'every' => 50 * 60],
        'video' => ['cmd' => 'bot:post-video', 'every' => 90 * 60],
        'news'  => ['cmd' => 'bot:post-news',  'every' => 120 * 60],
        'ad'    => ['cmd' => 'bot:post-ad',    'every' => 120 * 60],
    ];

    public function run(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'auth'], 401);
        }

        // Reddit / meme-api fetches can run 15-30s. Give PHP headroom, and
        // keep the worker alive even if the user's tab navigates away mid-run.
        @set_time_limit(120);
        @ignore_user_abort(true);

        // Coalesce concurrent pings — at most one scan per ~minute regardless
        // of how many browsers are open.
        $now  = time();
        $last = (int) Cache::get('task.last_scan', 0);
        if ($now - $last < 55) {
            return response()->json(['skipped' => true]);
        }
        Cache::put('task.last_scan', $now, 600);

        // Materialize any pending book requests whose 2-minute prep window
        // has elapsed. Runs on every scan (cheap when there's nothing to do).
        try {
            AssistantController::processPendingRequests(5);
        } catch (\Throwable $e) {
            // Soft-fail — bot scheduling below must still run.
        }

        // Cross-post one book to Reddit if the 20-min spacing allows. Cheap
        // when disabled or nothing's queued. Single book per tick keeps the
        // heartbeat fast and the subreddit's anti-spam happy.
        try {
            $this->crossPostOneBookToReddit();
        } catch (\Throwable $e) {
            // Never let Reddit failures break the heartbeat.
        }

        // Same idea for Pinterest — one pin per tick, own 20-min spacing key,
        // independent of the Reddit cadence so the two stagger naturally.
        try {
            $this->crossPostOneBookToPinterest();
        } catch (\Throwable $e) {
            // Never let Pinterest failures break the heartbeat.
        }

        foreach ($this->jobs as $key => $cfg) {
            $cacheKey = "task.lastrun.{$key}";
            $lastRun  = (int) Cache::get($cacheKey, 0);
            if ($now - $lastRun < $cfg['every']) continue;

            // Mark as run BEFORE invoking so a slow / crashing command can't
            // re-trigger on the next ping. Worst case: one missed cycle.
            Cache::put($cacheKey, $now, 86400 * 30);

            try {
                Artisan::call($cfg['cmd']);
                return response()->json(['ran' => $key, 'at' => date('c', $now)]);
            } catch (\Throwable $e) {
                return response()->json(['ran' => $key, 'error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['idle' => true]);
    }

    /**
     * Pick the oldest book that has never been successfully cross-posted to
     * Reddit and post it — but only if our 20-minute spacing cache says we're
     * allowed to. One book per tick.
     *
     * The spacing key is updated BEFORE the actual HTTP work so a slow Reddit
     * call can't be fired twice by overlapping heartbeats. On failure the
     * key still ticks forward — that's the same intentional throttle the
     * bot scheduler uses (worst case: one missed cycle).
     */
    private function crossPostOneBookToReddit(): void
    {
        $cfg = (array) config('reddit');
        if (empty($cfg['enabled'])) return;

        $spacingMinutes = max(1, (int) ($cfg['post_spacing_minutes'] ?? 20));
        $lastAt = (int) Cache::get('reddit:last_post_at', 0);
        $now    = time();
        if ($now - $lastAt < $spacingMinutes * 60) return; // still inside spacing window

        $maxAttempts = (int) ($cfg['max_attempts'] ?? 5);

        $book = BookDetail::query()
            ->whereNull('reddit_post_id')
            ->where('reddit_attempts', '<', $maxAttempts)
            ->whereNotNull('cover_url')
            ->orderBy('id')
            ->with('post.user:id,username')
            ->first();

        if (!$book) return;

        // Reserve the next spacing window up-front so concurrent heartbeats
        // don't both pick this book and stampede Reddit.
        Cache::put('reddit:last_post_at', $now, 86400);

        $poster = new RedditPoster($cfg);
        if (!$poster->isReady()) return;

        try {
            $fullname = $poster->postBook($book);
            $book->update([
                'reddit_post_id'   => $fullname,
                'reddit_posted_at' => now(),
                'reddit_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $book->increment('reddit_attempts');
            $book->update(['reddit_last_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('Reddit cross-post failed', [
                'book_id' => $book->id,
                'attempts' => $book->reddit_attempts,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pick the oldest book never successfully pinned and post it to Pinterest,
     * subject to the 20-minute spacing window. One pin per tick.
     *
     * Mirrors crossPostOneBookToReddit(): the spacing key ticks forward BEFORE
     * the HTTP work so overlapping heartbeats can't double-pin, and a failure
     * still consumes the window (worst case: one missed cycle).
     */
    private function crossPostOneBookToPinterest(): void
    {
        $cfg = (array) config('pinterest');
        if (empty($cfg['enabled'])) return;

        $spacingMinutes = max(1, (int) ($cfg['post_spacing_minutes'] ?? 20));
        $lastAt = (int) Cache::get('pinterest:last_post_at', 0);
        $now    = time();
        if ($now - $lastAt < $spacingMinutes * 60) return; // still inside spacing window

        $maxAttempts = (int) ($cfg['max_attempts'] ?? 5);

        $book = BookDetail::query()
            ->whereNull('pinterest_pin_id')
            ->where('pinterest_attempts', '<', $maxAttempts)
            ->whereNotNull('cover_url')
            ->orderBy('id')
            ->with('post.user:id,username')
            ->first();

        if (!$book) return;

        // Reserve the next window up-front so concurrent heartbeats don't both
        // pick this book and stampede Pinterest.
        Cache::put('pinterest:last_post_at', $now, 86400);

        $poster = new PinterestPoster($cfg);
        if (!$poster->isReady()) return;

        try {
            $pinId = $poster->postBook($book);
            $book->update([
                'pinterest_pin_id'    => $pinId,
                'pinterest_posted_at' => now(),
                'pinterest_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $book->increment('pinterest_attempts');
            $book->update(['pinterest_last_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('Pinterest cross-post failed', [
                'book_id'  => $book->id,
                'attempts' => $book->pinterest_attempts,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
