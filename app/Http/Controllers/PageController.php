<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    /** Landing OR home, depending on auth state */
    public function landing()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }
        return view('landing');
    }

    /** Home feed (Pinterest masonry) */
    public function home()
    {
        if (!Auth::check()) {
            return redirect()->route('landing');
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
            return redirect()->route('landing');
        }
        return view('article-create');
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

    /** Article view page */
    public function articleShow(string $slug)
    {
        $post = Post::with([
                'user',
                'category',
                'tags',
                'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user']),
            ])
            ->where('type', 'article')
            ->where('slug', $slug)
            ->firstOrFail();

        // Count this visit. views_count also bumps on feed impressions
        // (InteractionRecorder); opening/refreshing the article adds to it too.
        $post->increment('views_count');

        $myReaction = Auth::check() ? $post->reactionBy(Auth::id()) : null;
        $liked = $myReaction !== null;

        // Related articles — same category first, fall back to shared tags, then recent.
        $tagIds = $post->tags->pluck('id')->all();
        $related = Post::with(['user:id,name,username,profile_picture', 'category:id,name'])
            ->where('type', 'article')
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

        return view('article-show', [
            'post'        => $post,
            'liked'       => $liked,
            'myReaction'  => $myReaction,
            'related'     => $related,
        ]);
    }
}
