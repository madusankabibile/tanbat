<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-tunable Telegram cross-poster settings.
 *
 * Resolution order for every value: the settings row (written from
 * /admin/telegram) first, then the config/env default. That lets an admin
 * paste a new bot token or re-point the channel without a deploy, while a
 * fresh install still boots from .env alone.
 *
 * Mirrors App\Support\BookSearch, which does the same for the book engines.
 */
class TelegramSettings
{
    public const KEY_ENABLED   = 'telegram.enabled';
    public const KEY_TOKEN     = 'telegram.bot_token';
    public const KEY_CHAT      = 'telegram.chat_id';
    public const KEY_CAPTION   = 'telegram.caption_template';
    public const KEY_BUTTON    = 'telegram.button_text';
    public const KEY_SPACING   = 'telegram.post_spacing_minutes';

    /** Is the cross-poster switched on? */
    public static function enabled(): bool
    {
        $stored = Setting::get(self::KEY_ENABLED);
        if ($stored === null || $stored === '') {
            return (bool) config('telegram.enabled');
        }
        return $stored === '1';
    }

    public static function botToken(): string
    {
        return trim(self::value(self::KEY_TOKEN, (string) config('telegram.bot_token')));
    }

    /** "@channelusername" or a numeric -100… chat id. */
    public static function chatId(): string
    {
        return trim(self::value(self::KEY_CHAT, (string) config('telegram.chat_id')));
    }

    public static function captionTemplate(): string
    {
        return self::value(self::KEY_CAPTION, (string) config('telegram.caption_template'));
    }

    public static function buttonText(): string
    {
        return self::value(self::KEY_BUTTON, (string) config('telegram.button_text'));
    }

    public static function spacingMinutes(): int
    {
        $stored = Setting::get(self::KEY_SPACING);
        $value  = ($stored === null || $stored === '')
            ? (int) config('telegram.post_spacing_minutes')
            : (int) $stored;

        return max(1, $value);
    }

    /** Everything the poster needs, in the shape TelegramPoster expects. */
    public static function toArray(): array
    {
        return [
            'enabled'              => self::enabled(),
            'bot_token'            => self::botToken(),
            'chat_id'              => self::chatId(),
            'caption_template'     => self::captionTemplate(),
            'button_text'          => self::buttonText(),
            'post_spacing_minutes' => self::spacingMinutes(),
            'api_base'             => rtrim((string) config('telegram.api_base'), '/'),
            'verify_ssl'           => (bool) config('telegram.verify_ssl', true),
            'site_url'             => rtrim((string) config('telegram.site_url'), '/'),
            'caption_max'          => (int) config('telegram.caption_max', 1024),
            'max_attempts'         => (int) config('telegram.max_attempts', 5),
            'max_image_bytes'      => (int) config('telegram.max_image_bytes'),
        ];
    }

    public static function setEnabled(bool $on): void
    {
        Setting::put(self::KEY_ENABLED, $on ? '1' : '0');
    }

    /** Persist the editable fields. Null/blank values clear the override. */
    public static function save(array $values): void
    {
        $map = [
            'bot_token'            => self::KEY_TOKEN,
            'chat_id'              => self::KEY_CHAT,
            'caption_template'     => self::KEY_CAPTION,
            'button_text'          => self::KEY_BUTTON,
            'post_spacing_minutes' => self::KEY_SPACING,
        ];

        foreach ($map as $field => $key) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = $values[$field];
            Setting::put($key, ($value === null || $value === '') ? null : (string) $value);
        }
    }

    /** Read a stored override, falling back to the compiled-in default. */
    private static function value(string $key, string $default): string
    {
        $stored = Setting::get($key);
        return ($stored === null || $stored === '') ? $default : $stored;
    }

    /** Bot token with everything but the leading id masked, for display. */
    public static function maskedToken(): string
    {
        $token = self::botToken();
        if ($token === '') {
            return '';
        }
        $id = strtok($token, ':');
        return $id . ':' . str_repeat('•', 8) . substr($token, -4);
    }

    /** Public https://t.me/… URL for the configured channel, when derivable. */
    public static function channelUrl(): ?string
    {
        $chat = self::chatId();
        return str_starts_with($chat, '@')
            ? 'https://t.me/' . ltrim($chat, '@')
            : null;
    }
}
