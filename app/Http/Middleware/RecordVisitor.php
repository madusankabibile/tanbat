<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use App\Services\VisitorGeo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logs each real HTML page view into two tables:
 *
 *   `visitors`           one row per distinct visitor (deduped by IP + user
 *                        agent), holding their *latest* page/referrer/country.
 *                        Feeds the "Recent visitors" widget (partials/stat-counter).
 *   `visitor_page_views` one row per (visitor, path, day) with a hit counter, so
 *                        views-per-day and top-pages can be answered honestly.
 *
 * All work happens in terminate(), after the response has been flushed to the
 * browser, so it never adds latency to page loads.
 */
class RecordVisitor
{
    /** Path prefixes we never treat as a "page view". */
    private const SKIP_PREFIXES = ['api', 'auth', 'admin', 'tasks', 'build', 'storage', 'public', 'up'];

    /** `visitors` rows we'll never show are pruned past this age. */
    private const VISITOR_RETENTION_DAYS = 30;

    /** Page-view buckets are ~30 bytes each, so we can afford a longer window. */
    private const PAGE_VIEW_RETENTION_DAYS = 90;

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        try {
            if (!$this->shouldLog($request, $response)) {
                return;
            }

            $ip = $request->ip();
            $ua = substr((string) $request->userAgent(), 0, 255);
            $token = sha1($ip . '|' . $ua);
            $path = substr('/' . ltrim($request->path(), '/'), 0, 255);

            [$code, $name] = VisitorGeo::lookup($ip, $request->header('CF-IPCountry'));

            $referrer = $request->headers->get('referer');
            if ($referrer) {
                // Treat same-host navigation as internal so the widget can label it.
                $refHost = parse_url($referrer, PHP_URL_HOST);
                if ($refHost && $refHost === $request->getHost()) {
                    $referrer = null;
                }
            }

            $visitor = Visitor::firstOrNew(['visitor_token' => $token]);
            $visitor->fill([
                'ip_address'   => $ip,
                'country_code' => $code,
                'country_name' => $name,
                'page'         => $path,
                'referrer'     => $referrer ? substr($referrer, 0, 255) : null,
                'user_agent'   => $ua,
            ]);
            $visitor->hits = ($visitor->exists ? (int) $visitor->hits : 0) + 1;
            $visitor->save();

            $this->recordPageView($token, $path);

            // Occasionally prune rows we'll never show to keep the tables small.
            if (random_int(1, 50) === 1) {
                Visitor::where('updated_at', '<', now()->subDays(self::VISITOR_RETENTION_DAYS))->delete();
                DB::table('visitor_page_views')
                    ->where('day', '<', now()->subDays(self::PAGE_VIEW_RETENTION_DAYS)->toDateString())
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Analytics must never break a request — swallow everything.
        }
    }

    /**
     * Bump today's hit counter for this visitor + path.
     *
     * A single atomic upsert against the vpv_token_path_day_unique key: two
     * concurrent requests from the same visitor can't lose a hit the way a
     * read-then-write would. MySQL/MariaDB syntax, which is what this app runs on.
     */
    private function recordPageView(string $token, string $path): void
    {
        $now = now();

        DB::statement(
            'INSERT INTO visitor_page_views (visitor_token, path, day, hits, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE hits = hits + 1, updated_at = VALUES(updated_at)',
            [$token, $path, $now->toDateString(), $now, $now]
        );
    }

    private function shouldLog(Request $request, $response): bool
    {
        if (!$request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
        if ($status >= 400) {
            return false;
        }

        $ctype = method_exists($response, 'headers')
            ? (string) $response->headers->get('content-type')
            : '';
        if ($ctype !== '' && !str_contains($ctype, 'text/html')) {
            return false;
        }

        $first = explode('/', trim($request->path(), '/'))[0] ?? '';
        return !in_array($first, self::SKIP_PREFIXES, true);
    }
}
