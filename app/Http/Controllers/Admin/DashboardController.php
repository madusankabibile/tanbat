<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now      = now();
        $since    = $now->copy()->subDays(30)->startOfDay();
        $prevFrom = $now->copy()->subDays(60)->startOfDay();

        $stats = [
            'users_total'    => User::count(),
            'users_new_30d'  => User::where('created_at', '>=', $since)->count(),
            'posts_total'    => Post::count(),
            'posts_new_30d'  => Post::where('created_at', '>=', $since)->count(),
            'comments_total' => Comment::count(),
            'likes_total'    => (int) DB::table('post_likes')->count(),
            'categories'     => Category::count(),
            'ads_active'     => Advertisement::live()->count(),
            'ad_impressions' => (int) Advertisement::sum('impressions'),
            'ad_clicks'      => (int) Advertisement::sum('clicks'),
        ];

        $stats['ad_ctr'] = $stats['ad_impressions']
            ? round(($stats['ad_clicks'] / $stats['ad_impressions']) * 100, 2)
            : 0.0;

        // Period-over-period movement: the last 30 days against the 30 before
        // them, so each KPI can state a trend instead of a bare total.
        $trends = [
            'users'    => $this->trend('users', $since, $prevFrom),
            'posts'    => $this->trend('posts', $since, $prevFrom),
            'comments' => $this->trend('comments', $since, $prevFrom),
            'likes'    => $this->trend('post_likes', $since, $prevFrom),
        ];

        // 30-day daily series for users + posts.
        $userSeries = $this->dailySeries('users', $since, $now);
        $postSeries = $this->dailySeries('posts', $since, $now);

        // Post type distribution.
        $postTypes = Post::select('type', DB::raw('COUNT(*) as c'))
            ->groupBy('type')->pluck('c', 'type')->toArray();

        // Top categories by post count.
        $topCategories = Category::withCount('posts')
            ->orderByDesc('posts_count')->limit(6)->get(['id', 'name']);

        // Top posts by engagement (likes + comments).
        $topPosts = Post::with('user:id,name,username,profile_picture', 'category:id,name')
            ->orderByRaw('(likes_count + comments_count) DESC')
            ->limit(6)->get();

        // Most-active users.
        $topUsers = User::withCount(['posts', 'comments'])
            ->orderByDesc('posts_count')->limit(6)
            ->get(['id', 'name', 'username', 'profile_picture', 'role', 'created_at']);

        // Recent signups + posts.
        $recentUsers = User::latest()->limit(6)
            ->get(['id', 'name', 'username', 'profile_picture', 'role', 'created_at']);
        $recentPosts = Post::with('user:id,name,username', 'category:id,name')
            ->latest()->limit(6)->get();

        return view('admin.dashboard', compact(
            'stats', 'trends', 'userSeries', 'postSeries', 'postTypes',
            'topCategories', 'topPosts', 'topUsers', 'recentUsers', 'recentPosts'
        ));
    }

    /**
     * Rows created in the current window vs the window of equal length before it.
     * `pct` is null when the prior window was empty — there is no honest
     * percentage against a zero baseline, so the view shows the raw count.
     */
    private function trend(string $table, Carbon $since, Carbon $prevFrom): array
    {
        $current = (int) DB::table($table)->where('created_at', '>=', $since)->count();
        $prior   = (int) DB::table($table)
            ->where('created_at', '>=', $prevFrom)
            ->where('created_at', '<', $since)
            ->count();

        return [
            'current' => $current,
            'prior'   => $prior,
            'pct'     => $prior > 0 ? round((($current - $prior) / $prior) * 100, 1) : null,
        ];
    }

    /** Build [['date' => YYYY-MM-DD, 'count' => N], ...] for the given table. */
    private function dailySeries(string $table, Carbon $start, Carbon $end): array
    {
        $rows = DB::table($table)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('created_at', '>=', $start)
            ->groupBy('d')->pluck('c', 'd')->toArray();

        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $k = $cursor->toDateString();
            $out[] = ['date' => $k, 'count' => (int) ($rows[$k] ?? 0)];
            $cursor->addDay();
        }
        return $out;
    }
}
