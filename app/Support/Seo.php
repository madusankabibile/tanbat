<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Small helpers shared by the JSON-LD blocks on the public pages. Keeps the
 * publisher identity and description-trimming defined once instead of copied
 * into every view's @php block.
 */
class Seo
{
    /** The schema.org Organization that publishes every page. */
    public static function publisher(): array
    {
        return [
            '@type' => 'Organization',
            'name'  => config('seo.site_name', 'Tanbat'),
            'url'   => rtrim((string) config('app.url'), '/') . '/',
            'logo'  => ['@type' => 'ImageObject', 'url' => asset('favicon.ico')],
        ];
    }

    /** Strip tags, collapse whitespace, and clamp to a snippet-friendly length. */
    public static function describe(?string $text, int $limit = 160): string
    {
        return (string) Str::of((string) $text)->stripTags()->squish()->limit($limit, '');
    }
}
