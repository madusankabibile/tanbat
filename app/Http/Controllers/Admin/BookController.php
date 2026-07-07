<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Models\Post;
use App\Models\User;
use App\Services\PinterestPoster;
use App\Services\RedditPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin management for book posts (posts of type "book").
 *
 * Each row is a BookDetail joined to its parent Post. Admins can:
 *   - delete a book (removes the parent post; book_details cascades)
 *   - (re)publish a book to Reddit on demand, bypassing the heartbeat's
 *     20-minute spacing window
 *
 * The automated cross-poster lives in TaskRunnerController; this controller
 * is the manual override for it.
 */
class BookController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $reddit = $request->query('reddit'); // posted | pending | failed

        $query = BookDetail::query()
            ->with('post.user:id,name,username,profile_picture');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                  ->orWhere('author', 'like', $like)
                  ->orWhere('publisher', 'like', $like)
                  ->orWhere('md5', 'like', $like);
            });
        }

        $maxAttempts = (int) (config('reddit.max_attempts') ?? 5);
        $query = match ($reddit) {
            'posted'  => $query->whereNotNull('reddit_post_id'),
            'failed'  => $query->whereNull('reddit_post_id')->where('reddit_attempts', '>=', $maxAttempts),
            'pending' => $query->whereNull('reddit_post_id')->where('reddit_attempts', '<', $maxAttempts),
            default   => $query,
        };

        $books = $query->latest()->paginate(20)->withQueryString();

        $poster          = new RedditPoster();
        $redditReady     = $poster->isReady();
        $redditAccount   = $redditReady ? $poster->connectedAccount() : null;
        $subreddit       = (string) (config('reddit.subreddit') ?? '');

        $pinPoster       = new PinterestPoster();
        $pinterestReady  = $pinPoster->isReady();
        $pinterestAccount = $pinterestReady ? $pinPoster->connectedAccount() : null;

        return view('admin.books.index', compact(
            'books', 'q', 'reddit', 'maxAttempts',
            'redditReady', 'redditAccount', 'subreddit',
            'pinterestReady', 'pinterestAccount'
        ));
    }

    /** Show the "add a book manually" form. */
    public function create()
    {
        return view('admin.books.create');
    }

    /**
     * Create a book post by hand — the manual counterpart to the Assistant
     * wizard's scrape → confirm → materialize pipeline. The post is attributed
     * to the shared "anonymous" system account, exactly like guest-published
     * books, so it appears as an anonymous post site-wide.
     *
     * md5 is the natural dedup key and is required-unique in the schema. Admins
     * may paste a real Anna's Archive md5 (to line up with the auto-poster's
     * dedup) or leave it blank for a synthesized one.
     */
    public function store(Request $request)
    {
        // Lowercase the md5 up front so the unique check runs against the same
        // casing the DB stores — otherwise an uppercase duplicate slips past
        // Rule::unique and dies on the unique index instead.
        if (is_string($request->input('md5'))) {
            $request->merge(['md5' => strtolower(trim($request->input('md5')))]);
        }

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'author'       => ['nullable', 'string', 'max:255'],
            'publisher'    => ['nullable', 'string', 'max:255'],
            'year'         => ['nullable', 'string', 'max:8'],
            'language'     => ['nullable', 'string', 'max:64'],
            'extension'    => ['nullable', 'string', 'max:16'],
            'size'         => ['nullable', 'string', 'max:32'],
            'cover_url'    => ['nullable', 'url', 'max:1024'],
            'download_url' => ['nullable', 'url', 'max:1024'],
            'description'  => ['nullable', 'string'],
            'md5'          => [
                'nullable', 'string', 'regex:/^[a-f0-9]{32}$/i',
                Rule::unique('book_details', 'md5'),
            ],
        ]);

        // Normalize/generate the dedup key. A blank md5 gets a synthesized 32-hex
        // id so the rest of the book pipeline (dedup, slugging) works unchanged.
        $md5 = isset($data['md5']) && $data['md5'] !== ''
            ? strtolower($data['md5'])
            : md5(Str::uuid()->toString());

        DB::transaction(function () use ($data, $md5) {
            $post = Post::create([
                'user_id'     => $this->anonymousUserId(),
                'type'        => 'book',
                'title'       => $data['title'],
                'description' => null,
            ]);

            BookDetail::create([
                'post_id'      => $post->id,
                'md5'          => $md5,
                'slug'         => $this->makeUniqueSlug($data['title'], $md5),
                'title'        => $data['title'],
                'author'       => $data['author']       ?? null,
                'publisher'    => $data['publisher']    ?? null,
                'year'         => $data['year']         ?? null,
                'language'     => $data['language']     ?? null,
                'extension'    => $data['extension']    ?? null,
                'size'         => $data['size']         ?? null,
                'cover_url'    => $data['cover_url']    ?? null,
                'download_url' => $data['download_url'] ?? null,
                'description'  => $data['description']  ?? null,
            ]);
        });

        return redirect()
            ->route('admin.books.index')
            ->with('status', "Book \"{$data['title']}\" added.");
    }

    /**
     * Resolve (creating on first use) the shared anonymous system account that
     * owns guest-published books. Mirrors AssistantController's resolver so
     * manually-added books collapse onto the same account.
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
     * Mirrors AssistantController::makeUniqueSlug so hand-made and auto-made
     * books share one URL scheme.
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

    /**
     * Delete a book. We remove the parent post so the book disappears from the
     * site entirely; the book_details row cascades via its FK.
     */
    public function destroy(BookDetail $book)
    {
        $title = $book->title;

        if ($book->post) {
            $book->post->delete(); // cascades to book_details
        } else {
            $book->delete();
        }

        return redirect()
            ->route('admin.books.index')
            ->with('status', "Book \"{$title}\" deleted.");
    }

    /**
     * (Re)publish a book to Reddit immediately, bypassing the heartbeat's
     * spacing window. Resets the attempt counter on success so the row reads
     * as freshly posted.
     */
    public function repost(BookDetail $book)
    {
        $poster = new RedditPoster();

        if (!$poster->isReady()) {
            return back()->with('error', 'Reddit is not connected. Connect an account under Integrations → Reddit first.');
        }

        if (empty($book->cover_url)) {
            return back()->with('error', 'This book has no cover image, so it can\'t be posted to Reddit.');
        }

        try {
            $book->loadMissing('post.user:id,username');
            $fullname = $poster->postBook($book);

            $book->update([
                'reddit_post_id'    => $fullname,
                'reddit_posted_at'  => now(),
                'reddit_attempts'   => 0,
                'reddit_last_error' => null,
            ]);

            // Respect the spacing window for the automated poster going forward.
            Cache::put('reddit:last_post_at', time(), 86400);

            return back()->with('status', "Posted \"{$book->title}\" to Reddit ({$fullname}).");
        } catch (\Throwable $e) {
            $book->increment('reddit_attempts');
            $book->update(['reddit_last_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('Manual Reddit cross-post failed', [
                'book_id' => $book->id,
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Reddit post failed: ' . $e->getMessage());
        }
    }

    /**
     * (Re)publish a book to Pinterest immediately, bypassing the heartbeat's
     * spacing window. Mirrors repost() for Reddit.
     */
    public function repostPinterest(BookDetail $book)
    {
        $poster = new PinterestPoster();

        if (!$poster->isReady()) {
            return back()->with('error', 'Pinterest is not connected, or no board is selected. Set it up under Integrations → Pinterest first.');
        }

        if (empty($book->cover_url)) {
            return back()->with('error', 'This book has no cover image, so it can\'t be pinned.');
        }

        try {
            $book->loadMissing('post.user:id,username');
            $pinId = $poster->postBook($book);

            $book->update([
                'pinterest_pin_id'    => $pinId,
                'pinterest_posted_at' => now(),
                'pinterest_attempts'  => 0,
                'pinterest_last_error' => null,
            ]);

            // Respect the spacing window for the automated poster going forward.
            Cache::put('pinterest:last_post_at', time(), 86400);

            return back()->with('status', "Pinned \"{$book->title}\" to Pinterest.");
        } catch (\Throwable $e) {
            $book->increment('pinterest_attempts');
            $book->update(['pinterest_last_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('Manual Pinterest cross-post failed', [
                'book_id' => $book->id,
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Pinterest post failed: ' . $e->getMessage());
        }
    }
}
