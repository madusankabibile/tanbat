<?php

namespace App\Services;

use App\Models\BookDetail;
use App\Models\Post;
use App\Models\User;
use App\Support\BookRssSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

/**
 * Imports new books from an external ebook site's RSS feed and publishes each
 * one as an anonymous book post.
 *
 * Scope of what we take from a feed item — deliberately METADATA ONLY:
 *   • title / author / language / file format / file size  (from the summary)
 *   • the first image in the item, used as the cover thumbnail
 *   • the off-site download link when the item carries one (Yandex Disk etc.)
 *   • the origin permalink, stored as source_url for attribution
 *
 * The source embeds the book's scanned pages as images inside
 * <content:encoded>. Those are NOT mirrored — the imported post carries the
 * metadata and links back to the origin, it is not a copy of the book.
 *
 * Dedup: the feed's <guid> is stable per item, so md5(guid) becomes the
 * book_details.md5 key. Re-running an import is therefore idempotent — the
 * unique index on md5 makes a repeat a no-op rather than a duplicate post.
 */
class BookRssImporter
{
    /** Author names the source uses for its own staff — never a real author. */
    private const NON_AUTHOR_CATEGORIES = ['uncategorized', 'sinhala novels', 'novels', 'books'];

    private array $cfg;

    public function __construct(array $config = null)
    {
        $this->cfg = $config ?? (array) config('book_rss');
    }

    /**
     * Poll the feed and create a post for every item we haven't seen.
     *
     * Returns a summary: ['created' => int, 'skipped' => int, 'failed' => int,
     * 'titles' => string[]]. Never throws for a single bad item — one
     * malformed entry must not abort the whole run.
     *
     * @throws RuntimeException when the feed itself can't be fetched or parsed.
     */
    public function import(?string $feedUrl = null): array
    {
        $feedUrl = $feedUrl ?: BookRssSettings::feedUrl();
        $items   = $this->fetchItems($feedUrl);

        $maxPerRun = max(1, (int) ($this->cfg['max_per_run'] ?? 10));
        $result    = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'titles' => []];

