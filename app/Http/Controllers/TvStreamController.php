<?php

namespace App\Http\Controllers;

use App\Models\TvChannel;
use App\Support\TvStreamToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The HLS reverse-proxy that sits between the public TV player and a channel's
 * real stream.
 *
 * Flow:
 *   1. The player page POSTs to /tv/{slug}/session (same-origin, CSRF-checked)
 *      and receives a signed, expiring, session-bound manifest URL. The page's
 *      HTML/JS never contains the origin .m3u8.
 *   2. GET /tv/s/{token}/index.m3u8 fetches the upstream manifest server-side
 *      and REWRITES every URI in it — variants, segments, encryption keys — to
 *      point back here under freshly minted tokens. Nothing that leaves this
 *      server references the origin host.
 *   3. GET /tv/s/{token}/seg fetches and streams one segment through.
 *
 * What this buys: a network sniffer (or an m3u8-grabber extension) sees only
 * tanbat.com proxy URLs, and each one dies within config('tv.token_ttl')
 * seconds and only works for the session that minted it. What it does NOT buy:
 * true content protection. The bytes are still delivered unencrypted to a
 * browser that asked nicely. Only DRM changes that.
 */
class TvStreamController extends Controller
{
    /**
     * POST /tv/{channel:slug}/session
     *
     * Hand the player a playback URL. Deliberately a POST so the URL can't be
     * produced by pasting a link, and so it inherits CSRF + same-origin checks.
     */
    public function session(Request $request, TvChannel $channel): JsonResponse
    {
        if (!$channel->is_active) {
            return response()->json(['message' => 'This channel is offline.'], 404);
        }

        // Only serve a session to our own player page. Not a security boundary
        // on its own (headers are forgeable) — it just stops casual hotlinking.
        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== ''
            && !str_starts_with($referer, rtrim((string) config('app.url'), '/'))
            && parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return response()->json(['message' => 'Playback not permitted from here.'], 403);
        }

        $token = TvStreamToken::mint($request, $channel->stream_url, $channel->id, 'manifest');

