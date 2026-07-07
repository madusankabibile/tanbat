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

                // Scrape the article page for a fuller summary than the RSS
                // teaser. Only done for the chosen candidate → one HTTP call
                // per run, not one per headline.
                $summary = $this->bestSummary($c);

                $post = Post::create([
                    'user_id'     => $bot->id,
                    'type'        => 'status',
                    'status_text' => $c['title'],       // topic / headline
                    'description' => $summary,           // scraped content (may be null)
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
                    'link'      => $this->extractLink($item),
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

        $text = $this->cleanText($raw);
        // Feeds frequently append their own "Continue reading" / "Read more" tail.
        $text = trim((string) preg_replace('/\s*(continue reading|read more|read full article).*$/iu', '', $text));

        return $text !== '' ? $this->capSummary($text) : null;
    }

    /**
     * The best available summary for a chosen headline: a fuller blurb scraped
     * from the article page when possible, otherwise the RSS teaser. The page
     * scrape is only worth the extra HTTP round-trip once we've committed to a
     * candidate, so this is called from handle(), not fetchHeadlines().
     */
    private function bestSummary(array $c): ?string
    {
        $rss = $c['summary'] ?? null;

        if (!empty($c['link'])) {
            $page = $this->fetchArticleSummary($c['link']);
            // Prefer the page summary only when it's genuinely richer.
            if ($page !== null && mb_strlen($page) > mb_strlen((string) $rss)) {
                return $page;
            }
        }
        return $rss;
    }

    /**
     * Fetch the actual article page and extract a readable summary:
     *   1. og:description / twitter:description / meta description — the most
     *      reliable one-to-three sentence blurb across news sites.
     *   2. Fall back to the first few substantial <p> paragraphs of the body.
     * Returns null on any fetch/parse failure so the caller can fall back to
     * the RSS teaser.
     */
    private function fetchArticleSummary(string $url): ?string
    {
        try {
            $resp = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; tanbat-newsbot/1.0)'])
                ->timeout(15)
                ->get($url);
            if (!$resp->ok()) {
                Log::warning('newsbot: article fetch failed', ['url' => $url, 'status' => $resp->status()]);
                return null;
            }
            $html = $resp->body();
        } catch (\Throwable $e) {
            Log::warning('newsbot: article fetch exception', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
        if ($html === '') return null;

        foreach ([['og:description', 'property'], ['twitter:description', 'name'], ['description', 'name']] as [$key, $attr]) {
            $val = $this->metaContent($html, $key, $attr);
            if ($val !== null) {
                $t = $this->cleanText($val);
                if (mb_strlen($t) >= 60) return $this->capSummary($t);
            }
        }

        // Body paragraphs — grab the first few that look like real prose.
        if (preg_match_all('~<p[^>]*>(.*?)</p>~is', $html, $mm)) {
            $paras = [];
            foreach ($mm[1] as $p) {
                $t = $this->cleanText($p);
                if (mb_strlen($t) >= 40) $paras[] = $t;
                if (count($paras) >= 3) break;
            }
            if (!empty($paras)) return $this->capSummary(implode(' ', $paras));
        }

        return null;
    }

    /**
     * Pull the content="" of a <meta> tag identified by $attr="$key", tolerating
     * either attribute order (content-before-key or key-before-content).
     */
    private function metaContent(string $html, string $key, string $attr = 'property'): ?string
    {
        $k = preg_quote($key, '~');
        if (preg_match('~<meta[^>]*\b' . $attr . '=["\']' . $k . '["\'][^>]*content=["\'](.*?)["\'][^>]*>~is', $html, $m)) {
            return $m[1];
        }
        if (preg_match('~<meta[^>]*content=["\'](.*?)["\'][^>]*\b' . $attr . '=["\']' . $k . '["\'][^>]*>~is', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Strip tags/entities and collapse whitespace to a single clean line. */
    private function cleanText(string $raw): string
    {
        $t = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    /** Cap a summary at 500 chars on a clean ellipsis. */
    private function capSummary(string $text): string
    {
        return mb_strlen($text) > 500 ? mb_substr($text, 0, 497) . '...' : $text;
    }

    /**
     * Resolve the canonical article URL for an item, handling both RSS 2.0
     * (<link>URL</link>) and Atom (<link rel="alternate" href="URL"/>).
     */
    private function extractLink(\SimpleXMLElement $item): ?string
    {
        $link = trim((string) ($item->link ?? ''));
        if ($link !== '' && preg_match('~^https?://~i', $link)) {
            return $link;
        }
        foreach ($item->link as $l) {
            $href = trim((string) ($l['href'] ?? ''));
            $rel  = (string) ($l['rel'] ?? 'alternate');
            if ($href !== '' && in_array($rel, ['', 'alternate'], true)) {
                return $href;
            }
        }
        return null;
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
