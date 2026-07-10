<?php

namespace App\Services;

/**
 * Resolves an ISO-3166 alpha-2 country code to a self-hosted flag SVG.
 *
 * The SVGs live in `public/flags/` and were copied from the `flag-icons`
 * package (devDependency) with:
 *
 *     cp node_modules/flag-icons/flags/4x3/??.svg public/flags/
 *
 * Only the 2-letter files are vendored — VisitorGeo and `users.country` never
 * produce subdivision codes like `gb-eng`. They are served from our own origin
 * rather than a CDN so the admin panel makes no third-party requests.
 *
 * Emoji flags were rejected: Windows ships no flag glyphs, so they degrade to
 * bare letters on the platform this admin is used from.
 */
class CountryFlag
{
    /** Codes that mean "we don't know", not a real country. */
    private const PLACEHOLDERS = ['XX', 'T1'];

    /** @var array<string,bool> memoised file_exists() results, keyed by lowercase code */
    private static array $available = [];

    /** A usable flag exists for this code. */
    public static function exists(?string $code): bool
    {
        $code = self::normalise($code);
        if ($code === null) {
            return false;
        }

        return self::$available[$code] ??= is_file(public_path("flags/{$code}.svg"));
    }

    /** Public URL of the flag SVG, or null when there is no flag to show. */
    public static function url(?string $code): ?string
    {
        if (!self::exists($code)) {
            return null;
        }

        return asset('flags/' . self::normalise($code) . '.svg');
    }

    /** Lowercase 2-letter code, or null for empty/placeholder/malformed input. */
    private static function normalise(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));

        if (!preg_match('/^[a-z]{2}$/', $code)) {
            return null;
        }
        if (in_array(strtoupper($code), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $code;
    }
}
