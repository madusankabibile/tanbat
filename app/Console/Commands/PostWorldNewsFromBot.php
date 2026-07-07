<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostWorldNewsFromBot extends Command
{
    protected $signature   = 'bot:post-news {--username=robert_sheffield : The bot user that owns the post}';
    protected $description = 'Pull a hot world-news headline from public RSS feeds and publish it as a status post on behalf of the bot user.';

    /**
     * Public RSS feeds that don't require an API key and aren't typically
     * blocked from datacenter IPs (unlike Reddit's JSON endpoint, which
     * 403/429s most cloud providers). Tried in shuffled order until we land
     * on a fresh, unposted headline.
     */
    private array $feeds = [
        ['name' => 'BBC World',     'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml'],
        ['name' => 'Al Jazeera',    'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],
        ['name' => 'NPR World',     'url' => 'https://feeds.npr.org/1004/rss.xml'],
        ['name' => 'Sky News World','url' => 'https://feeds.skynews.com/feeds/rss/world.xml'],
    ];

    /** Backgrounds picked at random so the feed has visual variety. */
    private array $palettes = [
        ['#0f172a', '#f8fafc'],
        ['#1e293b', '#fde68a'],
        ['#7f1d1d', '#fee2e2'],
        ['#1e3a8a', '#bfdbfe'],
        ['#064e3b', '#a7f3d0'],
        ['#3f3f46', '#e4e4e7'],
        ['#581c87', '#f5d0fe'],
    ];

    public function handle(): int
    {
        $bot = User::where('username', $this->option('username'))->first();
        if (!$bot) {
            $this->error("Bot user '{$this->option('username')}' not found. Run `php artisan db:seed --class=NewsBotSeeder` first.");
            return self::FAILURE;
        }

        $feeds = $this->feeds;
        shuffle($feeds);

        foreach ($feeds as $feed) {
            $candidates = $this->fetchHeadlines($feed['url']);
            if (empty($candidates)) continue;

            foreach ($candidates as $c) {
                // Image is mandatory — skip headlines without one so the feed
                // never gets a bare text card from this bot.
                if (empty($c['image_url'])) continue;

                if (Post::where('type', 'status')
                    ->where('status_text', $c['title'])
                    ->exists()
                ) {
                    continue;
                }

                $stored = $this->downloadImage($c['image_url']);
                if (!$stored) {
                    // Couldn't fetch the actual bytes — try the next candidate
                    // rather than publishing a text-only post.
                    continue;
                }

                [$bg, $fc] = $this->palettes[array_rand($this->palettes)];

                $post = Post::create([
                    'user_id'     => $bot->id,
                    'type'        => 'status',
                    'status_text' => $c['title'],       // topic / headline
                    'description' => $c['summary'],      // scraped summary (may be null)
                    'bg_color'    => $bg,
                    'font_color'  => $fc,
                    'language'    => 'en',
                    'is_adult'    => false,
                ]);

                $post->media()->create([
                    'kind'     => 'image',
                    'path'     => $stored,
                    'position' => 0,
                ]);

                $this->info("Posted news #{$post->id}: {$c['title']} ({$feed['name']})");
                return self::SUCCESS;
            }
        }

        $this->warn('No fresh, unposted world-news headlines with an image found in any source feed.');
        return self::SUCCESS;
    }

    /**
     * Fetch and parse an RSS/Atom feed into [['title'=>..., 'image_url'=>...], ...].
     * Logs on failure so silent feed outages on prod are debuggable.
     */
    private function fetchHeadlines(string $url): array
    {
        try {
            $resp = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; tanbat-newsbot/1.0; +https://tanbat.example/bot)'])
                ->timeout(15)
                ->get($url);

            if (!$resp->ok()) {
                Log::warning('newsbot: feed fetch failed', ['url' => $url, 'status' => $resp->status()]);
                return [];
            }

            $body = $resp->body();
            // Suppress XML parser warnings — feeds occasionally include stray
            // entities. We catch a parse failure via the SimpleXML return value.
            $prev = libxml_use_internal_errors(true);
            $xml  = simplexml_load_string($body);
            libxml_use_internal_errors($prev);
            if ($xml === false) {
                Log::warning('newsbot: feed parse failed', ['url' => $url]);
                return [];
            }

            // RSS 2.0 → channel/item. Atom → entry. SimpleXML returns empty
            // elements (not null) for missing children, so isset() not ??.
            $items = null;
            if (isset($xml->channel->item)) $items = $xml->channel->item;
            elseif (isset($xml->item))      $items = $xml->item;
            elseif (isset($xml->entry))     $items = $xml->entry;
            if (!$items) return [];

            $out = [];
            foreach ($items as $item) {
                $title = trim((string) ($item->title ?? ''));
                if ($title === '') continue;

                // Strip the trailing source tag many news feeds append, e.g.
                // "Headline here - BBC News" or "Headline [Reuters]".
                $title = preg_replace('/\s+[-–|]\s+[^-–|]{1,40}$/u', '', $title);
                $title = preg_replace('/\s*[\[\(][^\]\)]+[\]\)]\s*$/u', '', $title);
                if (mb_strlen($title) > 1900) {
                    $title = mb_substr($title, 0, 1897) . '...';
                }

                $out[] = [
                    'title'     => $title,
                    'summary'   => $this->extractSummary($item),
                    'image_url' => $this->extractImageUrl($item),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('newsbot: feed exception', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Pull a plain-text summary from an RSS/Atom item. The <description>
     * (or Atom <summary>) usually holds a one-paragraph teaser, often wrapped
     * in HTML with an <img> — we strip tags/entities, collapse whitespace,
     * drop trailing "Continue reading" boilerplate, and cap the length.
     * Returns null when the feed carries no usable summary.
     */
    private function extractSummary(\SimpleXMLElement $item): ?string
    {
        $raw = trim((string) ($item->description ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($item->summary ?? '')); // Atom
        }
        if ($raw === '') return null;

        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        // Feeds frequently append their own "Continue reading" / "Read more" tail.
        $text = trim(preg_replace('/\s*(continue reading|read more|read full article).*$/iu', '', $text));

        if ($text === '') return null;
        if (mb_strlen($text) > 500) {
            $text = mb_substr($text, 0, 497) . '...';
        }
        return $text;
    }

    /**
     * RSS feeds expose preview images in several inconsistent shapes
     * (enclosure, media:thumbnail, media:content, or an <img> inside description).
     * Walk them in order of fidelity.
     */
    private function extractImageUrl(\SimpleXMLElement $item): ?string
    {
        // <enclosure url="..." type="image/jpeg">
        $enclosureUrl = (string) ($item->enclosure['url'] ?? '');
        if ($enclosureUrl !== '' && $this->looksLikeImage($enclosureUrl)) {
            return $enclosureUrl;
        }

        // <media:thumbnail> / <media:content> — read via children() to access ns
        foreach ($item->children('media', true) as $node) {
            $name = $node->getName();
            if (!in_array($name, ['thumbnail', 'content'], true)) continue;
            $url = (string) ($node['url'] ?? '');
            if ($url !== '' && $this->looksLikeImage($url)) return $url;
        }

        // First <img src=""> inside <description>
        $description = (string) ($item->description ?? '');
        if ($description !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $m)) {
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if ($this->looksLikeImage($url)) return $url;
        }

        return null;
    }

    private function looksLikeImage(string $url): bool
    {
        if (!preg_match('~^https?://~i', $url)) return false;
        // Either an explicit image extension or a feed-provider thumbnail host.
        if (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $url)) return true;
        // BBC / Sky / AJ thumbnail CDNs don't always have a clean extension.
        return (bool) preg_match('~(ichef\.bbci\.co\.uk|aljazeera\.com|skynews|npr\.org)~i', $url);
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $resp = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; tanbat-newsbot/1.0)'])
                ->timeout(30)
                ->get($url);
            if (!$resp->ok()) return null;

            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $ext = 'jpg';
            }
            $path = 'posts/status/' . Str::random(40) . '.' . $ext;
            Storage::disk('public')->put($path, $resp->body());
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
