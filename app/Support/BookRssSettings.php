<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-tunable settings for the book RSS importer.
 *
 * Same resolution order as App\Support\TelegramSettings: the stored setting
 * (written from /admin/book-rss) wins, otherwise the config/env default.
 */
class BookRssSettings
{
    public const KEY_ENABLED  = 'book_rss.enabled';
    public const KEY_FEED_URL = 'book_rss.feed_url';
    public const KEY_LAST_RUN = 'book_rss.last_run_at';   // unix timestamp
    public const KEY_LAST_LOG = 'book_rss.last_result';   // human-readable summary

    public static function enabled(): bool
    {
        $stored = Setting::get(self::KEY_ENABLED);
        if ($stored === null || $stored === '') {
            return (bool) config('book_rss.enabled');
        }
        return $stored === '1';
    }

    public static function feedUrl(): string
    {
        $stored = Setting::get(self::KEY_FEED_URL);
        return ($stored === null || $stored === '')
            ? (string) config('book_rss.feed_url')
            : $stored;
    }

    public static function setEnabled(bool $on): void
    {
        Setting::put(self::KEY_ENABLED, $on ? '1' : '0');
    }

    public static function setFeedUrl(string $url): void
    {
        Setting::put(self::KEY_FEED_URL, trim($url) ?: null);
    }

    /** Timestamp of the last import attempt, or null if it has never run. */
    public static function lastRunAt(): ?int
    {
        $value = Setting::get(self::KEY_LAST_RUN);
        return $value ? (int) $value : null;
    }

    public static function lastResult(): ?string
    {
        return Setting::get(self::KEY_LAST_LOG);
    }

    /** Record the outcome of an import run for the admin panel to display. */
    public static function recordRun(string $summary): void
    {
        Setting::put(self::KEY_LAST_RUN, (string) time());
        Setting::put(self::KEY_LAST_LOG, mb_substr($summary, 0, 500));
    }
}
