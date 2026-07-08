<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use App\Services\VisitorGeo;
use Closure;
use Illuminate\Http\Request;

/**
 * Logs each real HTML page view into the `visitors` table (one row per distinct
 * visitor, deduped by IP + user agent). Feeds the "Recent visitors" widget
 * (partials/stat-counter). All work happens in terminate(), after the response
 * has been flushed to the browser, so it never adds latency to page loads.
 */
class RecordVisitor
{
    /** Path prefixes we never treat as a "page view". */
    private const SKIP_PREFIXES = ['api', 'auth', 'admin', 'tasks', 'build', 'storage', 'public', 'up'];

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
                'page'         => '/' . ltrim($request->path(), '/'),
                'referrer'     => $referrer ? substr($referrer, 0, 255) : null,
                'user_agent'   => $ua,
            ]);
            $visitor->hits = ($visitor->exists ? (int) $visitor->hits : 0) + 1;
            $visitor->save();

            // Occasionally prune rows we'll never show to keep the table small.
            if (random_int(1, 50) === 1) {
                Visitor::where('updated_at', '<', now()->subDays(30))->delete();
            }
        } catch (\Throwable $e) {
            // Analytics must never break a request — swallow everything.
        }
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
