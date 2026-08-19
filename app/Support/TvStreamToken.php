<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Mints and verifies the opaque tokens that stand in for a TV channel's real
 * HLS URLs on the public player page.
 *
 * A token is an AES-256-CBC ciphertext (Laravel's Crypt, HMAC-authenticated)
 * carrying the upstream URL, the channel id, an expiry and — optionally — a
 * fingerprint of the requesting session. Two consequences follow:
 *
 *   1. The browser never sees the origin manifest, only "/tv/s/{token}/…".
 *      A sniffer extension harvests the proxy URL, not the source.
 *   2. A token cannot be forged or edited, so the proxy can trust the URL it
 *      carries and there is no SSRF hole — a client can only ever replay a
 *      URL we ourselves minted, and only until it expires.
 *
 * Base64url-encoded so a token drops into a path segment untouched.
 */
class TvStreamToken
{
    /**
     * @param string $url       Absolute upstream URL this token stands for.
     * @param int    $channelId Owning tv_channels.id (for logging/limits).
     * @param string $kind      'manifest' | 'segment' | 'key'
     */
    public static function mint(Request $request, string $url, int $channelId, string $kind = 'segment'): string
    {
        // A manifest token has to survive the whole viewing session — hls.js
        // re-fetches a live playlist every few seconds off the same URL. That's
        // safe because the token is session-bound, so a copied manifest URL is
        // inert in any other browser. Segment and key tokens, which are minted
        // fresh on every manifest reload, get the short TTL.
        $ttl = $kind === 'manifest'
            ? (int) config('tv.manifest_ttl', 14400)
            : (int) config('tv.token_ttl', 90);

        $payload = [
            'u' => $url,
            'c' => $channelId,
            'k' => $kind,
            'e' => now()->getTimestamp() + max(10, $ttl),
            's' => config('tv.bind_session', true) ? self::fingerprint($request) : null,
        ];

        return self::b64UrlEncode(Crypt::encryptString(json_encode($payload)));
    }

    /**
     * Decode + validate a token, returning its payload, or null when it is
     * malformed, expired, or was minted for a different viewer.
     *
     * @return array{u:string,c:int,k:string,e:int,s:?string}|null
     */
    public static function open(Request $request, string $token): ?array
    {
        try {
            $raw = Crypt::decryptString(self::b64UrlDecode($token));
        } catch (DecryptException) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['u']) || empty($data['e'])) {
            return null;
        }

        if (now()->getTimestamp() > (int) $data['e']) {
            return null;
        }

        // Session binding: a URL copied out of devtools is inert elsewhere.
        if (config('tv.bind_session', true)) {
            if (!hash_equals((string) ($data['s'] ?? ''), self::fingerprint($request))) {
                return null;
            }
        }

        // Only ever proxy http(s) — belt and braces on top of the fact that we
        // minted this URL ourselves.
        if (!preg_match('~^https?://~i', (string) $data['u'])) {
            return null;
        }

        return $data;
    }

    /**
     * Stable per-viewer fingerprint. The session id is the real key; IP and
     * user-agent are folded in so a stolen cookie-less URL still fails.
     */
    private static function fingerprint(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : '';

        return hash('sha256', implode('|', [
            $sessionId,
            $request->ip(),
            (string) $request->userAgent(),
            (string) config('app.key'),
        ]));
    }

    private static function b64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $txt): string
    {
        return (string) base64_decode(strtr($txt, '-_', '+/'), true);
    }
}
