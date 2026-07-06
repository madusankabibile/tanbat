<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Public RSS 2.0 feed of article posts.
 * This provides an auto-updating RSS feed for /articles/feed.xml.
 */
class ArticleFeedController extends Controller
{
    /** Max items to include. */
    private const LIMIT = 50;

    public function rss(Request $request)
    {
        $articles = Post::with(['user', 'category'])
            ->where('type', 'article')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $siteUrl   = rtrim(config('app.url'), '/');
        $feedUrl   = route('articles.feed');
        $now       = Carbon::now()->toRfc822String();
        $appName   = 'Tanbat';

        $items = '';
        foreach ($articles as $a) {
            $link    = $this->articleLink($a, $siteUrl);
            $image   = $this->absoluteUrl($a->featured_image_url ?: '', $siteUrl);
            $title   = $this->clean($a->title ?: 'Untitled');
            $desc    = $this->description($a);
            $pubDate = ($a->created_at ?: Carbon::now())->toRfc822String();
            $author  = $a->user ? $a->user->name : 'Tanbat';
            $catName = $a->category ? $a->category->name : '';

            $contentHtml = '';
            if ($image) {
                $contentHtml .= '<figure><img src="' . $this->esc($image) . '" alt="' . $this->esc($title) . '" style="max-width: 100%; height: auto;" /></figure>';
            }
            if ($a->body) {
                $contentHtml .= $a->body;
            }

            $items .= "    <item>\n"
                . '      <title>' . $this->cdata($title) . "</title>\n"
                . '      <link>' . $this->esc($link) . "</link>\n"
                . '      <guid isPermaLink="true">' . $this->esc($link) . "</guid>\n"
                . '      <pubDate>' . $pubDate . "</pubDate>\n"
                . '      <dc:creator>' . $this->cdata($author) . "</dc:creator>\n"
                . ($catName ? '      <category>' . $this->cdata($catName) . "</category>\n" : '')
                . '      <description>' . $this->cdata($desc) . "</description>\n"
                . '      <content:encoded>' . $this->cdata($contentHtml) . "</content:encoded>\n";

            if ($image) {
                $mime    = $this->imageMime($image);
                $imgEsc  = $this->esc($image);
                $items .= '      <media:content url="' . $imgEsc . '" medium="image" type="' . $mime . '" />' . "\n"
                    . '      <media:thumbnail url="' . $imgEsc . '" />' . "\n"
                    . '      <enclosure url="' . $imgEsc . '" type="' . $mime . '" length="0" />' . "\n";
            }
            $items .= "    </item>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
            . "  <channel>\n"
            . '    <title>' . $this->cdata($appName . ' — Latest Articles') . "</title>\n"
            . '    <link>' . $this->esc($siteUrl . '/articles') . "</link>\n"
            . '    <atom:link href="' . $this->esc($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n"
            . '    <description>' . $this->cdata('Newly added articles on ' . $appName) . "</description>\n"
            . "    <language>en-us</language>\n"
            . '    <lastBuildDate>' . $now . "</lastBuildDate>\n"
            . $items
            . "  </channel>\n"
            . "</rss>\n";

        return response($xml, 200, [
            'Content-Type'  => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900', // 15 min cache
        ]);
    }

    /** Public permalink to the article post. */
    private function articleLink(Post $a, string $siteUrl): string
    {
        return $siteUrl . '/articles/' . $a->slug;
    }

    /** Build the description: short description or full description. */
    private function description(Post $a): string
    {
        $text = trim($a->short_description ?: $a->description ?: '');
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
