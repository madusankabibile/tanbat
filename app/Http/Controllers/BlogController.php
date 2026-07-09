<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Services\GeoLocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public blog index at /blog.
 *
 * Surfaces every article-type post (native + migrated legacy) as a card grid.
 * The default "For you" ordering is personalised to the VISITOR'S COUNTRY,
 * resolved from their IP: articles that readers from the same country have
 * actually opened (tracked per-country in article_country_views, independent of
 * who authored them) are boosted, blended with a Hacker-News-style time-decayed
 * global engagement score so hot + new still surface. So an article read heavily
 * by Japanese visitors is recommended to more Japanese visitors. Explicit "Hot"
 * and "New" tabs pin the order to one axis. Fully guest-accessible.
 */
class BlogController extends Controller
{
    /** Global engagement weighting, reused by every sort + the is_hot badge. */
    private const ENGAGEMENT_SQL =
        '(posts.views_count + posts.likes_count * 3 + posts.comments_count * 5)';

    /** An article counts as "hot" once its weighted engagement clears this. */
    private const HOT_THRESHOLD = 300;

    /** New-badge window (days). */
    private const NEW_DAYS = 7;

    /** GET /blog — render the public blog index. */
    public function page(Request $request, GeoLocator $geo): View
    {
        $country = $geo->country($request);

        return view('blog', [
            'geoCountry'     => $country,
            'geoCountryName' => $country ? ($this->countryNames()[$country] ?? $country) : null,
            'categories'     => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'trending'       => $this->trending($country),
            'trendingScope'  => $this->trendingScope,
            'totalArticles'  => Post::where('type', 'article')->count(),
        ]);
    }

    /**
     * GET /api/blog/feed — paginated JSON of article cards.
     *
     * Query params: sort (for-you|hot|new), q, category (slug), page, per_page.
     */
    public function feed(Request $request, GeoLocator $geo): JsonResponse
    {
        $perPage = max(6, min(36, (int) $request->query('per_page', 12)));
        $page    = max(1, (int) $request->query('page', 1));
        $sort    = (string) $request->query('sort', 'for-you');

        // Let the client pass a country explicitly (e.g. a picker); else detect
        // from the request IP. Drives both the geo boost and the "local" badge.
        $explicit = strtoupper(trim((string) $request->query('country', '')));
        $geoCountry = ($explicit !== '' && $explicit !== 'ALL')
            ? $explicit
            : $geo->country($request);

        $query = $this->baseArticleQuery($geoCountry);
        $this->applyFilters($query, $request);
        $this->applySort($query, $sort, $geoCountry);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (Post $p) => $this->cardData($p, $geoCountry));

        return response()->json([
            'items'       => $items,
            'sort'        => $sort,
            'geo_country' => $geoCountry,
            'page'        => $paginator->currentPage(),
            'last_page'   => $paginator->lastPage(),
            'total'       => $paginator->total(),
            'has_more'    => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Base query: article posts with category + author eager-loaded. When the
     * visitor's country is known we LEFT JOIN their per-country view counter so
     * `country_views` is available for ranking and the "popular near you" badge.
     */
    private function baseArticleQuery(?string $geoCountry): Builder
    {
        $query = Post::query()
            ->where('posts.type', 'article')
            ->whereNotNull('posts.slug')
            ->where('posts.slug', '!=', '')
            ->with([
                'user:id,name,username,profile_picture',
                'category:id,name,slug',
            ])
            ->select('posts.*');

        if ($geoCountry) {
            $query->leftJoin('article_country_views as acv', function ($j) use ($geoCountry) {
                $j->on('acv.post_id', '=', 'posts.id')
                  ->where('acv.country_code', '=', $geoCountry);
            })->addSelect(DB::raw('COALESCE(acv.views, 0) AS country_views'));
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('posts.title', 'like', $like)
                  ->orWhere('posts.short_description', 'like', $like);
            });
        }

        $cat = trim((string) $request->query('category', ''));
        if ($cat !== '' && $cat !== 'all') {
            $query->whereHas('category', fn ($c) => $c->where('slug', $cat));
        }
    }

    private function applySort(Builder $query, string $sort, ?string $geoCountry): void
    {
        $ageHours = 'GREATEST(TIMESTAMPDIFF(HOUR, posts.created_at, NOW()), 0)';

        switch ($sort) {
            case 'new':
                $query->orderByDesc('posts.created_at')->orderByDesc('posts.id');
                break;

            case 'hot':
                // Pure engagement, freshest first as the tiebreaker.
                $query->orderByRaw(self::ENGAGEMENT_SQL . ' DESC')
                      ->orderByDesc('posts.created_at');
                break;

            case 'for-you':
            default:
                // Time-decayed global engagement — the hot + new blend that
                // orders everything, and the sole signal when we can't place
                // the visitor.
                $globalScore = '(' . self::ENGAGEMENT_SQL . ' + 1) / POW(' . $ageHours . ' + 2, 1.1)';

                if ($geoCountry) {
                    // Tiered: articles that readers from the visitor's country
                    // have actually opened lead the feed (ranked by how much
                    // that country reads them), then everything else falls back
                    // to the global hot/new blend. Robust regardless of how
                    // large global view counts get. This is what makes an
                    // article read by Japanese visitors surface for other
                    // Japanese visitors even if its author is elsewhere.
                    $query->orderByRaw('CASE WHEN COALESCE(acv.views, 0) > 0 THEN 0 ELSE 1 END')
                          ->orderByRaw('COALESCE(acv.views, 0) DESC');
                }
                $query->orderByRaw($globalScore . ' DESC')
                      ->orderByDesc('posts.created_at');
                break;
        }
    }

