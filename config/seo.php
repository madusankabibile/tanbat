<?php

/*
|--------------------------------------------------------------------------
| SEO defaults
|--------------------------------------------------------------------------
|
| Site-wide fallbacks for the shared meta partial (resources/views/partials/
| _seo.blade.php). Any page can override each of these per-request; these are
| what fills in when it doesn't. Centralised here so the site name, default
| share text and social handle live in exactly one place.
|
*/

return [
    // Appears in og:site_name and as the JSON-LD publisher.
    'site_name' => 'Tanbat',

    // Fallback meta description / share text for pages that don't set their own.
    // Written for humans first; it is the snippet under the title in search
    // results and the caption in a social link preview.
    'description' => 'Tanbat is a social network to connect with people, share posts and photos, '
        . 'discover and download free books, and read articles across every topic.',

    // Absolute URL to a default 1200x630 Open Graph image used when a page has
    // no image of its own. Leave null to simply omit og:image rather than ship
    // a broken or off-brand preview.
    'image' => null,

    // Twitter/X handle including the leading "@", or null. Enables the
    // twitter:site attribution when present.
    'twitter' => null,

    // BCP-47-ish locale advertised in og:locale.
    'locale' => 'en_US',
];
