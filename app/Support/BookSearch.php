<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Central definition of the book search engines and their admin-tunable
 * settings (active engine + per-engine domain). Shared by the admin panel
 * (App\Http\Controllers\Admin\BookSearchController) and the wizard backend
 * (App\Http\Controllers\AssistantController) so both read the same source.
 *
 * Domains are stored without a trailing slash and rotate from time to time —
 * that's exactly why the admin can edit them without a deploy.
 */
class BookSearch
{
    /** Settings key for the currently-selected engine slug. */
    public const KEY_ENGINE = 'book_search.engine';

    /** Per-engine metadata: label, the scraper library file, and default domain. */
    public const ENGINES = [
        'annas' => [
            'label'          => "Anna's Archive",
            'lib'            => 'temp/annas.php',     // relative to public_path()
            'default_domain' => 'https://annas-archive.gl',
            'domain_key'     => 'book_search.domain.annas',
        ],
        'zlib' => [
            'label'          => 'Z-Library',
            'lib'            => 'temp/zlib.php',
            'default_domain' => 'https://z-library.sk',
            'domain_key'     => 'book_search.domain.zlib',
        ],
    ];

    /** Engine used when nothing has been configured yet. */
    public const DEFAULT_ENGINE = 'annas';

    /** The active engine slug ('annas' | 'zlib'), validated against ENGINES. */
    public static function engine(): string
    {
        $engine = (string) Setting::get(self::KEY_ENGINE, self::DEFAULT_ENGINE);
        return isset(self::ENGINES[$engine]) ? $engine : self::DEFAULT_ENGINE;
    }

    /**
     * Domain for the given engine (or the active engine if null), falling back
     * to the engine's compiled-in default. Never has a trailing slash.
     */
    public static function domain(?string $engine = null): string
    {
        $engine = $engine && isset(self::ENGINES[$engine]) ? $engine : self::engine();
        $meta   = self::ENGINES[$engine];
        $stored = Setting::get($meta['domain_key']);
        $domain = $stored !== null && $stored !== '' ? $stored : $meta['default_domain'];
        return rtrim($domain, '/');
    }

    /** Absolute path to the active (or given) engine's scraper library. */
    public static function libPath(?string $engine = null): string
    {
        $engine = $engine && isset(self::ENGINES[$engine]) ? $engine : self::engine();
        return public_path(self::ENGINES[$engine]['lib']);
    }

    /** Persist the active engine. */
    public static function setEngine(string $engine): void
    {
        if (isset(self::ENGINES[$engine])) {
            Setting::put(self::KEY_ENGINE, $engine);
        }
    }

    /** Persist one engine's domain. Normalizes scheme + trailing slash. */
    public static function setDomain(string $engine, string $domain): void
    {
        if (!isset(self::ENGINES[$engine])) {
            return;
        }
        Setting::put(self::ENGINES[$engine]['domain_key'], self::normalizeDomain($domain));
    }

    /** Tidy a user-entered domain: trim, add https:// if missing, drop trailing "/". */
    public static function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return $domain;
        }
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }
        return rtrim($domain, '/');
    }
}
