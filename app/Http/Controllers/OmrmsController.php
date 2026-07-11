<?php

namespace App\Http\Controllers;

use App\Models\LegacyArticle;
use App\Models\Post;
use App\Support\Omrms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the omrms.com companion site renders that isn't just a re-skin of
 * an existing tanbat.com page: its article-grid home page and its own
 * articles-only XML sitemap. Single-article pages are handled by branching
 * inside the existing article controllers (PageController / LegacyArticleController)
 * so the view-count / geo bookkeeping stays in one place.
 */
class OmrmsController extends Controller
{
    /** Article cards per home-page batch. */
    private const PER_PAGE = 24;

    /** Sitemap URL ceiling (protocol caps a file at 50k). */
    private const SITEMAP_LIMIT = 49000;

    /**
     * omrms.com home — a paginated grid of article cards (native + migrated
     * legacy, i.e. every article-type Post). No feed, no navbar, no login.
     */
    public function home(Request $request)
    {
        $articles = Post::query()
            ->with(['user:id,name,username', 'category:id,name,slug'])
            ->where('type', 'article')
            ->whereNotNull('slug')->where('slug', '!=', '')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withPath(Omrms::url(''))   // keep ?page= links on omrms.com
            ->withQueryString();

        return view('omrms.home', ['articles' => $articles]);
    }

    /**
     * omrms.com /sitemap.xml — articles only, all pointing at omrms.com URLs.
     * Cached per host so the shared tanbat.com sitemap cache is never reused.
     */
    public function sitemap(): Response
    {
        $xml = app()->environment('testing')
            ? $this->buildSitemap()
            : Cache::remember('omrms:sitemap:' . request()->getHost(), now()->addHour(), fn () => $this->buildSitemap());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function buildSitemap(): string
    {
        $nodes = [$this->node(Omrms::url('/'), now(), 'daily', '1.0')];

        // Every article-type post (native + materialised legacy).
        Post::query()
            ->where('type', 'article')
            ->whereNotNull('slug')->where('slug', '!=', '')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::SITEMAP_LIMIT)
            ->select(['id', 'slug', 'is_legacy', 'legacy_post_id', 'created_at', 'updated_at'])
            ->chunk(1000, function ($chunk) use (&$nodes) {
                foreach ($chunk as $a) {
                    $nodes[] = $this->node(Omrms::articleUrl($a), $a->updated_at ?: $a->created_at ?: now(), 'weekly', '0.8');
                }
            });

        // Orphan legacy articles (no backing post) — excluded from the loop above,
        // added here so omrms indexes every legacy URL too.
        LegacyArticle::query()
            ->whereNotIn('old_post_id', function ($q) {
                $q->select('legacy_post_id')->from('posts')->whereNotNull('legacy_post_id');
            })
            ->orderByDesc('published_at')
            ->limit(self::SITEMAP_LIMIT)
            ->select(['old_post_id', 'slug', 'title', 'published_at'])
            ->chunk(1000, function ($chunk) use (&$nodes) {
                foreach ($chunk as $a) {
                    $nodes[] = $this->node(Omrms::legacyUrl($a), $a->published_at ?: now(), 'monthly', '0.6');
                }
            });

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode('', $nodes)
            . '</urlset>' . "\n";
    }

    private function node(string $loc, $lastmod, string $changefreq, string $priority): string
    {
        $lastmodStr = $lastmod instanceof \DateTimeInterface ? $lastmod->format('Y-m-d') : (string) $lastmod;

        return '  <url>' . "\n"
            . '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n"
            . '    <lastmod>' . $lastmodStr . '</lastmod>' . "\n"
            . '    <changefreq>' . $changefreq . '</changefreq>' . "\n"
            . '    <priority>' . $priority . '</priority>' . "\n"
            . '  </url>' . "\n";
    }
}
