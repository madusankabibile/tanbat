<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PostController;
use App\Models\BookDetail;
use App\Models\PendingBookRequest;
use App\Models\Post;
use App\Models\UserNotification;
use App\Support\BookSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tanbat Assistant — wizard-style resource finder.
 *
 * Today: books only. The active source is admin-selectable between Anna's
 * Archive (/public/temp/annas.php) and Z-Library (/public/temp/zlib.php) via
 * the admin "Book Search Engine" panel — see App\Support\BookSearch. Other
 * services (software / game / movie) are stubs that return "coming soon".
 */
class AssistantController
{
    /** Page entry point — renders the wizard SPA shell. */
    public function page()
    {
        if (!Auth::check()) {
            return redirect()->route('landing');
        }
        return view('assistant');
    }

    /**
     * Books listing page.
     *
     * Logged-in users get the JS-driven, searchable, infinite-scroll grid
     * (powered by /api/books). Guests get the full library rendered
     * server-side — every book as a plain tile linking to its detail page
     * (no ad links) — alongside a right-hand ad panel.
     */
    public function booksPage()
    {
        $guestBooks = null;
        if (!Auth::check()) {
            $guestBooks = BookDetail::query()
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->latest('id')
                ->limit(200)
                ->get();
        }

        return view('books', ['guestBooks' => $guestBooks]);
    }

    /**
     * GET /books/{slug} — dedicated book detail page.
     *
     * Guests can see the cover, title and description but are prompted to
     * sign in before they can download or interact with the post.
     */
    public function show(string $slug)
    {
        $book = BookDetail::where('slug', $slug)->first();
        if (!$book || !$book->post) {
            abort(404);
        }

        $post = $book->post()
            ->with([
                'user:id,name,username,profile_picture',
                'comments' => fn ($q) => $q->whereNull('parent_id'),
                'comments.user:id,name,username,profile_picture',
                'comments.replies' => fn ($q) => $q->oldest(),
                'comments.replies.user:id,name,username,profile_picture',
            ])
            ->first();
        $post->setRelation('bookDetail', $book);

        $myReaction = Auth::check() ? $post->reactionBy(Auth::id()) : null;

        return view('book-show', [
            'post'       => $post,
            'book'       => $book,
            'liked'      => $myReaction !== null,
            'myReaction' => $myReaction,
        ]);
    }

