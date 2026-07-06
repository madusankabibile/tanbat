<?php

/*
|--------------------------------------------------------------------------
| Reddit cross-post settings
|--------------------------------------------------------------------------
|
| Values live in .env — see the REDDIT_* keys there. This file is in source
| control and intentionally contains no secrets. The bot account that owns
| the script app must be approved to post in the target subreddit.
|
| Create the app at:  https://www.reddit.com/prefs/apps   (type: "script")
|
*/

return [

    // Master switch — when false, the heartbeat skips Reddit posting entirely.
    'enabled' => (bool) env('REDDIT_ENABLED', false),

    // App credentials from https://www.reddit.com/prefs/apps (script type).
    'client_id'     => env('REDDIT_CLIENT_ID', ''),
    'client_secret' => env('REDDIT_CLIENT_SECRET', ''),

    // Reddit account used for posting (must own the script app + be allowed
    // to post in the target subreddit).
    'username' => env('REDDIT_USERNAME', ''),
    'password' => env('REDDIT_PASSWORD', ''),

    // Reddit requires a unique, descriptive User-Agent. Include the bot
    // account's name so a moderator can reach you if something goes wrong.
    'user_agent' => env('REDDIT_USER_AGENT', 'Tanbat/1.0 by madusankabibile'),

    // Target subreddit (no leading r/).
    'subreddit' => env('REDDIT_SUBREDDIT', 'pdfbooks'),

    // Minimum spacing between consecutive cross-posts, in minutes. Reddit's
    // submission anti-spam catches faster cadences for new/low-karma bots;
    // 20 minutes is a safe default.
    'post_spacing_minutes' => (int) env('REDDIT_POST_SPACING_MINUTES', 20),

    // How many times to retry a failing book before marking it skipped.
    'max_attempts' => 5,

    // Image upload cap. Reddit's hard limit is 20 MB; we trim earlier so a
    // huge cover image never stalls the heartbeat.
    'max_image_bytes' => 10 * 1024 * 1024, // 10 MB

    // Title template — placeholders {title} and {username} are filled in
    // from the book + the requesting user.
    'title_template' => '{title} published on the Tanbat.com as requested by the {username}',

    // Body for the auto-comment. {description} → scraped book description.
    // {title} → book title (used so the search query is exact).
    'comment_template' =>
        "{description}\n\n" .
        "📚 To find your book, go to **tanbat.com/books** and search **\"{title}\"**.",
];
