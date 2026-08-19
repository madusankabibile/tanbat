<?php

namespace App\Http\Controllers;

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
    /** GET /tv — every live channel, most-watched first. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = TvChannel::query()->where('is_active', true);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        $channels = $query
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('tv', compact('channels', 'q'));
    }

    /** GET /tv/{slug} — the player page. */
    public function show(Request $request, string $slug)
    {
        $channel = TvChannel::where('slug', $slug)->firstOrFail();

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

        // The right rail's "related channels". Random-ish rotation via views
        // so the rail isn't identical on every channel.
        $related = TvChannel::where('is_active', true)
            ->whereKeyNot($channel->id)
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

    private function viewerIsAdmin(Request $request): bool
    {
        return (bool) $request->user()?->isAdmin();
    }
}
