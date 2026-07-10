<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visitor;
use App\Services\UserAgentParser;
use App\Services\VisitorGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Traffic and membership analytics.
 *
 * Two data sources with different shapes, and the difference drives the whole page:
 *
 *  - `visitor_page_views` is a per-day hit counter, so views-over-time and top
 *    pages are exact. Retained 90 days.
 *  - `visitors` is one row per visitor holding only their *latest* page,
 *    referrer and country. It answers "who is here and where from", never
 *    "how many views did /books get". Retained 30 days.
 *
 * Every panel below is fed from whichever table can answer it honestly.
 *
 * The page is split into tabs, and the tabs are server-side (plain links, not
 * JS show/hide) for three reasons: only the open tab's queries run, each tab is
 * deep-linkable, and the visitor log's pagination needs no special handling.
 */
class StatisticsController extends Controller
{
    /** Windows the range selector offers. `visitors`-backed panels cap out at 30. */
    private const RANGES = [7, 30, 90];

    /** Tab slug => label, in display order. The first is the default. */
    private const TABS = [
        'today'     => 'Today',
        'traffic'   => 'Traffic',
        'countries' => 'Countries',
        'map'       => 'Map',
        'referrers' => 'Referrers',
        'devices'   => 'Devices',
        'log'       => 'Visitor log',
        'members'   => 'Members',
    ];

    private const VISITOR_TABLE_RETENTION_DAYS = 30;

    private const LOG_PER_PAGE = 15;

    /** A visitor last seen within this many minutes counts as "here now". */
    private const LIVE_WINDOW_MINUTES = 15;

    /** Memoised: both the members strip and the health panel ask for this. */
    private ?int $neverPosted = null;

