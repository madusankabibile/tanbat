<?php

namespace App\Services;

use App\Models\BookDetail;
use App\Support\TelegramSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Announces new books in a Telegram channel as PHOTO messages.
 *
 * Flow:
 *   1. Resolve the book's cover to raw bytes (local storage when we own the
 *      file, otherwise an HTTP fetch).
 *   2. sendPhoto (multipart) with an HTML caption built from the template.
 *   3. Attach an inline keyboard whose single button deep-links to the book's
 *      page on the site — that's the "download" call to action.
 *
 * The cover is uploaded as binary rather than handed to Telegram as a URL on
 * purpose: Telegram would have to fetch the URL itself, which fails outright
 * in development (localhost) and any time the site sits behind auth or a slow
 * origin. Uploading the bytes works in every environment.
 *
 * Credentials come from App\Support\TelegramSettings (admin panel first, then
 * config/telegram.php ← .env).
 */
class TelegramPoster
{
    private array $cfg;

    public function __construct(array $config = null)
    {
        $this->cfg = $config ?? TelegramSettings::toArray();
    }

    /** Is the integration configured well enough to attempt a post? */
    public function isReady(): bool
    {
        return $this->isConfigured() && !empty($this->cfg['enabled']);
    }

    /** Credentials present, regardless of the on/off toggle. */
    public function isConfigured(): bool
    {
        return !empty($this->cfg['bot_token']) && !empty($this->cfg['chat_id']);
    }

    /**
     * Verify the token — returns Telegram's bot record (id, username, …).
     * Read-only; safe to call from the admin panel on every page load.
     */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * Verify channel access — returns the chat record (title, username, …).
     * Read-only. Fails if the bot was never added to the channel.
     */
    public function getChat(): array
    {
        return $this->call('getChat', ['chat_id' => $this->cfg['chat_id']]);
    }