        // Oldest first, so the channel announces books in publication order
        // rather than newest-first when a batch lands together.
        foreach (array_reverse($items) as $item) {
            if ($result['created'] >= $maxPerRun) {
                break;
            }

            try {
                $parsed = $this->parseItem($item, $feedUrl);
                if ($parsed === null) {
                    $result['skipped']++;
                    continue;
                }

                if (BookDetail::where('md5', $parsed['md5'])->exists()) {
                    $result['skipped']++;
                    continue;
                }

                $book = $this->createBook($parsed);
                $result['created']++;
                $result['titles'][] = $book->title;
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::warning('Book RSS import failed for one item', [
                    'feed'  => $feedUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /** Fetch + parse the feed. Returns the raw <item> elements. */
    private function fetchItems(string $feedUrl): array
    {
        if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Feed URL is not a valid URL: {$feedUrl}");
        }

        $res = Http::withHeaders(['User-Agent' => $this->cfg['user_agent'] ?? 'Tanbat/1.0'])
            ->withOptions(['verify' => false])
            ->timeout((int) ($this->cfg['http_timeout'] ?? 30))
            ->retry(2, 500)
            ->get($feedUrl);

        if (!$res->successful()) {
            throw new RuntimeException("Feed request failed: HTTP {$res->status()}");
        }

        // libxml throws warnings rather than exceptions on malformed markup;
        // switch to internal errors so a broken feed surfaces as our own
        // RuntimeException instead of a PHP warning in the heartbeat.
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($res->body());
        } finally {
            libxml_use_internal_errors($previous);
        }

        if ($xml === false || !isset($xml->channel->item)) {
            throw new RuntimeException('Feed did not parse as RSS (no channel/item found).');
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Turn one <item> into the field set we store, or null when it carries
     * too little to be a book (no title, or no stable guid to dedup on).
     */
    private function parseItem(SimpleXMLElement $item, string $feedUrl): ?array
    {
        $link = trim((string) $item->link);
        $guid = trim((string) $item->guid) ?: $link;
        if ($guid === '') {
            return null;
        }

        $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
        $summary = $this->plainText((string) $item->description);

        // The summary is the authoritative metadata block — the source renders
        // it as "Book Title : X | <sinhala> Author : Y Language : Z" and, for
        // items with a real file attached, "File Format : pdf File Size : 5 MB".
        $title = $this->field($summary, 'Book Title', ['Author', 'Language', 'File Format', 'File Size'])
            ?: $this->titleFromItem($item);

        if ($title === null || $title === '') {
            return null;
        }

        $author = $this->field($summary, 'Author', ['Language', 'File Format', 'File Size', 'PAGE', 'BOOK PREVIEW'])
            ?: $this->authorFromCategories($item)
            ?: $this->authorFromItemTitle($item);

        $language = $this->field($summary, 'Language', ['File Format', 'File Size', 'PAGE', 'BOOK PREVIEW'])
            ?: 'Sinhala';

        $extension = $this->field($summary, 'File Format', ['File Size', 'PAGE', 'BOOK PREVIEW']);
        $size      = $this->field($summary, 'File Size', ['PAGE', 'BOOK PREVIEW', 'File Format']);

        return [
            'md5'          => md5($guid),
            'title'        => Str::limit($title, 250, ''),
            'author'       => $author ? Str::limit($author, 250, '') : null,
            'language'     => Str::limit($language, 60, ''),
            'extension'    => $extension ? Str::lower(Str::limit($extension, 12, '')) : null,
            'size'         => $size ? Str::limit($size, 30, '') : null,
            'cover_src'    => $this->firstImage($content, $feedUrl),
            // Prefer a real off-site file link when the item has one; otherwise
            // the origin post is where the reader gets the book.
            'download_url' => $this->downloadLink($content, $feedUrl) ?: ($link ?: null),
            'source_url'   => $link ?: null,
            'categories'   => $this->categories($item),
        ];
    }

    /** Decode entities + strip tags, collapsing whitespace to single spaces. */
    private function plainText(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Pull "Label : value" out of the summary line, stopping at whichever of
     * the following labels appears first. The source writes the whole block on
     * one line, so a plain "everything after the colon" grab would swallow the
     * rest of the metadata.
     */
    private function field(string $text, string $label, array $stopWords = []): ?string
    {
        // Stop words are of two shapes: sibling labels ("Author", "File Size")
        // which are followed by a colon, and bare markers the source prints
        // between sections ("PAGE 1", "BOOK PREVIEW") which are not. Matching
        // the stop word on its own — rather than requiring a trailing ":" —
        // terminates the capture for both, otherwise a bare marker gets
        // swallowed into the value (e.g. size = "768 KB BOOK PREVIEW").
        $stops = array_map(fn ($w) => preg_quote($w, '~'), $stopWords);
        $stop  = $stops ? '\b(?:' . implode('|', $stops) . ')\b' : '$';

        $pattern = '~' . preg_quote($label, '~') . '\s*:\s*(.+?)\s*(?=' . $stop . '|$)~ui';
        if (!preg_match($pattern, $text, $m)) {
            return null;
        }

        // Trim surrounding whitespace and separator punctuation. This MUST be a
        // UTF-8-aware regex, not trim() with a character list: trim() works on
        // bytes, so listing "–"/"—" there strips their individual bytes off the
        // ends — and 0x93/0x94 are also the final byte of Sinhala vowel signs
        // like "ු" (E0 B7 94), which would leave a truncated, invalid sequence.
        $value = preg_replace('/^[\s|:\-–—]+|[\s|:\-–—]+$/u', '', $m[1]) ?? '';
        return $value !== '' ? $value : null;
    }

    /** Feed <title>, e.g. "Nihada Suli Sulaga – Malee". */
    private function titleFromItem(SimpleXMLElement $item): ?string
    {
        $title = $this->plainText((string) $item->title);
        return $title !== '' ? $title : null;
    }

    /**
     * The source tags each post with its genre AND the author's name, e.g.
     * ["Romance", "Chathu"]. The genre words are a known set, so whatever
     * isn't one of those is the author.
     */
    private function authorFromCategories(SimpleXMLElement $item): ?string
    {
        $cats = $this->categories($item);
        if (count($cats) < 2) {
            return null;
        }

        // The first category is the genre; later ones are author tags.
        foreach (array_slice($cats, 1) as $cat) {
            if (!in_array(mb_strtolower($cat), self::NON_AUTHOR_CATEGORIES, true)) {
                return $cat;
            }
        }
        return null;
    }

    /** Last resort: the part of "Title – Author" after the dash. */
    private function authorFromItemTitle(SimpleXMLElement $item): ?string
    {
        $title = $this->plainText((string) $item->title);
        if (preg_match('~\s[–—-]\s(.+)$~u', $title, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** @return string[] */
    private function categories(SimpleXMLElement $item): array
    {
        $out = [];
        foreach ($item->category as $cat) {
            $value = $this->plainText((string) $cat);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * First <img> in the item body — the source always leads with the cover
     * thumbnail before the page scans. Relative srcs are resolved against the
     * feed's own host.
     */
    private function firstImage(string $html, string $feedUrl): ?string
    {
        if (!preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m)) {
            return null;
        }
        return $this->absoluteUrl(html_entity_decode($m[1]), $feedUrl);
    }

    /**
     * An off-site link in the body is the actual book file (the source uses
     * Yandex Disk and similar). Links back to the source's own host are just
     * navigation, so they're ignored here.
     */
    private function downloadLink(string $html, string $feedUrl): ?string
    {
        if (!preg_match_all('~href=["\']([^"\']+)["\']~i', $html, $m)) {
            return null;
        }

        $sourceHost = strtolower((string) parse_url($feedUrl, PHP_URL_HOST));

        foreach ($m[1] as $href) {
            $href = html_entity_decode($href);
            if (!preg_match('~^https?://~i', $href)) {
                continue;
            }
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === '' || $host === $sourceHost || str_ends_with($host, '.' . $sourceHost)) {
                continue;
            }
            // Skip the usual social share / comment links.
            if (preg_match('~(facebook|twitter|x\.com|pinterest|whatsapp|telegram\.me|t\.me|gravatar|wordpress|gstatic|google)\.~i', $host)) {
                continue;
            }
            return $href;
        }
        return null;
    }

    /** Resolve a possibly-relative URL against the feed's scheme + host. */
    private function absoluteUrl(string $url, string $base): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $host   = parse_url($base, PHP_URL_HOST);
        if (!$host) {
            return $url;
        }
        return $scheme . '://' . $host . '/' . ltrim($url, '/');
    }

    /**
     * Copy the cover into our own public storage and return the absolute URL
     * we'll store on the book. Hotlinking the source is fragile — it may block
     * external referrers or rotate its upload paths — and Telegram needs a
     * cover it can actually fetch.
     *
     * Returns null (rather than throwing) when the cover can't be copied: a
     * book without a cover is still worth publishing, it just won't be
     * eligible for the image-based cross-posters.
     */
    private function copyCover(?string $sourceUrl): ?string
    {
        if (!$sourceUrl) {
            return null;
        }

        try {
            $res = Http::withHeaders(['User-Agent' => $this->cfg['user_agent'] ?? 'Tanbat/1.0'])
                ->withOptions(['verify' => false])
                ->timeout((int) ($this->cfg['http_timeout'] ?? 30))
                ->get($sourceUrl);

            if (!$res->successful()) {
                return null;
            }

            $body = $res->body();
            $max  = (int) ($this->cfg['max_image_bytes'] ?? 8 * 1024 * 1024);
            if ($body === '' || strlen($body) > $max) {
                return null;
            }

            $ext = match (strtolower(trim(explode(';', (string) $res->header('Content-Type'))[0]))) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png'               => 'png',
                'image/gif'               => 'gif',
                'image/webp'              => 'webp',
                default                   => null,
            };
            if ($ext === null) {
                return null;
            }

            $dir  = trim((string) ($this->cfg['cover_dir'] ?? 'books/covers'), '/');
            $path = $dir . '/' . Str::random(40) . '.' . $ext;
            Storage::disk($this->cfg['cover_disk'] ?? 'public')->put($path, $body);

            return $this->publicUrl($path);
        } catch (\Throwable $e) {
            Log::info('Cover copy failed, publishing book without one: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Absolute public URL for a stored cover. Built from the configured public
     * site URL rather than APP_URL — in development APP_URL points at a local
     * XAMPP subdirectory, and a cover URL that only resolves on localhost is
     * useless to both the cross-posters and search engines.
     */
    private function publicUrl(string $path): string
    {
        $base = rtrim((string) config('telegram.site_url'), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        return $base . '/storage/' . ltrim($path, '/');
    }

    /** Create the anonymous book post + its detail row. */
    private function createBook(array $parsed): BookDetail
    {
        // Fetch the cover outside the transaction — it's the slow part and
        // there's no reason to hold a DB lock across a network call.
        $coverUrl = $this->copyCover($parsed['cover_src']);

        return DB::transaction(function () use ($parsed, $coverUrl) {
            $post = Post::create([
                'user_id'     => $this->anonymousUserId(),
                'type'        => 'book',
                'title'       => $parsed['title'],
                'description' => null,
            ]);

            return BookDetail::create([
                'post_id'      => $post->id,
                'md5'          => $parsed['md5'],
                'slug'         => $this->makeUniqueSlug($parsed['title'], $parsed['md5']),
                'title'        => $parsed['title'],
                'author'       => $parsed['author'],
                'language'     => $parsed['language'],
                'extension'    => $parsed['extension'],
                'size'         => $parsed['size'],
                'cover_url'    => $coverUrl,
                'download_url' => $parsed['download_url'],
                'source_url'   => $parsed['source_url'],
                'description'  => $this->buildDescription($parsed),
            ]);
        });
    }

    /**
     * Short factual blurb for the book page. Kept to the metadata we parsed
     * plus an attribution line — we don't republish the source's body text.
     */
    private function buildDescription(array $parsed): string
    {
        $lines = [];
        if ($parsed['author']) {
            $lines[] = 'Author: ' . $parsed['author'];
        }
        if ($parsed['language']) {
            $lines[] = 'Language: ' . $parsed['language'];
        }
        if ($parsed['extension']) {
            $lines[] = 'Format: ' . strtoupper($parsed['extension']);
        }
        if ($parsed['size']) {
            $lines[] = 'Size: ' . $parsed['size'];
        }

        $genre = $parsed['categories'][0] ?? null;
        if ($genre) {
            $lines[] = 'Category: ' . $genre;
        }
        if ($parsed['source_url']) {
            $lines[] = 'Source: ' . $parsed['source_url'];
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve (creating on first use) the shared anonymous system account that
     * owns guest-published books. Mirrors AssistantController's resolver so
     * feed imports collapse onto the same account.
     */
    private function anonymousUserId(): int
    {
        return User::firstOrCreate(
            ['username' => 'anonymous'],
            [
                'name'     => 'Anonymous',
                'age'      => 18,
                'gender'   => 'other',
                'country'  => 'US',
                'email'    => 'anonymous@tanbat.local',
                'password' => bcrypt(Str::random(40)),
                'role'     => 'user',
            ]
        )->id;
    }

    /**
     * Deterministic, collision-resistant slug: slugified title + md5 prefix.
     * Mirrors AssistantController::makeUniqueSlug so imported and wizard-made
     * books share one URL scheme.
     *
     * Str::slug() strips Sinhala script entirely, so a title that is purely
     * Sinhala would slug to an empty string — the md5 suffix keeps those rows
     * unique and addressable.
     */
    private function makeUniqueSlug(string $title, string $md5): string
    {
        $base = Str::limit(Str::slug($title) ?: 'book', 60, '');
        $candidate = $base . '-' . substr(strtolower($md5), 0, 8);

        if (!BookDetail::where('slug', $candidate)->exists()) {
            return $candidate;
        }
        return $candidate . '-' . Str::lower(Str::random(4));
    }
}
