<?php

/*
|--------------------------------------------------------------------------
| Pinterest cross-post settings
|--------------------------------------------------------------------------
|
| Values live in .env — see the PINTEREST_* keys there. This file is in
| source control and intentionally contains no secrets.
|
| Create the app at:  https://developers.pinterest.com/apps/
| App type: use the standard OAuth (authorization-code) flow. The redirect
| URI registered there MUST exactly match `{APP_URL}/admin/pinterest/callback`
| (or whatever you set PINTEREST_REDIRECT_URI to). Pinterest requires HTTPS
| for any non-localhost redirect, e.g. https://tanbat.com/admin/pinterest/callback
|
*/

return [

    // Master switch — when false, the heartbeat skips Pinterest posting.
    'enabled' => (bool) env('PINTEREST_ENABLED', false),

    // App credentials from https://developers.pinterest.com/apps/
    'client_id'     => env('PINTEREST_CLIENT_ID', ''),
    'client_secret' => env('PINTEREST_CLIENT_SECRET', ''),

    // API host. Trial apps that haven't been approved for production can only
    // create Pins against the sandbox. Once your production access is granted
    // (or you're using a production-limited token), keep this on the live host.
    //   live:    https://api.pinterest.com
    //   sandbox: https://api-sandbox.pinterest.com
    'api_host' => rtrim(env('PINTEREST_API_HOST', 'https://api.pinterest.com'), '/'),

    // Optional explicit redirect URI override. Leave blank to derive it from
    // APP_URL via the route helper. Set this when APP_URL differs from your
    // public domain (e.g. behind a proxy), so the value matches what you
    // registered in the Pinterest app dashboard.
    'redirect_uri' => env('PINTEREST_REDIRECT_URI', ''),

    // OAuth scopes. Creating a pin writes to a board, so Pinterest requires
    // BOTH pins:write and boards:write (plus the read scopes to list boards
    // and identify the account). After changing this, the admin must
    // re-authorize so the new token carries the added scope.
    'scopes' => 'boards:read,boards:write,pins:read,pins:write,user_accounts:read',

    // Minimum spacing between consecutive pins, in minutes. Matches the
    // Reddit cadence so the two cross-posters stagger naturally.
    'post_spacing_minutes' => (int) env('PINTEREST_POST_SPACING_MINUTES', 20),

    // How many times to retry a failing book before giving up on it.
    'max_attempts' => 5,

    // Pinterest pin title cap is 100 chars; description cap is 800.
    'title_max'       => 100,
    'description_max' => 800,

    // Pin title template — {title} and {username} are filled from the book +
    // the requesting member.
    'title_template' => '{title}',

    // Pin description. {description} → scraped book description, {title} →
    // book title, {username} → requesting member. A short CTA is appended.
    'description_template' =>
        "{description}\n\n" .
        "📚 Get this book on Tanbat — tap to read more.",
];
