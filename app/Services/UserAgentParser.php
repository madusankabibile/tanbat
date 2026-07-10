<?php

namespace App\Services;

/**
 * Classifies a raw User-Agent string into a browser, a device class, an OS, and
 * a bot flag, for the admin statistics breakdowns.
 *
 * Deliberately a small ordered set of substring rules rather than a UA database:
 * the admin panel only needs to group a few thousand rows into a handful of
 * buckets, and every unmatched string lands in an explicit "Other"/"Unknown"
 * bucket rather than being silently dropped.
 *
 * Order matters throughout — Edge announces itself as Chrome, Chrome announces
 * itself as Safari, and most bots announce themselves as Mozilla. The most
 * specific token is always tested first.
 */
class UserAgentParser
{
    /** Substrings that mark automated traffic. Checked before anything else. */
    public const BOT_TOKENS = [
        'bot', 'crawl', 'spider', 'slurp', 'search', 'fetch', 'monitor', 'scrape',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'okhttp', 'axios', 'node-fetch', 'guzzlehttp', 'libwww-perl',
        'facebookexternalhit', 'whatsapp', 'telegrambot', 'slackbot', 'discordbot',
        'twitterbot', 'linkedinbot', 'pinterest', 'redditbot', 'embedly',
        'bingpreview', 'lighthouse', 'pagespeed', 'gtmetrix', 'uptimerobot',
        'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot', 'dataprovider',
    ];

    /** [needle => label], most specific first. */
    private const BROWSERS = [
        'edg/'        => 'Edge',
        'edga/'       => 'Edge',
        'edgios/'     => 'Edge',
        'opr/'        => 'Opera',
        'opera'       => 'Opera',
        'samsungbrowser' => 'Samsung Internet',
        'yabrowser'   => 'Yandex',
        'ucbrowser'   => 'UC Browser',
        'vivaldi'     => 'Vivaldi',
        'brave'       => 'Brave',
        'firefox'     => 'Firefox',
        'fxios'       => 'Firefox',
        'crios'       => 'Chrome',
        'chromium'    => 'Chrome',
        'chrome'      => 'Chrome',
        'safari'      => 'Safari',
        'msie'        => 'Internet Explorer',
        'trident'     => 'Internet Explorer',
    ];

    /** [needle => label], most specific first. */
    private const PLATFORMS = [
        'windows nt'  => 'Windows',
        'android'     => 'Android',
        'iphone'      => 'iOS',
        'ipad'        => 'iOS',
        'ipod'        => 'iOS',
        'cros'        => 'ChromeOS',
        'mac os x'    => 'macOS',
        'macintosh'   => 'macOS',
        'ubuntu'      => 'Linux',
        'linux'       => 'Linux',
    ];

    /**
     * @return array{browser:string, platform:string, device:string, is_bot:bool}
     */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);

        if ($ua === '') {
            return ['browser' => 'Unknown', 'platform' => 'Unknown', 'device' => 'Unknown', 'is_bot' => false];
        }

        $s = strtolower($ua);

        if (self::isBot($s)) {
            return ['browser' => 'Bot', 'platform' => 'Bot', 'device' => 'Bot', 'is_bot' => true];
        }

        return [
            'browser'  => self::match($s, self::BROWSERS, 'Other'),
            'platform' => self::match($s, self::PLATFORMS, 'Other'),
            'device'   => self::device($s),
            'is_bot'   => false,
        ];
    }

    public static function isBot(?string $ua): bool
    {
        $s = strtolower(trim((string) $ua));
        if ($s === '') {
            return false;
        }

        foreach (self::BOT_TOKENS as $token) {
            if (str_contains($s, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tablets must be tested before phones: an iPad's UA contains neither
     * "mobile" nor "iphone", but an Android tablet's UA contains "android"
     * *without* the "mobile" token that Android phones carry.
     */
    private static function device(string $s): string
    {
        if (str_contains($s, 'ipad') || str_contains($s, 'tablet') || str_contains($s, 'kindle') || str_contains($s, 'playbook')) {
            return 'Tablet';
        }
        if (str_contains($s, 'android') && !str_contains($s, 'mobile')) {
            return 'Tablet';
        }
        if (str_contains($s, 'mobile') || str_contains($s, 'iphone') || str_contains($s, 'ipod')
            || str_contains($s, 'android') || str_contains($s, 'windows phone')) {
            return 'Mobile';
        }
        return 'Desktop';
    }

    /** @param array<string,string> $map */
    private static function match(string $s, array $map, string $fallback): string
    {
        foreach ($map as $needle => $label) {
            if (str_contains($s, $needle)) {
                return $label;
            }
        }
        return $fallback;
    }
}
