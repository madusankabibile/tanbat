<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

/**
 * XML sitemap of the latest article posts (native + migrated legacy), served at
 * /sitemap.xml for search engines. Each entry uses Post::permalink() so native
 * articles point at /articles/{slug} and legacy ones at /blogs/{id}/{slug}.
 */
class SitemapController extends Controller
{
    /** How many of the most recent articles to list. */
    private const LIMIT = 50;

    public function index(): Response
    {
        $articles = Post::query()
            ->where('type', 'article')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'slug', 'is_legacy', 'legacy_post_id', 'type', 'created_at', 'updated_at']);

        $urls = [];

        // Homepage first, so the sitemap has a canonical root entry.
        $urls[] = $this->urlNode(rtrim(config('app.url'), '/') . '/', now(), 'daily', '1.0');

        foreach ($articles as $a) {
            $lastmod = $a->updated_at ?: $a->created_at ?: now();
            $urls[] = $this->urlNode($a->permalink(), $lastmod, 'weekly', '0.8');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode('', $urls)
            . '</urlset>' . "\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
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
