<?php

namespace App\Http\Controllers;

use App\Models\BookDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Public RSS 2.0 feed of book posts, shaped for Pinterest's "auto-publish from
 * RSS" feature (and any other reader). Pinterest crawls this feed and creates a
 * Pin per <item>, deduping on <guid>/<link>, so we don't need the write API.
 *
 * Each item carries the cover image three ways for maximum compatibility:
 *   - <media:content>  (Media RSS — what Pinterest prefers)
 *   - <media:thumbnail>
 *   - <enclosure>      (plain RSS fallback)
 *
 * The feed is intentionally public + unauthenticated: Pinterest's crawler has
 * no session. It only exposes data already visible on the public book pages
 * (title, description, cover, link).
 */
class BookFeedController extends Controller
{
    /** Max items to include. Pinterest reads newest-first and dedups. */
    private const LIMIT = 50;

    public function rss(Request $request)
    {
        $books = BookDetail::query()
            ->whereNotNull('cover_url')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $siteUrl   = rtrim(config('app.url'), '/');
        $feedUrl   = route('books.feed');
        $now       = Carbon::now()->toRfc822String();
        $appName   = 'Tanbat';

        $items = '';
        foreach ($books as $b) {
            $link    = $this->bookLink($b, $siteUrl);
            $image   = $this->absoluteUrl($b->cover_url, $siteUrl);
            $title   = $this->clean($b->title ?: 'Untitled');
            $desc    = $this->description($b);
            $pubDate = ($b->created_at ?: Carbon::now())->toRfc822String();
            $mime    = $this->imageMime($image);

            $imgEsc = $this->esc($image);
            $items .= "    <item>\n"
                . '      <title>' . $this->cdata($title) . "</title>\n"
                . '      <link>' . $this->esc($link) . "</link>\n"
                . '      <guid isPermaLink="true">' . $this->esc($link) . "</guid>\n"
                . '      <pubDate>' . $pubDate . "</pubDate>\n"
                . '      <description>' . $this->cdata($desc) . "</description>\n"
                . '      <media:content url="' . $imgEsc . '" medium="image" type="' . $mime . '" />' . "\n"
                . '      <media:thumbnail url="' . $imgEsc . '" />' . "\n"
                . '      <enclosure url="' . $imgEsc . '" type="' . $mime . '" length="0" />' . "\n"
                . "    </item>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
            . "  <channel>\n"
            . '    <title>' . $this->cdata($appName . ' — Latest Books') . "</title>\n"
            . '    <link>' . $this->esc($siteUrl . '/books') . "</link>\n"
            . '    <atom:link href="' . $this->esc($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n"
            . '    <description>' . $this->cdata('Newly added books on ' . $appName) . "</description>\n"
            . "    <language>en-us</language>\n"
            . '    <lastBuildDate>' . $now . "</lastBuildDate>\n"
            . $items
            . "  </channel>\n"
            . "</rss>\n";

        return response($xml, 200, [
            'Content-Type'  => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900', // 15 min; Pinterest polls periodically
        ]);
    }

    /** Public permalink to the book post. */
    private function bookLink(BookDetail $b, string $siteUrl): string
    {
        return $siteUrl . '/books/' . $b->slug;
    }

    /** Build the pin description: book blurb + light metadata + CTA. */
    private function description(BookDetail $b): string
    {
        $parts = [];
        if ($b->description) {
            $parts[] = trim($b->description);
        }
        $meta = array_filter([
            $b->author ? 'Author: ' . $b->author : null,
            $b->publisher ? 'Publisher: ' . $b->publisher : null,
            $b->year ? 'Year: ' . $b->year : null,
        ]);
        if ($meta) {
            $parts[] = implode(' · ', $meta);
        }
        $parts[] = '📚 Read more on Tanbat.';

        $text = trim(implode("\n\n", $parts));
        // Pinterest pin descriptions cap around 500–800 chars; keep it tidy.
        if (Str::length($text) > 700) {
            $text = Str::limit($text, 699, '…');
        }
        return $this->clean($text);
    }

    /** Make a possibly-relative URL absolute against the site root. */
    private function absoluteUrl(string $url, string $siteUrl): string
    {
        $url = trim($url);
        if ($url === '') return $url;
        if (Str::startsWith($url, ['http://', 'https://'])) return $url;
        if (Str::startsWith($url, '//')) return 'https:' . $url;
        return $siteUrl . '/' . ltrim($url, '/');
    }

    private function imageMime(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        return match ($ext) {
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            default      => 'image/jpeg',
        };
    }

    /** Strip control chars that would break XML. */
    private function clean(string $s): string
    {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '');
    }

    private function cdata(string $s): string
    {
        // Guard against an accidental "]]>" inside the payload.
        $s = str_replace(']]>', ']]]]><![CDATA[>', $s);
        return '<![CDATA[' . $s . ']]>';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
