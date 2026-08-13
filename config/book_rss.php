<?php

/*
|--------------------------------------------------------------------------
| Book RSS importer
|--------------------------------------------------------------------------
|
| Watches an external ebook site's RSS feed and turns each NEW item into an
| anonymous book post (posts.type = "book" owned by the shared "anonymous"
| account, exactly like guest-published books).
|
| What gets imported is METADATA ONLY — title, author, language, file format
| and size, the cover thumbnail, and the links. The scanned page images that
| the source embeds in <content:encoded> are deliberately NOT mirrored; the
| book page links back to the origin instead (see `source_url`).
|
| The feed URL and the enable toggle are editable at /admin/book-rss, so the
| source can be re-pointed without a deploy. See App\Support\BookRssSettings.
|
*/

return [

    // Master switch for the automatic (heartbeat-driven) import. Manual
    // "Import now" from the admin panel works regardless of this flag.
    'enabled' => (bool) env('BOOK_RSS_ENABLED', false),

    // Feed to poll.
    'feed_url' => env('BOOK_RSS_FEED_URL', 'https://sinhalaebooks.com/feed/'),

    // How often the heartbeat re-polls the feed, in minutes. The feed only
    // changes a few times a day, so there's no value in hammering it.
    'poll_interval_minutes' => (int) env('BOOK_RSS_POLL_MINUTES', 60),

    // Safety cap on how many new books one import run may create. The feed
    // carries ~10 items; this stops a feed that suddenly returns hundreds of
    // entries from flooding the site in a single tick.
    'max_per_run' => (int) env('BOOK_RSS_MAX_PER_RUN', 10),

    // Covers are copied to local storage so the book page and the Telegram
    // photo don't hotlink the source site (which may block hotlinking or
    // rotate its upload paths).
    'cover_disk'      => 'public',
    'cover_dir'       => 'books/covers',
    'max_image_bytes' => 8 * 1024 * 1024,

    // Category slug new imports are filed under. Falls back to the first
    // category in the table when this slug doesn't exist.
    'category_slug' => env('BOOK_RSS_CATEGORY', 'other'),

    'http_timeout'  => 30,
    'user_agent'    => 'Tanbat/1.0 (+https://tanbat.com) book-feed-importer',
];