    /**
     * Post a book to the channel. Returns the Telegram message id as a string.
     * Throws RuntimeException on any failure — callers bump retry counters.
     */
    public function postBook(BookDetail $book): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Telegram is not configured (bot token / channel missing).');
        }
        if (empty($book->cover_url)) {
            throw new RuntimeException('Book has no cover image to post.');
        }

        [$bytes, $filename, $mime] = $this->resolveCover($book->cover_url);

        $link    = $this->bookUrl($book);
        $caption = $this->buildCaption($book, $link);

        $response = Http::asMultipart()
            ->withOptions(['verify' => $this->verifySsl()])
            ->attach('photo', $bytes, $filename, ['Content-Type' => $mime])
            ->timeout(60)
            ->post($this->endpoint('sendPhoto'), [
                ['name' => 'chat_id',      'contents' => (string) $this->cfg['chat_id']],
                ['name' => 'caption',      'contents' => $caption],
                ['name' => 'parse_mode',   'contents' => 'HTML'],
                ['name' => 'reply_markup', 'contents' => $this->keyboard($link)],
            ]);

        $json = $this->unwrap($response, 'sendPhoto');

        $messageId = $json['message_id'] ?? null;
        if (!$messageId) {
            throw new RuntimeException('sendPhoto succeeded but returned no message_id.');
        }
        return (string) $messageId;
    }

    /**
     * Post a plain text message. Used by the admin panel's "Send test message"
     * button to prove the bot really can write to the channel — getChat only
     * proves it can read.
     */
    public function sendMessage(string $text): string
    {
        $json = $this->call('sendMessage', [
            'chat_id'                  => $this->cfg['chat_id'],
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        return (string) ($json['message_id'] ?? '');
    }

    /** Public URL of the book's page on the site — the button's destination. */
    public function bookUrl(BookDetail $book): string
    {
        $base = rtrim((string) ($this->cfg['site_url'] ?: config('app.url')), '/');
        return $base . '/books/' . $book->slug;
    }

    /**
     * Render the caption template. Every substituted value is HTML-escaped —
     * book titles legitimately contain "&" and Telegram rejects the whole
     * message if the HTML doesn't parse.
     */
    private function buildCaption(BookDetail $book, string $link): string
    {
        $size = '';
        if ($book->size) {
            $size = ' · ' . $book->size;
            if ($book->extension) {
                $size = ' · ' . strtoupper($book->extension) . ' ' . $book->size;
            }
        } elseif ($book->extension) {
            $size = ' · ' . strtoupper($book->extension);
        }

        $caption = strtr((string) $this->cfg['caption_template'], [
            '{title}'     => $this->escape($book->title ?: 'Untitled'),
            '{author}'    => $this->escape($book->author ?: 'Unknown author'),
            '{language}'  => $this->escape($book->language ?: 'Sinhala'),
            '{size}'      => $this->escape($size),
            '{extension}' => $this->escape(strtoupper((string) $book->extension)),
            // The link goes inside href="…" in the template, so it needs
            // escaping too, but must stay a usable URL.
            '{link}'      => $this->escape($link),
        ]);

        $max = (int) ($this->cfg['caption_max'] ?? 1024);
        if (mb_strlen($caption) > $max) {
            $caption = mb_substr($caption, 0, $max - 1) . '…';
        }

        return $caption;
    }

    /** Single-button inline keyboard pointing at the book page. */
    private function keyboard(string $link): string
    {
        return json_encode([
            'inline_keyboard' => [[
                [
                    'text' => (string) ($this->cfg['button_text'] ?: 'Download'),
                    'url'  => $link,
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Get the cover as [bytes, filename, mime].
     *
     * Covers we imported live on our own public disk, so the URL is mapped
     * back to a local read first — that avoids a pointless round trip and
     * works even when the site isn't reachable from the internet yet.
     */
    private function resolveCover(string $coverUrl): array
    {
        $max = (int) ($this->cfg['max_image_bytes'] ?? 10 * 1024 * 1024);

        $bytes = $this->readLocalCover($coverUrl);
        if ($bytes === null) {
            $res = Http::withOptions(['verify' => false])->timeout(45)->get($coverUrl);
            if (!$res->successful()) {
                throw new RuntimeException('Cover download failed: HTTP ' . $res->status());
            }
            $bytes = $res->body();
        }

        if ($bytes === '' ) {
            throw new RuntimeException('Cover image is empty.');
        }
        if (strlen($bytes) > $max) {
            throw new RuntimeException('Cover image exceeds the ' . round($max / 1048576) . ' MB cap.');
        }

        $ext  = strtolower(pathinfo((string) parse_url($coverUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $mime = match ($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return [$bytes, 'cover.' . $ext, $mime];
    }

    /**
     * If the cover URL points into our own /storage/ path and the file is
     * there, return its bytes. Null means "not ours — fetch it over HTTP".
     */
    private function readLocalCover(string $coverUrl): ?string
    {
        $path = (string) parse_url($coverUrl, PHP_URL_PATH);
        if (!str_contains($path, '/storage/')) {
            return null;
        }

        $relative = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');
        if ($relative === '') {
            return null;
        }

        $disk = Storage::disk('public');
        return $disk->exists($relative) ? $disk->get($relative) : null;
    }

    private function endpoint(string $method): string
    {
        $base = rtrim((string) ($this->cfg['api_base'] ?: 'https://api.telegram.org'), '/');
        return $base . '/bot' . $this->cfg['bot_token'] . '/' . $method;
    }

    /** JSON-body API call returning the unwrapped `result`. */
    private function call(string $method, array $params = []): array
    {
        if (!$this->isConfigured() && $method !== 'getMe') {
            throw new RuntimeException('Telegram is not configured (bot token / channel missing).');
        }
        if (empty($this->cfg['bot_token'])) {
            throw new RuntimeException('Telegram bot token is not set.');
        }

        $response = Http::timeout(30)
            ->withOptions(['verify' => $this->verifySsl()])
            ->asJson()
            ->post($this->endpoint($method), $params);

        return $this->unwrap($response, $method);
    }

    /**
     * Whether to verify Telegram's TLS certificate. True everywhere except a
     * local box that has no CA bundle — the bot token is in the request URL,
     * so this must not be switched off in production.
     */
    private function verifySsl(): bool
    {
        return (bool) ($this->cfg['verify_ssl'] ?? true);
    }

    /**
     * Telegram answers 200 with {"ok":false,...} for logical failures and
     * 4xx for others, so both paths have to be checked. `description` is the
     * human-readable reason ("chat not found", "not enough rights…") and is
     * exactly what an admin needs to see, so it's carried into the exception.
     */
    private function unwrap($response, string $method): array
    {
        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException($method . ' returned a non-JSON response (HTTP ' . $response->status() . ').');
        }

        if (empty($json['ok'])) {
            $reason = $json['description'] ?? ('HTTP ' . $response->status());
            throw new RuntimeException($method . ' failed: ' . $reason);
        }

        $result = $json['result'] ?? [];
        return is_array($result) ? $result : ['value' => $result];
    }
}
