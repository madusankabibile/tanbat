<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Public RSS 2.0 feed of recently registered users.
 * This provides an auto-updating RSS feed for /users/feed.xml.
 */
class UserFeedController extends Controller
{
    /** Max items to include. */
    private const LIMIT = 50;

    public function rss(Request $request)
    {
        $users = User::query()
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $siteUrl   = rtrim(config('app.url'), '/');
        $feedUrl   = route('users.feed');
        $now       = Carbon::now()->toRfc822String();
        $appName   = 'Tanbat';

        $items = '';
        foreach ($users as $u) {
            $link    = $this->userLink($u, $siteUrl);
            $image   = $this->absoluteUrl($u->avatarUrl() ?: '', $siteUrl);
            $title   = $this->clean($u->name ?: $u->username);
            $desc    = $this->description($u);
            $pubDate = ($u->created_at ?: Carbon::now())->toRfc822String();

            // Build rich content for Google SEO
            $contentHtml = '';
            if ($image) {
                $contentHtml .= '<figure><img src="' . $this->esc($image) . '" alt="' . $this->esc($title) . '" style="max-width: 100%; height: auto; border-radius: 50%;" /></figure>';
            }
            if ($u->bio) {
                $contentHtml .= '<p>' . $this->esc($u->bio) . '</p>';
            }

            $items .= "    <item>\n"
                . '      <title>' . $this->cdata($title) . "</title>\n"
                . '      <link>' . $this->esc($link) . "</link>\n"
                . '      <guid isPermaLink="true">' . $this->esc($link) . "</guid>\n"
                . '      <pubDate>' . $pubDate . "</pubDate>\n"
                . '      <dc:creator>' . $this->cdata($appName) . "</dc:creator>\n"
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
            . '    <title>' . $this->cdata($appName . ' — New Users') . "</title>\n"
            . '    <link>' . $this->esc($siteUrl . '/discover/people') . "</link>\n"
            . '    <atom:link href="' . $this->esc($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n"
            . '    <description>' . $this->cdata('Newly registered users on ' . $appName) . "</description>\n"
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

    /** Public permalink to the user profile. */
    private function userLink(User $u, string $siteUrl): string
    {
        return $siteUrl . '/' . $u->username;
    }

    /** Build the description from bio. */
    private function description(User $u): string
    {
        $text = trim($u->bio ?: 'Check out ' . ($u->name ?: $u->username) . '\'s profile on Tanbat!');
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
