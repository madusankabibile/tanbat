<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedRanker;
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

        $result = $this->ranker->feedFor(Auth::user(), $excludeIds, $limit);

        // Reuse the existing PostController shape so the JS can render with
        // its current cardHTML() unchanged.
        $postShaper = new PostController();
        $shaped = $result['posts']->map(fn (Post $p) => $postShaper->shape($p));

        return response()->json([
            'data'       => $shaped,
            'exhausted'  => $result['exhausted'] ?? $shaped->isEmpty(),
            'cold_start' => $result['cold_start'] ?? false,
            'recycled'   => $result['recycled'] ?? false,
        ]);
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
