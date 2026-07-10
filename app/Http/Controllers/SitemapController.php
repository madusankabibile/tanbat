<?php

namespace App\Http\Controllers;

use App\Models\BookDetail;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Response;

/**
 * XML sitemap served at /sitemap.xml.
 *
 * Covers the high-value listing pages plus every indexable article (native +
 * migrated legacy) and every book detail page. Article permalinks come from
 * Post::permalink() so native articles point at /articles/{slug} and legacy
 * ones at /blogs/{id}/{slug}; book pages use the books.show route.
 *
 * The document is cached: it lists thousands of URLs, and rebuilding that on
 * every crawler hit would be wasteful for content that changes at most a few
 * times an hour.
 */
class SitemapController extends Controller
{
    /**
     * Hard ceiling on article URLs. The sitemap protocol caps a single file at
     * 50,000 URLs; we stay under that with headroom for books and static pages.
     * If the article count ever approaches this, split into a sitemap index.
     */
    private const ARTICLE_LIMIT = 49000;

    private const CACHE_TTL_MINUTES = 60;

    public function index(): Response
    {
        // Skip the cache under tests so assertions see current data.
        $xml = app()->environment('testing')
            ? $this->build()
            : Cache::remember('sitemap:xml', now()->addMinutes(self::CACHE_TTL_MINUTES), fn () => $this->build());

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function build(): string
    {
        $root  = rtrim((string) config('app.url'), '/') . '/';
        $nodes = [];

        // Listing / entry pages first, most important at the top.
        $nodes[] = $this->urlNode($root, now(), 'daily', '1.0');
        $nodes[] = $this->urlNode(route('books'), now(), 'daily', '0.7');
        if (Route::has('blog')) {
            $nodes[] = $this->urlNode(route('blog'), now(), 'daily', '0.7');
        }
        // /discover/people is intentionally omitted: PeopleController redirects
        // guests to home, so a crawler would only ever get a 302 from it.

        // Every indexable article. Chunked so we never hold thousands of models
        // in memory at once.
        Post::query()
            ->where('type', 'article')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ARTICLE_LIMIT)
            ->select(['id', 'slug', 'is_legacy', 'legacy_post_id', 'type', 'created_at', 'updated_at'])
            ->chunk(1000, function ($chunk) use (&$nodes) {
                foreach ($chunk as $a) {
                    $lastmod = $a->updated_at ?: $a->created_at ?: now();
                    $nodes[] = $this->urlNode($a->permalink(), $lastmod, 'weekly', '0.8');
                }
            });

        // Every book detail page.
        BookDetail::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('updated_at')
            ->select(['slug', 'created_at', 'updated_at'])
            ->chunk(1000, function ($chunk) use (&$nodes) {
                foreach ($chunk as $b) {
                    $lastmod = $b->updated_at ?: $b->created_at ?: now();
                    $nodes[] = $this->urlNode(route('books.show', $b->slug), $lastmod, 'monthly', '0.6');
                }
            });

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode('', $nodes)
            . '</urlset>' . "\n";
    }

    private function urlNode(string $loc, $lastmod, string $changefreq, string $priority): string
    {
        $lastmodStr = $lastmod instanceof \DateTimeInterface
            ? $lastmod->format('Y-m-d')
            : (string) $lastmod;

        return '  <url>' . "\n"
            . '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n"
            . '    <lastmod>' . $lastmodStr . '</lastmod>' . "\n"
            . '    <changefreq>' . $changefreq . '</changefreq>' . "\n"
            . '    <priority>' . $priority . '</priority>' . "\n"
            . '  </url>' . "\n";
    }
}
