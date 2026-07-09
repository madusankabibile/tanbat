<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedRanker;
use App\Services\GeoLocator;
use App\Services\InteractionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function __construct(
        private FeedRanker $ranker,
        private InteractionRecorder $recorder,
        private GeoLocator $geo,
    ) {}

    /**
     * GET /api/feed?limit=1&exclude_ids=1,2,3
     *
     * Streaming endpoint: returns up to `limit` posts (default 1), excluding any
     * IDs the client has already loaded in this session. The algorithm re-runs
     * on every request, so prior interactions (likes, comments, clicks, dwell)
     * influence which post comes back next.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 1);
        $excludeIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $request->query('exclude_ids', ''))
        )));

        // Resolve the viewer's country from their IP so the ranker can surface
        // more local content. Backfill the profile country when it's missing
        // (never overwrite a self-reported one), but always rank by live IP.
        $user = Auth::user();
        $viewerCountry = $this->geo->country($request);
        if ($user && $viewerCountry && empty($user->country)) {
            $user->forceFill(['country' => $viewerCountry])->save();
        }

        // Guests get IP-keyed rotation: hash the visitor's IP into a stable key
        // so the ranker can exclude posts already shown to them across refreshes.
        $guestKey = $user ? null : $this->guestKey($request);

        $result = $this->ranker->feedFor($user, $excludeIds, $limit, $viewerCountry, $guestKey);

        // Reuse the existing PostController shape so the JS can render with
        // its current cardHTML() unchanged.
        $postShaper = new PostController();
        $shaped = $result['posts']->map(fn (Post $p) => $postShaper->shape($p));

        // Remember what we just served this guest so the next refresh rotates.
        if ($guestKey) {
            $this->recordGuestSeen($guestKey, $result['posts']->pluck('id')->all());
        }

        return response()->json([
            'data'       => $shaped,
            'exhausted'  => $result['exhausted'] ?? $shaped->isEmpty(),
            'cold_start' => $result['cold_start'] ?? false,
            'recycled'   => $result['recycled'] ?? false,
        ]);
    }

    /**
     * Stable per-guest key: SHA-256 of the visitor's real IP (never stored raw).
     * Prefers Cloudflare's CF-Connecting-IP / the first X-Forwarded-For hop so
     * visitors behind the CDN aren't all collapsed into one bucket, falling back
     * to the connecting IP. Returns null for un-geolocatable/empty IPs — those
     * guests simply don't get rotation (they'd all share one key otherwise).
     */
    private function guestKey(Request $request): ?string
    {
        $ip = $request->header('CF-Connecting-IP');
        if (!$ip) {
            $fwd = (string) $request->header('X-Forwarded-For', '');
            $ip  = $fwd !== '' ? trim(explode(',', $fwd)[0]) : $request->ip();
        }
        $ip = is_string($ip) ? trim($ip) : '';
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        // Salt with the app key so the hash isn't a bare IP digest.
        return hash('sha256', $ip . '|' . config('app.key'));
    }

    /**
     * Record the posts just served to a guest IP so future refreshes exclude
     * them (rotation). Upsert bumps shown_count on re-encounters; a cheap
     * probabilistic prune keeps the table from growing unbounded.
     *
     * @param int[] $postIds
     */
    private function recordGuestSeen(string $ipHash, array $postIds): void
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (empty($postIds)) return;

        $now  = now();
        $rows = array_map(fn ($pid) => [
            'ip_hash'     => $ipHash,
            'post_id'     => $pid,
            'seen_at'     => $now,
            'shown_count' => 1,
        ], $postIds);

        try {
            DB::table('guest_feed_impressions')->upsert(
                $rows,
                ['ip_hash', 'post_id'],
                ['seen_at', 'shown_count']
            );

            // ~2% of requests prune rows older than the TTL so the table
            // self-cleans without a scheduled job.
            if (random_int(1, 50) === 1) {
                $ttlHours = (int) config('feed.guest_impression_ttl_hours', 12);
                DB::table('guest_feed_impressions')
                    ->where('seen_at', '<', now()->subHours($ttlHours))
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Table not migrated yet — skip rotation bookkeeping silently so the
            // feed still serves. Run `php artisan migrate` on the host to enable.
        }
    }

    /**
     * POST /api/feed/impressions  { post_ids: [..] }
     * Batched from the client's IntersectionObserver every few seconds.
     */
    public function impressions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_ids'   => 'required|array|min:1|max:60',
            'post_ids.*' => 'integer',
        ]);
        if (!Auth::check()) {
            return response()->json(['recorded' => 0]);
        }
        $this->recorder->recordImpressions(Auth::id(), $data['post_ids']);
        return response()->json(['recorded' => count($data['post_ids'])]);
    }

    /**
     * POST /api/posts/{post}/click  { dwell_ms?: int }
     * Recorded when the user opens the modal; dwell sent on modal close.
     */
    public function click(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'event'    => 'nullable|in:click,dwell',
            'dwell_ms' => 'nullable|integer|min:0|max:3600000',
        ]);
        if (!Auth::check()) return response()->json(['recorded' => false]);

        $event = $data['event'] ?? 'click';
        $this->recorder->record(Auth::id(), $post->id, $event, [
            'dwell_ms' => $data['dwell_ms'] ?? null,
        ]);
        return response()->json(['recorded' => true]);
    }

}
