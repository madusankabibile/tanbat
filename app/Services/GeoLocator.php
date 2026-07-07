<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves a visitor's country (ISO-3166 alpha-2, uppercase) from their IP.
 *
 * Strategy, cheapest-first:
 *   1. Cloudflare's `CF-IPCountry` request header — free and instant when the
 *      site is proxied through Cloudflare (no lookup, no rate limit).
 *   2. A cached external lookup against ip-api.com keyed by IP. Successful
 *      lookups are cached 30 days (an IP's country is stable); misses are
 *      cached briefly so a transient API outage self-heals.
 *
 * Private / reserved / localhost IPs return null (nothing to geolocate).
 */
class GeoLocator
{
    /** Non-country placeholders Cloudflare / APIs may return. */
    private const NON_COUNTRIES = ['XX', 'T1', 'AP', 'EU', 'A1', 'A2', 'O1'];

    /** Resolve the country for the current request. */
    public function country(Request $request): ?string
    {
        $cf = strtoupper(trim((string) $request->header('CF-IPCountry', '')));
        if ($this->isValidCountry($cf)) {
            return $cf;
        }

        return $this->countryForIp($request->ip());
    }

    /** Resolve (and cache) the country for a specific IP address. */
    public function countryForIp(?string $ip): ?string
    {
        // Skip missing, private (LAN) and reserved (localhost) addresses — an
        // external geo API can't place them, and CF headers won't exist locally.
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $key    = 'geo:country:' . $ip;
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === '' ? null : $cached; // '' is a cached known-miss
        }

        $cc = $this->lookupApi($ip);

        // Cache hits for a month; cache misses for a day so an API blip doesn't
        // pin this IP to "unknown" long-term.
        Cache::put($key, $cc ?? '', $cc ? now()->addDays(30) : now()->addDay());

        return $cc;
    }

    private function lookupApi(string $ip): ?string
    {
        try {
            $resp = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,countryCode',
            ]);
            if ($resp->ok() && $resp->json('status') === 'success') {
                $cc = strtoupper((string) $resp->json('countryCode'));
                return $this->isValidCountry($cc) ? $cc : null;
            }
        } catch (\Throwable $e) {
            // Network/API failure — treat as unknown (cached as a short miss).
        }
        return null;
    }

    private function isValidCountry(?string $cc): bool
    {
        return is_string($cc)
            && preg_match('/^[A-Z]{2}$/', $cc)
            && !in_array($cc, self::NON_COUNTRIES, true);
    }
}
