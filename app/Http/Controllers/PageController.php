<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Visitor;
use App\Support\Omrms;
use App\Support\OmrmsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    /**
     * Public home feed. Guests can browse the whole feed; liking and commenting
     * trigger the login/register modal (wired client-side). There is no separate
     * landing page any more — the root URL is the feed for everyone.
     */
    public function home()
    {
        // omrms.com is an article-only companion site off this same codebase —
        // its root is a grid of article cards, not the social feed.
        if (Omrms::isActive()) {
            return app(OmrmsController::class)->home(request());
        }

        return view('home');
    }

    /** Privacy policy — static legal page, public */
    public function privacy()
    {
        return view('privacy');
    }

    /** Article create page */
    public function articleCreate()
    {
        if (!Auth::check()) {
            return redirect()->route('home');
        }
        return view('article-create');
    }

    /**
     * Dedicated shareable page for a status / image / video post:
     * GET /posts/{type}/{id}. Public (guests can view). Redirects to the
     * canonical URL when the {type} segment doesn't match the post's real type.
     */
    public function postShow(string $type, Post $post)
    {
        // Only status/image/video have this page. Articles/books live elsewhere;
        // send those to their own canonical URL rather than 404.
        if (!in_array($post->type, ['status', 'image', 'video'], true)) {
            return redirect()->to($post->permalink(), 301);
        }

        // Canonical type in the URL — /posts/image/5 for an actual video → 301.
        if ($post->type !== $type) {
            return redirect()->route('post.show', ['type' => $post->type, 'post' => $post->id], 301);
        }

        $post->load([
            'user:id,name,username,profile_picture,country',
            'category:id,name,slug',
            'media',
            'tags:id,name',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user']),
        ]);

        // Count this visit (mirrors the modal's /api/posts/{id} behaviour).
        $post->increment('views_count');

        $myReaction = Auth::check() ? $post->reactionBy(Auth::id()) : null;

        // "More posts" — recent status/image/video posts, newest first.
        $related = Post::with(['user:id,name,username,profile_picture', 'media'])
            ->whereIn('type', ['status', 'image', 'video'])
            ->where('id', '!=', $post->id)
            ->latest()
            ->limit(6)
            ->get();

        return view('post-show', [
            'post'       => $post,
            'liked'      => $myReaction !== null,
            'myReaction' => $myReaction,
            'related'    => $related,
        ]);
    }

    /** GET /api/sidebar — profile stats + site stats + active users */
    public function sidebar(): JsonResponse
    {
        $user = Auth::user();

        $profile = null;
        if ($user) {
            $row = Post::where('user_id', $user->id)
                ->selectRaw('COUNT(*) AS posts, COALESCE(SUM(views_count),0) AS views, COALESCE(SUM(likes_count),0) AS likes, COALESCE(SUM(comments_count),0) AS comments')
                ->first();
            $profile = [
                'id'              => $user->id,
                'name'            => $user->name,
                'username'        => $user->username,
                'profile_picture' => $user->avatarUrl(),
                'banner_image'    => $user->bannerUrl(),
                'role'            => $user->role,
                'stats' => [
                    'posts'    => (int) ($row->posts ?? 0),
                    'reach'    => (int) ($row->views ?? 0),   // reach == total views across your posts
                    'likes'    => (int) ($row->likes ?? 0),
                    'comments' => (int) ($row->comments ?? 0),
                ],
            ];
        }

        $stats = [
            'posts'    => Post::count(),
            'users'    => User::count(),
            'likes'    => (int) Post::sum('likes_count'),
            'comments' => Comment::count(),
        ];

        // "Active" = last activity (post or comment) within 24h. Online flag if within 5 min.
        $recentWindow = now()->subDay();
        $onlineWindow = now()->subMinutes(5);
        $activeUsers = User::query()
            // Hide automated content bots + the shared anonymous account so the
            // rail surfaces real members (see config/bots.php).
            ->whereNotIn('username', config('bots.usernames', []))
            ->select('id', 'name', 'username', 'profile_picture', 'updated_at')
            ->withCount(['posts as recent_posts' => fn ($q) => $q->where('created_at', '>=', $recentWindow)])
            ->withCount(['comments as recent_comments' => fn ($q) => $q->where('created_at', '>=', $recentWindow)])
            ->withCount('posts as total_posts')
            // Rank by recent activity (posts + comments in the last 24h), then by
            // lifetime posts. Ordering on the withCount aliases avoids embedding a
            // raw date literal in SQL — double-quoted literals break on MySQL
            // servers running in ANSI_QUOTES mode (some managed hosts).
            ->orderByRaw('(recent_posts + recent_comments) desc')
            ->orderByDesc('total_posts')
            ->limit(6)
            ->get()
            ->map(fn (User $u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'profile_picture' => $u->avatarUrl(),
                'posts'           => $u->total_posts,
                'online'          => $u->updated_at && $u->updated_at->gte($onlineWindow),
            ]);

        // Touch the current user so future "online" checks see them.
        if ($user) {
            $user->forceFill(['updated_at' => now()])->save();
        }

        return response()->json([
            'profile' => $profile,
            'stats'   => $stats,
            'active_users' => $activeUsers,
        ]);
    }

    /** GET /api/visitors — last 8 distinct visitors for the "Recent visitors" widget */
    public function visitors(): JsonResponse
    {
        $visitors = Visitor::query()
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['country_code', 'country_name', 'page', 'referrer', 'updated_at'])
            ->map(fn (Visitor $v) => [
                'country_code' => $v->country_code ? strtolower($v->country_code) : null,
                'country_name' => $v->country_name ?: 'Unknown',
                'page'         => $v->page ?: '/',
                // Show just the source host for external referrers; null => "Direct".
                'referrer'     => $v->referrer ? (parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer) : null,
                'when'         => $v->updated_at?->diffForHumans(null, true) . ' ago',
            ]);

        return response()->json(['visitors' => $visitors]);
    }

    /** Article view page */
    public function articleShow(string $slug, \Illuminate\Http\Request $request, \App\Services\ArticleGeoViews $geoViews)
    {
        $post = Post::with([
                'user',
                'category',
                'tags',
                'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user']),
            ])
            ->where('type', 'article')
            ->where('is_legacy', false) // migrated blog articles live at /blogs/{id}/{slug}
            ->where('slug', $slug)
            ->firstOrFail();

        // Count this visit. views_count also bumps on feed impressions
        // (InteractionRecorder); opening/refreshing the article adds to it too.
        $post->increment('views_count');
        // Attribute the view to the visitor's country for the /blog "For you"
        // geo-ranking (article_country_views). No-op if IP can't be geolocated.
        $geoViews->record($post->id, $request);

        $myReaction = Auth::check() ? $post->reactionBy(Auth::id()) : null;
        $liked = $myReaction !== null;

        // Related articles — same category first, fall back to shared tags, then recent.
        $tagIds = $post->tags->pluck('id')->all();
        $related = Post::with(['user:id,name,username,profile_picture', 'category:id,name'])
            ->where('type', 'article')
            ->where('is_legacy', false)
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post, $tagIds) {
                if ($post->category_id) {
                    $q->where('category_id', $post->category_id);
                }
                if (!empty($tagIds)) {
                    $q->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds));
                }
            })
            ->latest()
            ->limit(6)
            ->get();

        if ($related->count() < 4) {
            $extra = Post::with(['user:id,name,username,profile_picture', 'category:id,name'])
                ->where('type', 'article')
                ->where('id', '!=', $post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest()
                ->limit(6 - $related->count())
                ->get();
            $related = $related->concat($extra);
        }

        // omrms.com renders the same article in its own stripped-down,
        // ad-supported, SEO-focused template (no navbar / login / comments).
        if (Omrms::isActive()) {
            return view('omrms.article', ['a' => OmrmsArticle::fromPost($post, $related)]);
        }

        return view('article-show', [
            'post'        => $post,
            'liked'       => $liked,
            'myReaction'  => $myReaction,
            'related'     => $related,
        ]);
    }
}
