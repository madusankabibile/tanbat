<?php

namespace App\Support;

use App\Models\LegacyArticle;
use App\Models\Post;
use App\Models\Visitor;
use Illuminate\Support\Str;

/**
 * omrms.com is an article-only companion site served from this same codebase /
 * public_html as tanbat.com. It has its own stripped-down, ad-supported UI (no
 * navbar, no login) that only ever shows articles — new and migrated legacy.
 *
 * Everything omrms-specific funnels through here. In particular URL building:
 * the global URL generator is pinned to APP_URL (tanbat.com) in
 * AppServiceProvider, so url()/route()/permalink() would emit tanbat.com links
 * on omrms.com too. These helpers instead build against the *live request host*
 * (request()->getSchemeAndHttpHost()), which is unaffected by that pin — so
 * omrms.com pages link to, and self-canonical to, omrms.com.
 */
class Omrms
{
    /** True when the current request is being served under the omrms.com domain. */
    public static function isActive(): bool
    {
        $host = strtolower((string) request()->getHost());

        return $host === 'omrms.com' || str_ends_with($host, '.omrms.com');
    }

    /** Canonical public origin for omrms.com (used off-host, e.g. in tanbat admin). */
    public const CANONICAL_URL = 'https://omrms.com';

    /** Site/brand name used in <title>, og:site_name and the JSON-LD publisher. */
    public static function siteName(): string
    {
        return 'OMRMS';
    }

    /**
     * omrms.com URL for a post, built from the FIXED canonical host rather than
     * the request host. Use this off-domain — e.g. links in the tanbat admin,
     * which runs under tanbat.com but wants to point at the omrms.com article.
     */
    public static function canonicalArticleUrl(Post $post): string
    {
        return self::CANONICAL_URL . self::articlePath($post);
    }

