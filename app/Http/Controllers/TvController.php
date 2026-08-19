<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\TvChannel;
use Illuminate\Http\Request;

/**
 * Public TV pages — the channel grid at /tv and the player at /tv/{slug}.
 *
 * Both are guest-accessible. The player page is rendered WITHOUT the channel's
 * stream URL anywhere in its markup; the browser asks for a signed playback
 * session at runtime (TvStreamController::session).
 */
class TvController extends Controller
{
    /** GET /tv — every live channel, most-watched first, filterable by category. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Categories live on the parent post, so both the filter and the card
        // labels reach through it.
        $query = TvChannel::query()
            ->where('is_active', true)
            ->with('post:id,category_id', 'post.category:id,name,slug');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        // Filter by category slug so /tv?category=sports is a shareable URL.
        $categorySlug = trim((string) $request->query('category', ''));
        $category = $categorySlug !== ''
            ? Category::where('slug', $categorySlug)->first()
            : null;

        if ($category) {
            $query->whereHas('post', fn ($p) => $p->where('category_id', $category->id));
        }

        $channels = $query
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('tv', [
            'channels'   => $channels,
            'q'          => $q,
            'category'   => $category,
            'categories' => $this->liveCategories(),
        ]);
    }

    /** GET /tv/{slug} — the player page. */
    public function show(Request $request, string $slug)
    {
        $channel = TvChannel::with('post:id,category_id', 'post.category:id,name,slug')
            ->where('slug', $slug)
            ->firstOrFail();

        // One canonical URL per channel. MySQL's collation matches slugs
        // case-insensitively, so /tv/sampleTV finds the row — send it on to
        // /tv/sampletv rather than serving the same page at two addresses.
        if ($channel->slug !== $slug) {
            return redirect()->route('tv.show', $channel->slug, 301);
        }

        // An offline channel 404s for the public but stays reachable for an
        // admin previewing it before going live.
        if (!$channel->is_active && !$this->viewerIsAdmin($request)) {
            abort(404);
        }

        // Cheap view counter — a bare increment, no model events, so it costs
        // one UPDATE and never fires observers on a hot page.
        TvChannel::whereKey($channel->id)->increment('views');

        // The right rail's "related channels" — genuinely related now that
        // channels are categorised: same category first, then everything else
        // to fill the rail, most-watched within each group.
        $categoryId = $channel->post?->category_id;

        $related = TvChannel::where('is_active', true)
            ->whereKeyNot($channel->id)
            ->with('post:id,category_id', 'post.category:id,name,slug')
            ->when($categoryId, fn ($q) => $q->orderByRaw(
                '(SELECT posts.category_id FROM posts WHERE posts.id = tv_channels.post_id) = ? DESC',
                [$categoryId]
            ))
            ->orderByDesc('views')
            ->limit(12)
            ->get();

        $stats = [
            'views'      => $channel->views + 1,
            'channels'   => TvChannel::where('is_active', true)->count(),
            'total'      => (int) TvChannel::where('is_active', true)->sum('views'),
            'added'      => $channel->created_at,
        ];

        return view('tv-show', compact('channel', 'related', 'stats'));
    }

    /**
     * Categories that actually have a live channel in them — an empty filter
     * option is just a dead end for the viewer.
     */
    private function liveCategories()
    {
        return Category::whereHas('posts', fn ($p) => $p
                ->where('type', 'tv')
                ->whereHas('tvChannel', fn ($c) => $c->where('is_active', true)))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    private function viewerIsAdmin(Request $request): bool
    {
        return (bool) $request->user()?->isAdmin();
    }
}