    public function index(Request $request)
    {
        $tab = (string) $request->query('tab', '');
        if (!array_key_exists($tab, self::TABS)) {
            $tab = array_key_first(self::TABS);
        }

        $days = (int) $request->query('days', 30);
        if (!in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        $now = now();
        // Inclusive window: `days = 7` means today plus the six days before it.
        $since     = $now->copy()->subDays($days - 1)->startOfDay();
        $prevFrom  = $since->copy()->subDays($days);
        $sinceDate = $since->toDateString();

        // `visitors` only holds 30 days, so its panels can never honour a 90-day
        // request. Track the effective window separately and say so in the view.
        $visitorTableDays  = min($days, self::VISITOR_TABLE_RETENTION_DAYS);
        $visitorTableSince = $now->copy()->subDays($visitorTableDays - 1)->startOfDay();

        $shared = [
            'tab'                  => $tab,
            'tabs'                 => self::TABS,
            'days'                 => $days,
            'ranges'               => self::RANGES,
            'since'                => $since,
            'visitorTableDays'     => $visitorTableDays,
            'visitorWindowClamped' => $days > self::VISITOR_TABLE_RETENTION_DAYS,
            // The window selector is meaningless on a tab that always means "today".
            'showRangeSelector'    => $tab !== 'today',
        ];

        // Only the open tab's queries run. Each arm returns just what its
        // partial reads, so an unopened tab costs nothing.
        $data = match ($tab) {
            'today'     => $this->todayTab(),
            'traffic'   => [
                'traffic'       => $this->trafficStats($sinceDate, $since, $prevFrom),
                'trafficSeries' => $this->trafficSeries($since, $now),
                'topPages'      => $this->topPages($sinceDate),
            ],
            'countries' => ['countries' => $this->countries($visitorTableSince, 20)],
            'map'       => ['mapCountries' => $this->countries($visitorTableSince, null)],
            'referrers' => ['referrers' => $this->referrers($visitorTableSince)],
            'devices'   => ['devices' => $this->deviceBreakdown($visitorTableSince)],
            'log'       => [
                'visitorLog'     => $this->visitorLog($request, $days),
                'logFilters'     => $this->logFilters($request),
                'countryOptions' => $this->countries($visitorTableSince, null),
            ],
            'members'   => [
                'members'      => $this->memberStats($since, $prevFrom),
                'signupSeries' => $this->dailySeries('users', $since, $now),
                'demographics' => $this->demographics(),
                'health'       => $this->accountHealth(),
                'leaders'      => $this->leaderboards(),
            ],
        };

        return view('admin.statistics.index', $shared + $data);
    }

    /**
     * JSON feed for the Today tab's poller.
     *
     * Returns formatted values plus HTML rendered by the *same* partials the
     * full page uses, so the live view can never drift from the server-rendered
     * one and no row markup has to be duplicated in JavaScript.
     *
     * Only Today is polled: every other tab is a multi-day aggregate that does
     * not move minute to minute, and two of them own Chart.js canvases that a
     * naive innerHTML swap would destroy.
     */
    public function live()
    {
        $data  = $this->todayTab();
        $today = $data['today'];

        $trend = fn (array $chip) => view('admin.partials._trend', [
            't'           => $chip,
            'currentText' => 'today',
            'compareText' => 'vs yesterday',
        ])->render();

        return response()
            ->json([
                'values' => [
                    'views'        => number_format($today['views']),
                    'uniques'      => number_format($today['uniques']),
                    'new_visitors' => number_format($today['new_visitors']),
                    'returning'    => number_format($today['returning']),
                    'live'         => number_format($today['live']),
                ],
                'html' => [
                    'views_trend'   => $trend($today['views_trend']),
                    'uniques_trend' => $trend($today['uniques_trend']),
                    'pages'    => view('admin.statistics.tabs._today-pages', [
                        'todayTopPages' => $data['todayTopPages'],
                    ])->render(),
                    'visitors' => view('admin.statistics.tabs._today-visitors', [
                        'liveVisitors' => $data['liveVisitors'],
                    ])->render(),
                ],
                'server_time' => now()->toIso8601String(),
            ])
            // A cached "live" number is worse than no live number at all.
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /* ─────────────────────────────── Today ──────────────────────────────── */

    /**
     * Today against yesterday, from the page-view counters.
     *
     * There is no hourly breakdown to offer: `visitor_page_views` buckets by
     * day, so an hourly chart would be invented. What *is* honest is who is on
     * the site right now, which `visitors.updated_at` answers exactly.
     */
    private function todayTab(): array
    {
        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $day = fn (string $d) => DB::table('visitor_page_views')
            ->where('day', $d)
            ->selectRaw('COALESCE(SUM(hits), 0) AS views, COUNT(DISTINCT visitor_token) AS uniques')
            ->first();

        $now  = $day($today);
        $prev = $day($yesterday);

        $uniques = (int) $now->uniques;

        // First-ever-seen today. Counted from `visitors`, which is a different
        // table than the uniques above, so clamp rather than let rounding or a
        // pruned row produce a negative "returning" count.
        $newVisitors = Visitor::whereDate('created_at', $today)->count();
        $returning   = max(0, $uniques - $newVisitors);

        return [
            'today' => [
                'views'          => (int) $now->views,
                'uniques'        => $uniques,
                'views_trend'    => $this->movement((int) $now->views, (int) $prev->views),
                'uniques_trend'  => $this->movement($uniques, (int) $prev->uniques),
                'new_visitors'   => $newVisitors,
                'returning'      => $returning,
                'live'           => Visitor::where('updated_at', '>=', now()->subMinutes(self::LIVE_WINDOW_MINUTES))->count(),
                'live_minutes'   => self::LIVE_WINDOW_MINUTES,
            ],
            'todayTopPages' => $this->topPages($today),
            'liveVisitors'  => Visitor::where('updated_at', '>=', now()->startOfDay())
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(),
        ];
    }

    /* ────────────────────────────── Visitors ────────────────────────────── */

    /**
     * Headline traffic numbers for the window, plus the same numbers for the
     * window immediately before it so each KPI can show movement.
     */
    private function trafficStats(string $sinceDate, Carbon $since, Carbon $prevFrom): array
    {
        $window = fn (string $from, string $to) => DB::table('visitor_page_views')
            ->whereBetween('day', [$from, $to])
            ->selectRaw('COALESCE(SUM(hits), 0) AS views, COUNT(DISTINCT visitor_token) AS uniques')
            ->first();

        $yesterday = $since->copy()->subDay()->toDateString();
        $current = $window($sinceDate, now()->toDateString());
        $prior   = $window($prevFrom->toDateString(), $yesterday);

        $views   = (int) $current->views;
        $uniques = (int) $current->uniques;

        // Bot share is a property of the visitor, not of a page view, so it can
        // only be computed from `visitors` — and only over its 30-day window.
        $knownVisitors = Visitor::count();
        $botVisitors   = Visitor::bots()->count();

        return [
            'views'          => $views,
            'uniques'        => $uniques,
            'views_prior'    => (int) $prior->views,
            'uniques_prior'  => (int) $prior->uniques,
            'views_trend'    => $this->movement((int) $current->views, (int) $prior->views),
            'uniques_trend'  => $this->movement($uniques, (int) $prior->uniques),
            'views_per_visitor' => $uniques > 0 ? round($views / $uniques, 1) : 0.0,
            'countries_count' => (int) Visitor::whereNotNull('country_code')->distinct()->count('country_code'),
            'total_visitors' => $knownVisitors,
            'bot_visitors'   => $botVisitors,
            'bot_share'      => $knownVisitors > 0 ? round($botVisitors / $knownVisitors * 100, 1) : 0.0,
        ];
    }

    /** Views and unique visitors per day, zero-filled across the whole window. */
    private function trafficSeries(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('visitor_page_views')
            ->where('day', '>=', $start->toDateString())
            ->selectRaw('day, SUM(hits) AS views, COUNT(DISTINCT visitor_token) AS uniques')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($r) => (string) $r->day);

        $out = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $out[] = [
                'date'    => $key,
                'views'   => (int) ($row->views ?? 0),
                'uniques' => (int) ($row->uniques ?? 0),
            ];
        }
        return $out;
    }