    /**
     * Most recent site visitors for the "Recent visitors" card, formatted for
     * display. Same shape PageController@visitors returns for tanbat's widget.
     */
    public static function recentVisitors(int $limit = 8): array
    {
        return Visitor::query()
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['country_code', 'country_name', 'page', 'referrer', 'updated_at'])
            ->map(fn (Visitor $v) => [
                'country_code' => $v->country_code ? strtolower($v->country_code) : null,
                'country_name' => $v->country_name ?: 'Unknown',
                'page'         => $v->page ?: '/',
                'referrer'     => $v->referrer ? (parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer) : null,
                'when'         => ($v->updated_at?->diffForHumans(null, true) ?? '') . ' ago',
            ])
            ->all();
    }

    /**
     * Absolute URL on the CURRENT request host (omrms.com), bypassing the global
     * URL generator that APP_URL pins to tanbat.com. Pass a path like '/foo'.
     */
    public static function url(string $path = ''): string
    {
        return rtrim(request()->getSchemeAndHttpHost(), '/') . '/' . ltrim($path, '/');
    }

    /** Path (no host) to an article-type Post — native or migrated legacy. */
    public static function articlePath(Post $post): string
    {
        return ($post->is_legacy && $post->legacy_post_id)
            ? '/blogs/' . $post->legacy_post_id . '/' . $post->slug
            : '/articles/' . $post->slug;
    }

    /** omrms.com canonical URL for an article-type Post. */
    public static function articleUrl(Post $post): string
    {
        return self::url(self::articlePath($post));
    }

    /** omrms.com canonical URL for an orphan LegacyArticle (legacy_articles table). */
    public static function legacyUrl(LegacyArticle $article): string
    {
        $slug = $article->slug ?: LegacyArticle::slugify($article->title);

        return self::url('/blogs/' . $article->old_post_id . '/' . $slug);
    }

    /** Clean, clamped plain-text description drawn from HTML or text. */
    public static function describe(?string $text, int $limit = 200): string
    {
        return (string) Str::of(strip_tags((string) $text))->squish()->limit($limit, '');
    }

    /**
     * Link to an author's ORIGINAL profile on tanbat.com. omrms.com has no
     * profiles of its own — authorship lives on the main site — so bylines point
     * back there. Returns null for anonymous / unknown authors.
     */
    public static function authorUrl(?string $username): ?string
    {
        $username = trim((string) $username);

        return $username === '' ? null : 'https://tanbat.com/' . ltrim($username, '/');
    }

    /**
     * Site-wide article stats for the "Site stats" card. Cached for an hour —
     * the SUM/COUNT over thousands of rows is too heavy to run per request.
     * "articles" counts every reachable article URL: article-type posts plus
     * orphan legacy rows (those with no backing post).
     */
    public static function siteStats(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('omrms:site-stats', now()->addHour(), function () {
            $orphanLegacy = \App\Models\LegacyArticle::query()
                ->whereNotIn('old_post_id', function ($q) {
                    $q->select('legacy_post_id')->from('posts')->whereNotNull('legacy_post_id');
                })->count();

            return [
                'articles'   => (int) \App\Models\Post::where('type', 'article')->count() + $orphanLegacy,
                'categories' => (int) \App\Models\Category::whereHas('posts', fn ($q) => $q->where('type', 'article'))->count(),
                'authors'    => (int) \App\Models\Post::where('type', 'article')->whereNotNull('user_id')->distinct()->count('user_id'),
                'reads'      => (int) \App\Models\Post::where('type', 'article')->sum('views_count'),
            ];
        });
    }

    /** Compact number for stat tiles: 950 → "950", 12300 → "12.3k", 1_200_000 → "1.2M". */
    public static function shortNum(int $n): string
    {
        if ($n >= 1000000) {
            return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
        }
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
        }

        return (string) $n;
    }

    /** Rough reading time in minutes (~200 wpm), floored at 1. */
    public static function readingTime(?string $html): int
    {
        $words = str_word_count(strip_tags((string) $html));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Split article HTML into two halves at the nearest paragraph boundary, so a
     * mid-article ad can be dropped between them. Returns [firstHalf, secondHalf];
     * secondHalf is '' when the body is too short (or has no <p>) to split.
     */
    public static function halves(?string $html): array
    {
        $html  = (string) $html;
        $parts = array_values(array_filter(
            preg_split('~(?<=</p>)~i', $html) ?: [$html],
            fn ($p) => trim((string) $p) !== ''
        ));

        if (count($parts) < 4) {
            return [$html, ''];
        }

        $mid = (int) floor(count($parts) / 2);

        return [implode('', array_slice($parts, 0, $mid)), implode('', array_slice($parts, $mid))];
    }

    /**
     * Rewrite an image URL that lives on THIS shared server (tanbat.com / the
     * APP_URL host / a relative path) so it is served from the current request
     * host instead. The uploads + storage dirs sit in the shared public_html,
     * so omrms.com/... hits the same file — and, unlike an absolute tanbat.com
     * src, it loads same-origin instead of being blocked by cross-domain
     * hotlink protection. Third-party hosts (e.g. old domyl.com images) are
     * left untouched — we don't own those files.
     */
    public static function img(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, 'data:')) {
            return $url === '' ? null : $url;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Relative / root-relative path → serve from the current host.
        if ($host === '') {
            return self::url($url);
        }

        // Same-server host → swap the host, keep the path (+ query).
        if (in_array($host, self::ownHosts(), true)) {
            $path  = (string) parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            return self::url($path) . ($query ? '?' . $query : '');
        }

        return $url;
    }

    /** Apply img() to every <img src="…"> inside a body of HTML. */
    public static function bodyImages(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '~(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2~is',
            fn ($m) => $m[1] . $m[2] . self::img($m[3]) . $m[2],
            $html
        ) ?? $html;
    }

    /** Hosts whose files physically live in this account's shared public_html. */
    private static function ownHosts(): array
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return array_values(array_filter(array_unique([
            'tanbat.com', 'www.tanbat.com', $appHost,
        ])));
    }
}
