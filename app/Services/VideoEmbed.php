<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VideoEmbed
{
    /**
     * Parse a YouTube or Vimeo URL into [provider, id].
     * Returns null if the URL is not recognized.
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') return null;

        // youtu.be/<id>
        if (preg_match('~^https?://(?:www\.)?youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            return ['youtube', $m[1]];
        }
        // youtube.com/watch?v=<id>, /embed/<id>, /shorts/<id>
        if (preg_match('~^https?://(?:www\.|m\.)?youtube\.com/(?:watch\?[^#]*\bv=|embed/|shorts/|v/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            return ['youtube', $m[1]];
        }
        // vimeo.com/<id>  (digits)
        if (preg_match('~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{5,})~i', $url, $m)) {
            return ['vimeo', $m[1]];
        }
        return null;
    }

    /**
     * Best-effort poster image URL for an embed.
     * - YouTube: deterministic image CDN
     * - Vimeo:   uses public oEmbed (returns thumbnail_url)
     * Returns null on failure; caller should fall back to a placeholder.
     */
    public static function fetchPoster(string $provider, string $id): ?string
    {
        if ($provider === 'youtube') {
            return "https://img.youtube.com/vi/{$id}/hqdefault.jpg";
        }
        if ($provider === 'vimeo') {
            try {
                $resp = Http::timeout(4)->get('https://vimeo.com/api/oembed.json', [
                    'url' => "https://vimeo.com/{$id}",
                ]);
                if ($resp->ok()) {
                    return $resp->json('thumbnail_url') ?: null;
                }
            } catch (\Throwable $e) {
                // swallow — caller falls back
            }
        }
        return null;
    }
}