        return response()->json([
            'src' => route('tv.stream.playlist', ['token' => $token]),
            // The player re-mints before this elapses so long views never stall
            // on an expired manifest token.
            'ttl' => (int) config('tv.token_ttl', 90),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * GET /tv/s/{token}/index.m3u8 — proxy + rewrite an HLS manifest.
     */
    public function playlist(Request $request, string $token)
    {
        $data = TvStreamToken::open($request, $token);
        if (!$data) {
            return response('Expired or invalid playback token.', 403)
                ->header('Cache-Control', 'no-store, private');
        }

        $channel = TvChannel::find($data['c']);
        if (!$channel || !$channel->is_active) {
            return response('Channel unavailable.', 404);
        }

        try {
            $res = $this->upstream($channel)
                ->timeout((int) config('tv.manifest_timeout', 12))
                ->get($data['u']);
        } catch (\Throwable $e) {
            Log::warning('TV manifest fetch failed', ['channel' => $channel->id, 'error' => $e->getMessage()]);
            return response('Upstream unreachable.', 502);
        }

        if (!$res->successful()) {
            return response('Upstream returned ' . $res->status() . '.', 502);
        }

        $body = $this->rewriteManifest($request, $res->body(), $data['u'], $channel);

        return response($body, 200)
            ->header('Content-Type', 'application/vnd.apple.mpegurl')
            // A live manifest must never be cached — and a cached one would
            // outlive its own tokens anyway.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * GET /tv/s/{token}/seg — proxy one segment (or encryption key) through.
     *
     * Streamed rather than buffered so a multi-megabyte segment never sits in
     * PHP memory and the first bytes reach the player while the rest is still
     * in flight.
     */
    public function segment(Request $request, string $token): StreamedResponse|\Illuminate\Http\Response
    {
        $data = TvStreamToken::open($request, $token);
        if (!$data) {
            return response('Expired or invalid playback token.', 403);
        }

        $channel = TvChannel::find($data['c']);
        if (!$channel || !$channel->is_active) {
            return response('Channel unavailable.', 404);
        }

        try {
            $res = $this->upstream($channel)
                ->withOptions(['stream' => true])
                ->timeout((int) config('tv.segment_timeout', 25))
                ->get($data['u']);
        } catch (\Throwable $e) {
            Log::warning('TV segment fetch failed', ['channel' => $channel->id, 'error' => $e->getMessage()]);
            return response('Upstream unreachable.', 502);
        }

        if (!$res->successful()) {
            return response('Upstream returned ' . $res->status() . '.', 502);
        }

        $stream = $res->toPsrResponse()->getBody();
        $type   = $res->header('Content-Type') ?: 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(64 * 1024);
                // Push each chunk out rather than letting it pool in PHP's
                // output buffer — this is the difference between streaming and
                // downloading the whole segment before sending any of it.
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            $stream->close();
        }, 200, [
            'Content-Type'           => $type,
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Rewrite every URI in an HLS manifest to a signed proxy URL.
     *
     * Three kinds of line carry a URI:
     *   - a bare line (a segment, or a variant playlist in a master manifest)
     *   - URI="…" inside #EXT-X-KEY / #EXT-X-MAP / #EXT-X-MEDIA /
     *     #EXT-X-I-FRAME-STREAM-INF
     * Anything following #EXT-X-STREAM-INF is itself a manifest, so it has to
     * come back here as a manifest token (and get rewritten in turn) rather
     * than be streamed out as an opaque segment.
     */
    private function rewriteManifest(Request $request, string $body, string $baseUrl, TvChannel $channel): string
    {
        $out           = [];
        $nextIsVariant = false;

        foreach (preg_split('/\R/', $body) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $out[] = $line;
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                // Tags whose URI="…" attribute points at another manifest
                // rather than at media bytes.
                $isManifestAttr = str_starts_with($trimmed, '#EXT-X-MEDIA')
                    || str_starts_with($trimmed, '#EXT-X-I-FRAME-STREAM-INF');

                if (str_contains($trimmed, 'URI="')) {
                    $kind = $isManifestAttr ? 'manifest' : 'segment';
                    $trimmed = preg_replace_callback(
                        '/URI="([^"]*)"/',
                        function ($m) use ($request, $baseUrl, $channel, $kind) {
                            if ($m[1] === '') {
                                return $m[0];
                            }
                            return 'URI="' . $this->proxyUrl($request, $m[1], $baseUrl, $channel, $kind) . '"';
                        },
                        $trimmed
                    );
                }

                if (str_starts_with($trimmed, '#EXT-X-STREAM-INF')) {
                    $nextIsVariant = true;
                }

                $out[] = $trimmed;
                continue;
            }

            // A bare URI line.
            $kind = $nextIsVariant ? 'manifest' : 'segment';
            $nextIsVariant = false;
            $out[] = $this->proxyUrl($request, $trimmed, $baseUrl, $channel, $kind);
        }

        return implode("\n", $out);
    }

    /** Absolutize a manifest URI against its parent, then wrap it in a token. */
    private function proxyUrl(Request $request, string $uri, string $baseUrl, TvChannel $channel, string $kind): string
    {
        $absolute = $this->absolutize($uri, $baseUrl);
        $token    = TvStreamToken::mint($request, $absolute, $channel->id, $kind);

        return $kind === 'manifest'
            ? route('tv.stream.playlist', ['token' => $token])
            : route('tv.stream.segment', ['token' => $token]);
    }

    /**
     * Resolve a (possibly relative) manifest URI against the manifest's own
     * URL — the standard scheme-relative / root-relative / path-relative cases.
     */
    private function absolutize(string $uri, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $uri)) {
            return $uri;
        }
        if (str_starts_with($uri, '//')) {
            return (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . ':' . $uri;
        }

        $parts  = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $root   = $scheme . '://' . $host . $port;

        if (str_starts_with($uri, '/')) {
            return $root . $uri;
        }

        // Relative to the manifest's own directory. Collapse any ../ so a
        // nested playlist can climb out of its folder the way a browser would.
        $dir      = rtrim(dirname($parts['path'] ?? '/'), '/');
        $segments = [];
        foreach (explode('/', ltrim($dir, '/') . '/' . $uri) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $seg;
        }

        return $root . '/' . implode('/', $segments);
    }

    /**
     * A pending HTTP request configured for this channel's upstream: the
     * headers some CDNs insist on, plus the local no-CA-bundle escape hatch.
     */
    private function upstream(TvChannel $channel): \Illuminate\Http\Client\PendingRequest
    {
        $headers = [
            'User-Agent' => $channel->user_agent ?: config('tv.user_agent'),
            'Accept'     => '*/*',
        ];

        if ($channel->referer) {
            $headers['Referer'] = $channel->referer;
            $headers['Origin']  = rtrim((string) preg_replace('~^(https?://[^/]+).*$~i', '$1', $channel->referer), '/');
        }

        return Http::withHeaders($headers)
            ->withOptions([
                'verify'          => (bool) config('tv.verify_ssl', true),
                'allow_redirects' => ['max' => 5, 'strict' => false, 'referer' => true],
            ]);
    }
}