    /** True top pages: real per-path view counts, not "the last page each visitor saw". */
    private function topPages(string $sinceDate): array
    {
        return DB::table('visitor_page_views')
            ->where('day', '>=', $sinceDate)
            ->selectRaw('path, SUM(hits) AS views, COUNT(DISTINCT visitor_token) AS uniques')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'path'    => $r->path,
                'views'   => (int) $r->views,
                'uniques' => (int) $r->uniques,
            ])
            ->all();
    }

    /**
     * Visitors and their lifetime hits grouped by country. `hits` is the
     * visitor's all-time total, so this ranks countries by engagement rather
     * than by views inside the window — labelled as such in the view.
     *
     * A null $limit returns every country: the map shades all of them, and the
     * log's country filter must be able to offer all of them.
     */
    private function countries(Carbon $since, ?int $limit = 12): array
    {
        $query = Visitor::where('updated_at', '>=', $since)
            ->selectRaw("COALESCE(country_code, 'XX') AS code,
                         COALESCE(country_name, 'Unknown') AS name,
                         COUNT(*) AS visitors,
                         SUM(hits) AS hits")
            ->groupBy('code', 'name')
            ->orderByDesc('visitors');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn ($r) => [
                'code'     => $r->code,
                'name'     => $r->name,
                'visitors' => (int) $r->visitors,
                'hits'     => (int) $r->hits,
            ])
            ->all();
    }

    /**
     * External referrers only — RecordVisitor nulls out same-host referers, and
     * a null referrer is a direct visit, so both are excluded here.
     */
    private function referrers(Carbon $since): array
    {
        $rows = Visitor::where('updated_at', '>=', $since)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->get(['referrer', 'hits']);

        return collect($rows)
            ->groupBy(fn ($r) => parse_url($r->referrer, PHP_URL_HOST) ?: $r->referrer)
            ->map(fn ($group, $host) => [
                'host'     => $host,
                'visitors' => $group->count(),
                'hits'     => (int) $group->sum('hits'),
            ])
            ->sortByDesc('visitors')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Device / browser mix, parsed from the stored user agents. Bots are counted
     * but kept out of the device and browser mixes, where they would swamp the
     * real numbers without meaning anything.
     */
    private function deviceBreakdown(Carbon $since): array
    {
        $agents = Visitor::where('updated_at', '>=', $since)->pluck('user_agent');

        $devices  = [];
        $browsers = [];
        $bots     = 0;

        foreach ($agents as $ua) {
            $parsed = UserAgentParser::parse($ua);
            if ($parsed['is_bot']) {
                $bots++;
                continue;
            }
            $devices[$parsed['device']]   = ($devices[$parsed['device']] ?? 0) + 1;
            $browsers[$parsed['browser']] = ($browsers[$parsed['browser']] ?? 0) + 1;
        }

        arsort($devices);
        arsort($browsers);

        return [
            'devices'  => $devices,
            'browsers' => array_slice($browsers, 0, 6, true),
            'bots'     => $bots,
            'humans'   => array_sum($devices),
        ];
    }

    /** @return array{q:string, country:?string, traffic:string, sort:string} */
    private function logFilters(Request $request): array
    {
        $traffic = $request->query('traffic', 'all');
        $sort    = $request->query('sort', 'recent');

        return [
            'q'       => trim((string) $request->query('q', '')),
            'country' => $request->query('country') ?: null,
            'traffic' => in_array($traffic, ['all', 'human', 'bot'], true) ? $traffic : 'all',
            'sort'    => in_array($sort, ['recent', 'first', 'hits'], true) ? $sort : 'recent',
        ];
    }

    /** The raw log: one row per visitor, filterable, paginated on its own key. */
    private function visitorLog(Request $request, int $days)
    {
        $f = $this->logFilters($request);

        $query = Visitor::query();

        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('ip_address', 'like', $like)
                  ->orWhere('page', 'like', $like)
                  ->orWhere('country_name', 'like', $like)
                  ->orWhere('referrer', 'like', $like);
            });
        }

        if ($f['country']) {
            $query->where('country_code', $f['country']);
        }

        match ($f['traffic']) {
            'bot'   => $query->bots(),
            'human' => $query->humans(),
            default => null,
        };

        match ($f['sort']) {
            'first' => $query->orderBy('created_at', 'desc'),
            'hits'  => $query->orderByDesc('hits'),
            default => $query->orderByDesc('updated_at'),
        };

        // withQueryString() carries `tab` and `days` forward, so paging never
        // drops the reader back onto the default tab.
        return $query
            ->paginate(self::LOG_PER_PAGE, ['*'], 'vpage')
            ->withQueryString()
            ->appends(['tab' => 'log', 'days' => $days])
            ->fragment('tabpanel');
    }

    /* ────────────────────────────── Members ─────────────────────────────── */

    private function memberStats(Carbon $since, Carbon $prevFrom): array
    {
        $total    = User::count();
        $new      = User::where('created_at', '>=', $since)->count();
        // Half-open, so a signup landing exactly on $since is not counted twice.
        $prior    = User::where('created_at', '>=', $prevFrom)
            ->where('created_at', '<', $since)->count();
        $verified = User::whereNotNull('email_verified_at')->count();

        // "Active" = published a post or left a comment inside the window.
        $active = User::where(function ($w) use ($since) {
            $w->whereHas('posts', fn ($p) => $p->where('created_at', '>=', $since))
              ->orWhereHas('comments', fn ($c) => $c->where('created_at', '>=', $since));
        })->count();

        $neverPosted = $this->neverPosted();

        return [
            'total'          => $total,
            'new'            => $new,
            'new_trend'      => $this->movement($new, $prior),
            'verified'       => $verified,
            'verified_share' => $total > 0 ? round($verified / $total * 100, 1) : 0.0,
            'active'         => $active,
            'active_share'   => $total > 0 ? round($active / $total * 100, 1) : 0.0,
            'never_posted'       => $neverPosted,
            'never_posted_share' => $total > 0 ? round($neverPosted / $total * 100, 1) : 0.0,
        ];
    }

    private function neverPosted(): int
    {
        return $this->neverPosted ??= User::doesntHave('posts')->count();
    }

    /**
     * Gender, age bands and country. `users.country` is a placeholder 'XX' for
     * the overwhelming majority of rows, so `known` is reported alongside the
     * ranking — a country chart that hides a 99% unknown bucket lies.
     */
    private function demographics(): array
    {
        $gender = User::selectRaw("COALESCE(NULLIF(gender, ''), 'unspecified') AS g, COUNT(*) AS c")
            ->groupBy('g')->orderByDesc('c')->pluck('c', 'g')->map(fn ($n) => (int) $n)->all();

        // Bands are half-open: 18-24 is age >= 18 AND age < 25.
        $bands = [
            'Under 18' => [0, 18],
            '18–24'    => [18, 25],
            '25–34'    => [25, 35],
            '35–44'    => [35, 45],
            '45–54'    => [45, 55],
            '55–64'    => [55, 65],
            '65+'      => [65, 200],
        ];

        $ageRows = User::selectRaw('age, COUNT(*) AS c')->whereNotNull('age')
            ->groupBy('age')->pluck('c', 'age')->all();

        $ages = array_fill_keys(array_keys($bands), 0);
        foreach ($ageRows as $age => $count) {
            foreach ($bands as $label => [$lo, $hi]) {
                if ($age >= $lo && $age < $hi) {
                    $ages[$label] += (int) $count;
                    break;
                }
            }
        }

        $countryRows = User::selectRaw('country, COUNT(*) AS c')
            ->groupBy('country')->orderByDesc('c')->get();

        $known = [];
        $unknown = 0;
        foreach ($countryRows as $row) {
            $name = VisitorGeo::countryName($row->country);
            if ($name === null) {
                $unknown += (int) $row->c;
                continue;
            }
            $known[] = ['code' => strtoupper($row->country), 'name' => $name, 'count' => (int) $row->c];
        }

        return [
            'gender'          => $gender,
            'ages'            => $ages,
            'age_total'       => array_sum($ages),
            'countries'       => array_slice($known, 0, 10),
            'country_known'   => array_sum(array_column($known, 'count')),
            'country_unknown' => $unknown,
        ];
    }

    private function accountHealth(): array
    {
        $total = max(1, User::count());

        $metrics = [
            'Admins'            => User::where('role', 'admin')->count(),
            'Unverified email'  => User::whereNull('email_verified_at')->count(),
            'No profile photo'  => User::whereNull('profile_picture')->orWhere('profile_picture', '')->count(),
            'No bio'            => User::whereNull('bio')->orWhere('bio', '')->count(),
            'Never posted'      => $this->neverPosted(),
        ];

        return collect($metrics)
            ->map(fn ($count, $label) => [
                'label' => $label,
                'count' => $count,
                'share' => round($count / $total * 100, 1),
            ])
            ->values()
            ->all();
    }

    /** Top members by posts, by comments, and by likes received across their posts. */
    private function leaderboards(): array
    {
        $columns = ['id', 'name', 'username', 'profile_picture'];

        $byPosts = User::withCount('posts')->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')->limit(6)->get($columns);

        $byComments = User::withCount('comments')->having('comments_count', '>', 0)
            ->orderByDesc('comments_count')->limit(6)->get($columns);

        // Likes received lives on the denormalised posts.likes_count, so this is
        // one grouped scan rather than a join through post_likes.
        $likeTotals = DB::table('posts')
            ->selectRaw('user_id, SUM(likes_count) AS total')
            ->groupBy('user_id')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'user_id');

        $likeUsers = User::whereIn('id', $likeTotals->keys())->get($columns)->keyBy('id');

        $byLikes = $likeTotals
            ->map(fn ($total, $id) => [
                'user'  => $likeUsers->get($id),
                'total' => (int) $total,
            ])
            ->filter(fn ($row) => $row['user'] !== null) // user deleted since the scan
            ->values()
            ->all();

        return compact('byPosts', 'byComments', 'byLikes');
    }

    /* ────────────────────────────── Helpers ─────────────────────────────── */

    /**
     * Period-over-period movement. `pct` is null when the prior window was empty:
     * there is no honest percentage against a zero baseline, and the view shows
     * the raw count instead. Mirrors DashboardController::trend().
     */
    private function movement(int $current, int $prior): array
    {
        return [
            'current' => $current,
            'prior'   => $prior,
            'pct'     => $prior > 0 ? round((($current - $prior) / $prior) * 100, 1) : null,
        ];
    }

    /** Zero-filled [['date' => YYYY-MM-DD, 'count' => N], ...] for the given table. */
    private function dailySeries(string $table, Carbon $start, Carbon $end): array
    {
        $rows = DB::table($table)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS c')
            ->where('created_at', '>=', $start)
            ->groupBy('d')
            ->pluck('c', 'd')
            ->all();

        $out = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $out[] = ['date' => $key, 'count' => (int) ($rows[$key] ?? 0)];
        }
        return $out;
    }
}
