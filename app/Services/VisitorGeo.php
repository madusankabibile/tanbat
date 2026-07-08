<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves an IP address to [countryCode, countryName].
 *
 * Cloudflare's CF-IPCountry header is used when present (free, instant). For
 * everything else we hit the free ip-api.com endpoint, cached per-IP for a
 * week so repeat visitors never trigger a network call. Runs from the
 * RecordVisitor middleware's terminate() hook, so latency here is off the
 * page-render path.
 */
class VisitorGeo
{
    public static function lookup(?string $ip, ?string $cfCountry = null): array
    {
        // Cloudflare edge already told us the country — cheapest path.
        $cf = strtoupper((string) $cfCountry);
        if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
            return [$cf, self::name($cf)];
        }

        if (!$ip || self::isPrivate($ip)) {
            return [null, 'Local network'];
        }

        return Cache::remember("geo:$ip", now()->addDays(7), function () use ($ip) {
            try {
                $res = Http::timeout(2)->retry(1, 200)
                    ->get("http://ip-api.com/json/{$ip}", [
                        'fields' => 'status,country,countryCode',
                    ]);
                $data = $res->json();
                if (($data['status'] ?? null) === 'success') {
                    $code = strtoupper($data['countryCode'] ?? '') ?: null;
                    $nm   = $data['country'] ?? ($code ? self::name($code) : null);
                    return [$code, $nm];
                }
            } catch (\Throwable $e) {
                // Network hiccup / rate limit — fall through to Unknown.
            }
            return [null, 'Unknown'];
        });
    }

    private static function isPrivate(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /** Minimal ISO-3166 code → English name fallback (used for CF header path). */
    private static function name(string $code): string
    {
        static $map = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'IN' => 'India',
            'LK' => 'Sri Lanka', 'CA' => 'Canada', 'AU' => 'Australia',
            'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
            'NL' => 'Netherlands', 'RU' => 'Russia', 'CN' => 'China', 'JP' => 'Japan',
            'KR' => 'South Korea', 'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina',
            'ZA' => 'South Africa', 'NG' => 'Nigeria', 'EG' => 'Egypt', 'AE' => 'UAE',
            'SA' => 'Saudi Arabia', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
            'ID' => 'Indonesia', 'PH' => 'Philippines', 'MY' => 'Malaysia',
            'SG' => 'Singapore', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'TR' => 'Turkey',
            'PL' => 'Poland', 'SE' => 'Sweden', 'NO' => 'Norway', 'FI' => 'Finland',
            'DK' => 'Denmark', 'IE' => 'Ireland', 'CH' => 'Switzerland', 'AT' => 'Austria',
            'BE' => 'Belgium', 'PT' => 'Portugal', 'GR' => 'Greece', 'NZ' => 'New Zealand',
            'UA' => 'Ukraine', 'RO' => 'Romania', 'CZ' => 'Czechia', 'HU' => 'Hungary',
        ];
        return $map[$code] ?? $code;
    }
}