    /**
     * GET /api/books — paginated list of all book posts (auth only).
     * Powers the dedicated /books page; the home feed renders book posts
     * via the regular FeedRanker pipeline.
     */
    public function booksList(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in required.'], 401);
        }

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        $query = Post::with(['user:id,name,username,profile_picture', 'bookDetail'])
            ->where('type', 'book');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->whereHas('bookDetail', function ($b) use ($like) {
                $b->where('title', 'like', $like)
                  ->orWhere('author', 'like', $like)
                  ->orWhere('publisher', 'like', $like);
            });
        }

        $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        $shaper = new PostController();
        $rows = collect($paginator->items())->map(fn (Post $p) => $shaper->shapePublic($p))->all();

        return response()->json([
            'data'  => $rows,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/assistant/search?service=book&query=...
     *
     * Search is cached per query so 20 simultaneous users with the same query
     * still only fan out to the scraper once per TTL window.
     */
    public function search(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in required.'], 401);
        }

        $data = $request->validate([
            'service' => 'required|in:book,software,game,movie',
            'query'   => 'required|string|min:2|max:200',
        ]);

        // Only "book" actually works right now; the others are intentional stubs
        // so the wizard's first step can list every planned service.
        if ($data['service'] !== 'book') {
            return response()->json([
                'ok'       => false,
                'message'  => ucfirst($data['service']) . ' search is coming soon.',
                'results'  => [],
            ], 200);
        }

        $query = trim(preg_replace('/\s+/u', ' ', $data['query']));
        $cacheKey = $this->bookCacheKey($query);

        // 10-min TTL — short enough that fresh uploads still surface eventually,
        // long enough to coalesce a burst of duplicate searches.
        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $request) {
            return $this->scrapeBooks($query, $request);
        });

        return response()->json($payload);
    }

    /** How long a "confirm" sits in the queue before the post is created. */
    public const PREPARATION_SECONDS = 120;

    /**
     * POST /api/assistant/confirm
     *  body: { service: "book", query: "...", md5: "<32hex>" }
     *
     * Queues the book request. The actual post is created PREPARATION_SECONDS
     * later by processPendingRequests() — kicked off by the /tasks/run
     * heartbeat and also by the wizard's /api/assistant/status polling.
     *
     * Dedup model:
     *   • If a BookDetail with this md5 already exists, skip the wait entirely
     *     — fire a fresh notification for this user and return 'ready'.
     *   • Otherwise create a per-user pending row. Concurrent confirms for
     *     the same md5 each get their own pending row → each user gets their
     *     own notification when the underlying post is materialized.
     */
    public function confirm(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in required.'], 401);
        }

        $data = $request->validate([
            'service' => 'required|in:book',
            'query'   => 'required|string|min:2|max:200',
            'md5'     => 'required|string|regex:/^[a-f0-9]{32}$/i',
        ]);

        $query = trim(preg_replace('/\s+/u', ' ', $data['query']));
        $md5   = strtolower($data['md5']);
        $userId = Auth::id();

        // Fast path: this book already exists in the library. Skip the wait,
        // just notify (if not already notified recently).
        $existingBook = BookDetail::where('md5', $md5)->first();
        if ($existingBook && $existingBook->post) {
            $this->notifyBookReady($userId, $existingBook->post);
            $shaped = (new PostController())->shapePublic($existingBook->post->load('bookDetail', 'user'));
            return response()->json([
                'ok'            => true,
                'status'        => 'ready',
                'md5'           => $md5,
                'post'          => $shaped,
                'view_url'      => $shaped['view_url'] ?? url('/books/' . $existingBook->slug),
                'eta_seconds'   => 0,
            ]);
        }

        // Re-validate the md5 against the cached search payload so the client
        // can't queue arbitrary md5s.
        $cacheKey = $this->bookCacheKey($query);
        $payload  = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $request) {
            return $this->scrapeBooks($query, $request);
        });
        $match = collect($payload['results'] ?? [])->firstWhere('md5', $md5);
        if (!$match) {
            return response()->json([
                'ok'      => false,
                'message' => 'That book could not be matched against the latest search — please search again.',
            ], 422);
        }

        // Reuse this user's existing pending row if they double-confirmed the
        // same md5 (prevents stacking up duplicate work).
        $pending = PendingBookRequest::firstOrCreate(
            [
                'user_id' => $userId,
                'md5'     => $md5,
                'processed_at' => null,
            ],
            [
                'query'   => $query,
                'payload' => $match,
                'due_at'  => now()->addSeconds(self::PREPARATION_SECONDS),
            ]
        );

        $eta = max(0, now()->diffInSeconds($pending->due_at, false));

        return response()->json([
            'ok'          => true,
            'status'      => 'pending',
            'md5'         => $md5,
            'request_id'  => $pending->id,
            'due_at'      => $pending->due_at->toIso8601String(),
            'eta_seconds' => $eta,
        ]);
    }

    /**
     * GET /api/assistant/status?md5=<32hex>
     *
     * Polled by the wizard's "preparing" pane. When the due_at has passed,
     * this endpoint processes the pending row inline so the user doesn't
     * have to wait for the next /tasks/run tick.
     */
    public function status(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in required.'], 401);
        }
        $data = $request->validate([
            'md5' => 'required|string|regex:/^[a-f0-9]{32}$/i',
        ]);
        $md5 = strtolower($data['md5']);
        $userId = Auth::id();

        // Has the post been created in the meantime?
        $book = BookDetail::with('post.user', 'post.bookDetail')->where('md5', $md5)->first();
        if ($book && $book->post) {
            $shaped = (new PostController())->shapePublic($book->post);
            return response()->json([
                'ok'       => true,
                'status'   => 'ready',
                'post'     => $shaped,
                'view_url' => $shaped['view_url'] ?? url('/books/' . $book->slug),
            ]);
        }

        // Find this user's pending row.
        $pending = PendingBookRequest::where('user_id', $userId)
            ->where('md5', $md5)
            ->whereNull('processed_at')
            ->latest('id')
            ->first();

        if (!$pending) {
            return response()->json(['ok' => false, 'status' => 'unknown'], 404);
        }

        // Past due — process inline so the wizard finishes promptly.
        if ($pending->due_at->isPast()) {
            self::processPendingRequests(5);
            // Re-query — may now be done.
            $book = BookDetail::with('post.user', 'post.bookDetail')->where('md5', $md5)->first();
            if ($book && $book->post) {
                $shaped = (new PostController())->shapePublic($book->post);
                return response()->json([
                    'ok'       => true,
                    'status'   => 'ready',
                    'post'     => $shaped,
                    'view_url' => $shaped['view_url'] ?? url('/books/' . $book->slug),
                ]);
            }
        }

        return response()->json([
            'ok'          => true,
            'status'      => 'pending',
            'md5'         => $md5,
            'due_at'      => $pending->due_at->toIso8601String(),
            'eta_seconds' => max(0, now()->diffInSeconds($pending->due_at, false)),
        ]);
    }

    /**
     * Materialize any pending book requests whose due_at has passed.
     *
     * Called both by the /tasks/run heartbeat (fallback for users who closed
     * their tab) and by /api/assistant/status when the wizard's timer fires
     * (so the user sees their book the moment they hit the 2-minute mark).
     *
     * Concurrency-safe: a cross-process cache lock prevents two workers from
     * picking up the same row, and the inner DB transaction with a SELECT
     * FOR UPDATE on book_details handles the md5 dedup. `$limit` caps the
     * batch size per call so a slow scrape can't stall the heartbeat.
     */
    public static function processPendingRequests(int $limit = 10): int
    {
        $lock = Cache::lock('assistant:process-pending', 30);
        if (!$lock->get()) return 0;

        $self = new self();
        $processed = 0;

        try {
            $rows = PendingBookRequest::whereNull('processed_at')
                ->where('due_at', '<=', now())
                ->orderBy('due_at')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                try {
                    $self->materializeBookPost($row);
                } catch (\Throwable $e) {
                    // Mark processed even on failure so we don't loop forever
                    // on a single bad row. The next user confirm will retry.
                    $row->update(['processed_at' => now()]);
                    \Log::warning('Pending book request failed', [
                        'id' => $row->id, 'error' => $e->getMessage(),
                    ]);
                }
                $processed++;
            }
        } finally {
            $lock->release();
        }

        return $processed;
    }

    /**
     * Turn one pending row into a real Book post (or attach to an existing
     * post for the same md5), and notify the requesting user.
     */
    private function materializeBookPost(PendingBookRequest $pending): void
    {
        $match  = $pending->payload ?: [];
        $md5    = $pending->md5;
        $userId = $pending->user_id;
        $engine = $match['_engine'] ?? 'annas';

        $description = $this->fetchBookDescription($match, $md5, $engine);

        // Anna's "url" is the md5 landing page; Z-Library carries a direct
        // download link separate from its detail page.
        $downloadUrl = $engine === 'zlib'
            ? (($match['download'] ?? '') ?: ($match['url'] ?? null))
            : ($match['url'] ?? null);

        $post = DB::transaction(function () use ($match, $md5, $userId, $description, $downloadUrl) {
            $existing = BookDetail::where('md5', $md5)->lockForUpdate()->first();
            if ($existing) {
                return $existing->post()->with('bookDetail', 'user')->first();
            }

            $slug = $this->makeUniqueSlug($match['title'] ?? 'book', $md5);

            $newPost = Post::create([
                'user_id'     => $userId,
                'type'        => 'book',
                'title'       => $match['title'] ?? 'Untitled',
                'description' => null,
            ]);

            BookDetail::create([
                'post_id'      => $newPost->id,
                'md5'          => $md5,
                'slug'         => $slug,
                'title'        => $match['title']     ?? 'Untitled',
                'author'       => $match['author']    ?? null,
                'publisher'    => $match['publisher'] ?? null,
                'year'         => $match['year']      ?? null,
                'language'     => $match['language']  ?? null,
                'extension'    => $match['extension'] ?? null,
                'size'         => $match['size']      ?? null,
                'cover_url'    => $match['cover']     ?? null,
                'download_url' => $downloadUrl,
                'description'  => $description,
            ]);

            return $newPost->load('bookDetail', 'user');
        });

        $pending->update([
            'processed_at' => now(),
            'post_id'      => $post->id,
        ]);

        $this->notifyBookReady($userId, $post);
    }

    /** Fire the book_ready notification (idempotent within the recent window). */
    private function notifyBookReady(int $userId, Post $post): void
    {
        $alreadyNotified = UserNotification::where('user_id', $userId)
            ->where('type', 'book_ready')
            ->whereRaw("JSON_EXTRACT(data, '$.post_id') = ?", [$post->id])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();
        if ($alreadyNotified) return;

        UserNotification::create([
            'user_id'  => $userId,
            'actor_id' => null,
            'type'     => 'book_ready',
            'data'     => [
                'post_id' => $post->id,
                'title'   => $post->bookDetail?->title,
                'author'  => $post->bookDetail?->author,
            ],
        ]);
    }

    /** Per-query cache key, namespaced by the active engine so switching the
     *  engine in the admin panel doesn't serve another engine's cached hits. */
    private function bookCacheKey(string $query): string
    {
        return 'assistant:book:' . BookSearch::engine() . ':' . sha1(mb_strtolower($query));
    }

    /**
     * Run the admin-selected scraper (Anna's Archive or Z-Library) in-process
     * against the admin-configured domain. Loading the scraper as a library
     * bypasses a self-HTTP roundtrip — shared hosts frequently block PHP from
     * connecting back to their own public hostname.
     *
     * Errors are returned as ok=false instead of throwing so the wizard can
     * show a friendly retry state. Every row is tagged with `_engine` so the
     * later publish step knows where it came from (the active engine may have
     * changed by the time the pending request is materialized).
     */
    private function scrapeBooks(string $query, Request $request): array
    {
        $engine  = BookSearch::engine();
        $domain  = BookSearch::domain($engine);
        $libPath = BookSearch::libPath($engine);

        if (!is_file($libPath)) {
            return [
                'ok'      => false,
                'message' => 'Book search backend is not installed on this server.',
                'query'   => $query,
                'results' => [],
            ];
        }

        try {
            $raw = $engine === 'zlib'
                ? $this->scrapeZlib($query, $domain, $libPath)
                : $this->scrapeAnnas($query, $domain, $libPath);
        } catch (\Throwable $e) {
            $raw = null;
        }

        if ($raw === null) {
            return [
                'ok'      => false,
                'message' => 'The book search service is temporarily unavailable. Please try again.',
                'query'   => $query,
                'results' => [],
            ];
        }

        // Strip results without an md5 (we can't dedup or build a post for those)
        // and cap to the first 25 so the wizard doesn't churn through a huge list.
        $results = collect($raw)
            ->filter(fn ($r) => !empty($r['md5']) && !empty($r['title']))
            ->take(25)
            ->values()
            ->all();

        return [
            'ok'      => true,
            'query'   => $query,
            'engine'  => $engine,
            'count'   => count($results),
            'results' => $results,
        ];
    }

    /** Anna's Archive crawler → rows already carry a real md5. Returns null on error. */
    private function scrapeAnnas(string $query, string $domain, string $libPath): ?array
    {
        if (!defined('ANNAS_LIBRARY_ONLY')) define('ANNAS_LIBRARY_ONLY', true);
        require_once $libPath;
        if (!function_exists('crawl_annas')) return null;

        [$raw, $status, $error, $source] = crawl_annas($query, 1, $domain);
        if ($error !== null) return null;

        return collect($raw ?: [])->map(function ($r) {
            $r['_engine'] = 'annas';
            return $r;
        })->all();
    }

    /**
     * Z-Library crawler → rows have no md5, so we synthesize a stable 32-hex id
     * from the detail-page URL. That lets the entire md5-keyed confirm/publish
     * pipeline (validation regex, dedup, pending queue) work unchanged. The real
     * download link and source URL ride along for the publish step.
     */
    private function scrapeZlib(string $query, string $domain, string $libPath): ?array
    {
        if (!defined('ZLIB_LIBRARY_ONLY')) define('ZLIB_LIBRARY_ONLY', true);
        require_once $libPath;
        if (!function_exists('crawl_zlib')) return null;

        [$raw, $status, $error, $source] = crawl_zlib($query, 1, $domain);
        if ($error !== null) return null;

        return collect($raw ?: [])->map(function ($r) {
            $url = $r['url'] ?? '';
            $seed = $url !== '' ? $url : (string) ($r['id'] ?? '');
            return [
                'title'     => $r['title']     ?? '',
                'author'    => $r['author']    ?? '',
                'publisher' => $r['publisher'] ?? '',
                'year'      => $r['year']      ?? '',
                'language'  => $r['language']  ?? '',
                'extension' => $r['extension'] ?? '',
                'size'      => $r['size']      ?? '',
                'cover'     => $r['cover']     ?? '',
                'url'       => $url,
                'download'  => $r['download']  ?? '',
                'md5'       => $seed !== '' ? md5($seed) : '',
                '_engine'   => 'zlib',
            ];
        })->all();
    }

    /** Engine-aware description lookup for the publish step. */
    private function fetchBookDescription(array $match, string $md5, string $engine): ?string
    {
        if ($engine === 'zlib') {
            return $this->fetchZlibDescription($match['url'] ?? '');
        }
        return $this->fetchBookDetailPage($md5)['description'] ?? null;
    }

    /**
     * Pull the md5 detail page from Anna's Archive and extract a description.
     * Cached for 1 hour per md5 so we don't hammer the source when many users
     * end up confirming the same book in succession. Uses the admin-configured
     * Anna's domain.
     *
     * Returns ['description' => string|null].
     */
    private function fetchBookDetailPage(string $md5): array
    {
        $md5 = strtolower($md5);
        $cacheKey = 'assistant:book:detail:' . $md5;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($md5) {
            $url  = BookSearch::domain('annas') . '/md5/' . $md5;
            $html = $this->httpGet($url, 30, true);
            if (!$html) {
                return ['description' => null];
            }
            return ['description' => $this->extractDescription($html)];
        });
    }

    /**
     * Fetch a Z-Library book page and extract its synopsis. The detail page
     * sits behind the same proof-of-work wall as search, so we route through
     * the zlib.php library's fetch_zlib() to clear it. Cached for 1 hour per URL.
     */
    private function fetchZlibDescription(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;

        $cacheKey = 'assistant:book:detail:zlib:' . sha1($url);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($url) {
            $html = $this->fetchZlibHtml($url);
            if (!$html) return null;
            return $this->extractZlibDescription($html);
        });
    }

    /** Load zlib.php as a library and clear the proof-of-work wall for $url. */
    private function fetchZlibHtml(string $url): ?string
    {
        $libPath = BookSearch::libPath('zlib');
        if (!is_file($libPath)) return null;

        if (!defined('ZLIB_LIBRARY_ONLY')) define('ZLIB_LIBRARY_ONLY', true);
        require_once $libPath;
        if (!function_exists('fetch_zlib')) return null;

        try {
            [$html, $status, $error] = fetch_zlib($url);
        } catch (\Throwable $e) {
            return null;
        }
        return $error === null ? $html : null;
    }

    /**
     * Heuristic description scraper for a Z-Library book page: prefer the
     * synopsis box, fall back to the always-present meta description.
     */
    private function extractZlibDescription(string $html): ?string
    {
        foreach ([
            '~<div[^>]*\bid="bookDescriptionBox"[^>]*>(.*?)</div>~is',
            '~<div[^>]*class="[^"]*bookDescriptionBox[^"]*"[^>]*>(.*?)</div>~is',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $text = $this->normalizeText($m[1]);
                if (mb_strlen($text) >= 40) return $text;
            }
        }

        if (preg_match('~<meta\s+name="description"\s+content="([^"]+)"~i', $html, $m)) {
            $text = $this->normalizeText($m[1]);
            if ($text !== '') return $text;
        }

        return null;
    }

    /**
     * Heuristic description scraper for an Anna's Archive md5 page.
     *
     *  1. Prefer a labelled "description" block inside the page's top-box
     *     (most reliable when a real synopsis is present).
     *  2. Fall back to <meta name="description"> which is always populated
     *     but contains short metadata for books with no synopsis.
     */
    private function extractDescription(string $html): ?string
    {
        // Strategy 1: "<div ...>description</div><div ...>SYNOPSIS</div>"
        if (preg_match(
            '~<div[^>]*class="[^"]*text-xs[^"]*text-gray-500[^"]*uppercase[^"]*"[^>]*>\s*description\s*</div>\s*<div[^>]*>(.*?)</div>~is',
            $html, $m
        )) {
            $text = $this->normalizeText($m[1]);
            if (mb_strlen($text) >= 40) return $text;
        }

        // Strategy 2: meta description
        if (preg_match('~<meta\s+name="description"\s+content="([^"]+)"~i', $html, $m)) {
            $text = $this->normalizeText($m[1]);
            if ($text !== '') return $text;
        }

        return null;
    }

    private function normalizeText(string $raw): string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    /**
     * Generate a unique slug for a book post. Title gets slugified, truncated
     * to 60 chars, and suffixed with the first 8 chars of the md5 — that's
     * deterministic per book and avoids race-y "slug-N" loops under
     * concurrent confirms. A second fallback adds a random suffix on the
     * (extremely unlikely) collision.
     */
    private function makeUniqueSlug(string $title, string $md5): string
    {
        $base = Str::slug($title) ?: 'book';
        $base = Str::limit($base, 60, '');
        $candidate = $base . '-' . substr(strtolower($md5), 0, 8);

        if (!BookDetail::where('slug', $candidate)->exists()) {
            return $candidate;
        }
        // Practically unreachable (md5 prefix collision on same title), but
        // keep a fallback so the insert can't fail on the UNIQUE index.
        return $candidate . '-' . Str::lower(Str::random(4));
    }

    private function httpGet(string $url, int $timeout = 30, bool $browserHeaders = false): ?string
    {
        $ua = $browserHeaders
            ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
            : 'Tanbat-Assistant/1.0';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 400) return null;
            return $body;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'header' => "User-Agent: {$ua}\r\nAccept-Language: en-US,en;q=0.9\r\n"],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }
}
