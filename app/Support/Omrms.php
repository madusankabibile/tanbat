<?php

namespace App\Support;

use App\Models\LegacyArticle;
use App\Models\Post;
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

    /** Site/brand name used in <title>, og:site_name and the JSON-LD publisher. */
    public static function siteName(): string
    {
        return 'OMRMS';
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
}
