<?php

/*
|--------------------------------------------------------------------------
| Automated / system accounts
|--------------------------------------------------------------------------
|
| Usernames that belong to the platform rather than to real members:
| the scheduled content bots (see App\Console\Commands\Post*FromBot) and the
| shared account that owns guest ("anonymous") book requests.
|
| These are hidden from social listings such as the home page "Most active"
| rail so they don't crowd out genuine members.
|
*/

return [

    'usernames' => [
        'james_caldwell',   // meme bot   — bot:post-meme
        'robert_sheffield', // news bot   — bot:post-news
        'daniel_whitmore',  // ad bot     — bot:post-ad
        'lucas_carlisle',   // video bot  — bot:post-video
        'anonymous',        // shared guest / anonymous book-request account
    ],

];
