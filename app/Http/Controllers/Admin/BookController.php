<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Services\PinterestPoster;
use App\Services\RedditPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
