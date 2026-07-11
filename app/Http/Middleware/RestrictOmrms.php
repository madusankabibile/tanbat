<?php

namespace App\Http\Middleware;

use App\Support\Omrms;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * omrms.com is an article-only companion site (see App\Support\Omrms). It shares
 * tanbat.com's routes, so without a guard every non-article page — profiles,
 * /books, /search, the feed, etc. — would render tanbat content under the
 * omrms.com host. This middleware lets omrms.com serve ONLY its own pages and
 * 404s everything else. On tanbat.com it is a pass-through.
 */
class RestrictOmrms
{
    /** The only route names omrms.com is allowed to serve. */
    private const ALLOWED = [
        'home',            // /
        'home.feed',       // /home (alias)
        'articles.show',   // /articles/{slug}       — native article
        'legacy.article',  // /blogs/{id}/{slug}      — migrated legacy article
        'search',          // /search?q=              — article search results
        'omrms.search.api',// /api/omrms/search       — live search JSON (in web.php)
        'omrms.visitors.api', // /api/omrms/visitors  — recent-visitors JSON (in web.php)
        'omrms.categories',// /categories
        'omrms.category',  // /category/{slug}
        'omrms.publish',   // /how-to-publish
        'sitemap',         // /sitemap.xml
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Omrms::isActive()) {
            $name = optional($request->route())->getName();

            if (!in_array($name, self::ALLOWED, true)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