    /** Shape a post into the JSON a blog card consumes. */
    private function cardData(Post $p, ?string $geoCountry): array
    {
        $author = $p->user;
        $engagement = (int) $p->views_count + (int) $p->likes_count * 3 + (int) $p->comments_count * 5;
        // country_views is only selected when a geo country was resolved.
        $countryViews = (int) ($p->country_views ?? 0);

        return [
            'id'             => $p->id,
            'title'          => $p->title ?: 'Untitled',
            'excerpt'        => Str::limit(strip_tags((string) ($p->short_description ?: $p->description ?: '')), 160),
            'featured_image' => $p->featured_image_url,
            'view_url'       => $p->permalink(),
            'category'       => $p->category?->name,
            'category_slug'  => $p->category?->slug,
            'author'         => $author ? [
                'name'     => $author->name,
                'username' => $author->username,
                'avatar'   => $author->avatarUrl(),
                'url'      => url('/' . $author->username),
            ] : null,
            'views'          => (int) $p->views_count,
            'likes'          => (int) $p->likes_count,
            'comments'       => (int) $p->comments_count,
            'country_views'  => $countryViews,
            'country'        => $geoCountry,
            'published_at'   => $p->created_at?->diffForHumans(),
            'is_legacy'      => (bool) $p->is_legacy,
            'is_hot'         => $engagement >= self::HOT_THRESHOLD,
            'is_new'         => $p->created_at && $p->created_at->gte(now()->subDays(self::NEW_DAYS)),
            // "Popular near you" — readers from the visitor's own country have
            // opened this article at least a few times.
            'is_local'       => $countryViews >= 3,
        ];
    }

    /** Scope of the last trending() result: 'country' or 'global'. */
    private string $trendingScope = 'global';

    /**
     * Right-rail "Popular now" list. Prefers articles most-read by visitors from
     * $country; falls back to the global most-engaged when there's no country
     * data yet (e.g. a cold start or an un-geolocatable visitor).
     */
    private function trending(?string $country)
    {
        if ($country) {
            $local = Post::query()
                ->where('posts.type', 'article')
                ->join('article_country_views as acv', function ($j) use ($country) {
                    $j->on('acv.post_id', '=', 'posts.id')
                      ->where('acv.country_code', '=', $country);
                })
                ->where('acv.views', '>', 0)
                ->with('category:id,name,slug')
                ->orderByDesc('acv.views')
                ->orderByDesc('posts.created_at')
                ->select('posts.*', 'acv.views as country_views')
                ->limit(5)
                ->get();

            if ($local->isNotEmpty()) {
                $this->trendingScope = 'country';
                return $local;
            }
        }

        $this->trendingScope = 'global';
        return Post::query()
            ->where('posts.type', 'article')
            ->whereNotNull('posts.slug')->where('posts.slug', '!=', '')
            ->with('category:id,name,slug')
            ->orderByRaw(self::ENGAGEMENT_SQL . ' DESC')
            ->orderByDesc('posts.created_at')
            ->limit(5)
            ->get();
    }

    /** Minimal ISO-3166 alpha-2 → display name map for countries we surface. */
    private function countryNames(): array
    {
        return [
            'US' => 'the United States', 'GB' => 'the United Kingdom', 'LK' => 'Sri Lanka',
            'IN' => 'India', 'JP' => 'Japan', 'CN' => 'China', 'DE' => 'Germany',
            'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal',
            'BR' => 'Brazil', 'IE' => 'Ireland', 'KR' => 'South Korea', 'IR' => 'Iran',
            'IL' => 'Israel', 'PK' => 'Pakistan', 'SE' => 'Sweden', 'AE' => 'the UAE',
            'EG' => 'Egypt', 'CA' => 'Canada', 'AU' => 'Australia', 'RU' => 'Russia',
            'NL' => 'the Netherlands', 'TR' => 'Turkey', 'SA' => 'Saudi Arabia',
            'MX' => 'Mexico', 'ID' => 'Indonesia', 'NG' => 'Nigeria', 'ZA' => 'South Africa',
            'BD' => 'Bangladesh', 'PH' => 'the Philippines', 'VN' => 'Vietnam',
            'TH' => 'Thailand', 'SG' => 'Singapore', 'MY' => 'Malaysia',
        ];
    }
}
