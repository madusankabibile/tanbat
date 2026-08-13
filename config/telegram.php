<?php

/*
|--------------------------------------------------------------------------
| Telegram channel cross-post settings
|--------------------------------------------------------------------------
|
| Announces each new book post as a PHOTO message (cover image + caption)
| in a Telegram channel, with an inline "Download" button pointing back at
| the book's page on the site.
|
| Secrets live in .env (TELEGRAM_*). The bot token and channel can ALSO be
| set from the admin panel at /admin/telegram — those stored values win over
| the env defaults, so the channel can be re-pointed without a deploy. See
| App\Support\TelegramSettings for the resolution order.
|
| Setup:
|   1. Create the bot with @BotFather → copy the token.
|   2. Add the bot to the channel as an ADMIN with "Post Messages" permission.
|   3. Paste the token + channel at /admin/telegram and hit "Save & verify".
|
*/

return [

    // Master switch — when false, the heartbeat skips Telegram entirely.
    // The admin toggle at /admin/telegram overrides this.
    'enabled' => (bool) env('TELEGRAM_ENABLED', false),

    // Bot token from @BotFather, e.g. "123456:AA...". Overridable in admin.
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    // Target channel. Either "@channelusername" or a numeric -100... chat id.
    'chat_id' => env('TELEGRAM_CHAT_ID', '@SinhalaFreeBooks'),

    'api_base' => 'https://api.telegram.org',

    // Verify Telegram's TLS certificate. Keep this TRUE in production — the
    // bot token travels in the request URL, so an unverified connection is a
    // real exposure. It exists only because a stock local XAMPP ships without
    // a CA bundle and every HTTPS call fails with cURL error 60; set
    // TELEGRAM_VERIFY_SSL=false in the LOCAL .env only.
    'verify_ssl' => filter_var(env('TELEGRAM_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

    // Public site URL used to build the book links that go INTO the message.
    // APP_URL points at the local XAMPP subdirectory in development, which
    // would produce unreachable links in a public channel — so the outgoing
    // URL base is configured separately and defaults to production.
    'site_url' => rtrim(env('TELEGRAM_SITE_URL', 'https://tanbat.com'), '/'),

    // Minimum spacing between consecutive channel posts, in minutes. Telegram
    // tolerates far more than Reddit, but spacing keeps the channel readable
    // instead of dumping ten books at once after an import.
    'post_spacing_minutes' => (int) env('TELEGRAM_POST_SPACING_MINUTES', 5),

    // How many times to retry a failing book before marking it skipped.
    'max_attempts' => 5,

    // Telegram caps photo captions at 1024 characters.
    'caption_max' => 1024,

    // Cover upload cap. Telegram's sendPhoto limit is 10 MB.
    'max_image_bytes' => 10 * 1024 * 1024,

    // Label on the inline button under the photo.
    'button_text' => env('TELEGRAM_BUTTON_TEXT', '📥 Download / බාගන්න'),

    // Caption template (Telegram HTML parse mode — only <b>, <i>, <a>, <code>
    // and a few others are allowed; everything substituted in is escaped).
    //
    // Placeholders: {title} {author} {language} {size} {extension} {link}
    // Blank lines around the fields survive into the message as-is.
    'caption_template' =>
        "📖 <b>{title}</b>\n" .
        "✍️ {author}\n" .
        "🌐 {language}{size}\n\n" .
        "👇 <a href=\"{link}\">Download this book on Tanbat</a>\n\n" .
        "#SinhalaBooks #නවකතා",
];
