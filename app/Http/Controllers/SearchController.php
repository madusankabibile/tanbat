<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** GET /search?q=... — full search results page */
    public function page(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        // omrms.com has its own guest-accessible, article-only search page.
        if (\App\Support\Omrms::isActive()) {
            return app(OmrmsController::class)->search($request);
        }

        if (!Auth::check()) {
            return redirect()->route('home');
        }
        $q = trim((string) $request->query('q', ''));
        $tab = $request->query('tab', 'all');
        return view('search', ['q' => $q, 'tab' => $tab]);
    }

    /** GET /api/search/suggest?q=... — small set of mixed live suggestions for the navbar dropdown */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 1) {
            return response()->json([
                'q'      => $q,
                'people' => [], 'posts' => [], 'tags' => [],
            ]);
        }

        $like = '%' . $this->escapeLike($q) . '%';
        $prefixLike = $this->escapeLike($q) . '%';

        $people = User::query()
            ->select('id', 'name', 'username', 'profile_picture')
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                  ->orWhere('username', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn (User $u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'profile_picture' => $u->avatarUrl(),
                'url'             => url('/' . $u->username),
            ]);

        // Posts text-search. Joining book_details lets a query like "Pragmatic
        // Programmer" surface book posts even though their title/author live
        // on the side table, not on posts.title. Filter book posts out for
        // anonymous viewers — the rest of the app treats them as members-only.
        $postQuery = Post::query()
            ->from('posts')
            ->leftJoin('book_details', 'book_details.post_id', '=', 'posts.id')
            ->select('posts.*')
            ->with(['user:id,name,username,profile_picture', 'bookDetail']);

        if (!Auth::check()) {
            $postQuery->where('posts.type', '!=', 'book');
        }

        $posts = $postQuery
            ->where(function ($w) use ($like) {
                $w->where('posts.title', 'like', $like)
                  ->orWhere('posts.status_text', 'like', $like)
                  ->orWhere('posts.description', 'like', $like)
                  ->orWhere('posts.short_description', 'like', $like)
                  ->orWhere('book_details.title', 'like', $like)
                  ->orWhere('book_details.author', 'like', $like)
                  ->orWhere('book_details.publisher', 'like', $like);
            })
            ->latest('posts.created_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $p) => [
                'id'      => $p->id,
                'type'    => $p->type,
                'snippet' => $this->snippet($p),
                'thumb'   => $this->thumbFor($p),
                'user'    => $p->user ? [
                    'name'     => $p->user->name,
                    'username' => $p->user->username,
                ] : null,
                'url'     => $this->urlFor($p, $q),
            ]);

        $tags = Tag::query()
            ->where('name', 'like', $prefixLike)
            ->orWhere('slug', 'like', $prefixLike)
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get()
            ->map(fn (Tag $t) => [
                'id'    => $t->id,
                'name'  => $t->name,
                'slug'  => $t->slug,
                'count' => (int) $t->posts_count,
                'url'   => url('/search?q=' . urlencode('#' . $t->name) . '&tab=tags'),
            ]);

        return response()->json([
            'q'      => $q,
            'people' => $people,
            'posts'  => $posts,
            'tags'   => $tags,
        ]);
    }

    /** GET /api/search/results?q=...&tab=...&page=... */
    public function results(Request $request): JsonResponse
    {
        $q   = trim((string) $request->query('q', ''));
        $tab = (string) $request->query('tab', 'all');
        $page = max(1, (int) $request->query('page', 1));

        if ($q === '') {
            return response()->json([
                'q' => $q, 'tab' => $tab,
                'counts' => ['posts' => 0, 'images' => 0, 'videos' => 0, 'people' => 0, 'tags' => 0],
                'items'  => [], 'has_more' => false,
            ]);
        }

        // Tag-style query — when the user types #foo, treat the bare word as a tag query.
        $tagQuery = null;
        if (str_starts_with($q, '#')) {
            $tagQuery = ltrim($q, '#');
        }

        $like = '%' . $this->escapeLike($tagQuery ?? $q) . '%';

        $counts = [
            'posts'  => $this->postsQuery($q, $like, $tagQuery)->count(),
            'images' => $this->postsQuery($q, $like, $tagQuery)->where('posts.type', 'image')->count(),
            'videos' => $this->postsQuery($q, $like, $tagQuery)->where('posts.type', 'video')->count(),
            'people' => User::where('name', 'like', $like)->orWhere('username', 'like', $like)->count(),
            'tags'   => Tag::where('name', 'like', $like)->orWhere('slug', 'like', $like)->count(),
        ];

        $perPage = 20;
        $items = [];
        $hasMore = false;

        $shaper = new PostController();

        if ($tab === 'people') {
            $paginator = User::query()
                ->select('id', 'name', 'username', 'profile_picture', 'country', 'created_at')
                ->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('username', 'like', $like);
                })
                ->orderByRaw('CASE WHEN username = ? THEN 0 WHEN name = ? THEN 1 ELSE 2 END', [$q, $q])
                ->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(fn (User $u) => [
                'kind'            => 'user',
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'profile_picture' => $u->avatarUrl(),
                'country'         => $u->country,
                'url'             => url('/' . $u->username),
            ]);
            $hasMore = $paginator->hasMorePages();
        } elseif ($tab === 'tags') {
            $paginator = Tag::query()
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(fn (Tag $t) => [
                'kind'  => 'tag',
                'id'    => $t->id,
                'name'  => $t->name,
                'slug'  => $t->slug,
                'count' => (int) $t->posts_count,
                'url'   => url('/search?q=' . urlencode('#' . $t->name) . '&tab=posts'),
            ]);
            $hasMore = $paginator->hasMorePages();
        } else {
            $query = $this->postsQuery($q, $like, $tagQuery);

            if ($tab === 'images') {
                $query->where('posts.type', 'image');
            } elseif ($tab === 'videos') {
                $query->where('posts.type', 'video');
            } elseif ($tab === 'posts') {
                // all post types — leave as is
            } elseif ($tab === 'all') {
                // all post types — leave as is
            }

            $with = ['user:id,name,username,profile_picture', 'category', 'media', 'tags', 'bookDetail'];
            if (Auth::check()) {
                $uid = Auth::id();
                $with['likers'] = fn ($qq) => $qq->where('users.id', $uid);
            }

            $paginator = $query->with($with)
                ->orderByDesc('posts.created_at')
                ->paginate($perPage, ['posts.*'], 'page', $page);

            $items = collect($paginator->items())->map(fn (Post $p) => $shaper->shapePublic($p));
            $hasMore = $paginator->hasMorePages();
        }

        return response()->json([
            'q'       => $q,
            'tab'     => $tab,
            'counts'  => $counts,
            'items'   => $items,
            'page'    => $page,
            'has_more'=> $hasMore,
        ]);
    }

    /**
     * Posts query used by counts and listings — covers text columns, book
     * metadata (via book_details), and tag matches. Anonymous viewers can't
     * see book posts.
     */
    private function postsQuery(string $q, string $like, ?string $tagQuery)
    {
        $query = Post::query()
            ->from('posts')
            ->leftJoin('book_details', 'book_details.post_id', '=', 'posts.id')
            ->select('posts.*');

        if (!Auth::check()) {
            $query->where('posts.type', '!=', 'book');
        }

        return $query
            ->where(function ($w) use ($like, $tagQuery) {
                $w->where('posts.title', 'like', $like)
                  ->orWhere('posts.status_text', 'like', $like)
                  ->orWhere('posts.description', 'like', $like)
                  ->orWhere('posts.short_description', 'like', $like)
                  ->orWhere('book_details.title', 'like', $like)
                  ->orWhere('book_details.author', 'like', $like)
                  ->orWhere('book_details.publisher', 'like', $like);

                $w->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('post_tag')
                        ->join('tags', 'tags.id', '=', 'post_tag.tag_id')
                        ->whereColumn('post_tag.post_id', 'posts.id')
                        ->where(function ($t) use ($like) {
                            $t->where('tags.name', 'like', $like)
                              ->orWhere('tags.slug', 'like', $like);
                        });
                });
            });
    }

    private function snippet(Post $p): string
    {
        if ($p->type === 'book' && $p->relationLoaded('bookDetail') && $p->bookDetail) {
            $bd = $p->bookDetail;
            $byline = $bd->author ? ' — ' . $bd->author : '';
            return trim(mb_substr(($bd->title ?? '') . $byline, 0, 120));
        }
        $raw = $p->title
            ?: ($p->status_text
                ?: ($p->short_description
                    ?: ($p->description ?: '')));
        $raw = strip_tags((string) $raw);
        $raw = preg_replace('/\s+/u', ' ', $raw);
        return trim(mb_substr($raw, 0, 120));
    }

    private function thumbFor(Post $p): ?string
    {
        if ($p->type === 'book' && $p->relationLoaded('bookDetail') && $p->bookDetail?->cover_url) {
            return $p->bookDetail->cover_url;
        }
        if ($p->featured_image) {
            return asset('storage/' . $p->featured_image);
        }
        if ($p->thumbnail) {
            return preg_match('~^https?://~i', $p->thumbnail)
                ? $p->thumbnail
                : asset('storage/' . $p->thumbnail);
        }
        $first = $p->media()->orderBy('position')->first();
        if ($first) {
            return asset('storage/' . $first->path);
        }
        return null;
    }

    /** Pretty URL for a post in the suggest dropdown. */
    private function urlFor(Post $p, string $q): string
    {
        if ($p->type === 'article' && $p->slug) {
            return url('/articles/' . $p->slug);
        }
        if ($p->type === 'book' && $p->relationLoaded('bookDetail') && $p->bookDetail?->slug) {
            return url('/books/' . $p->bookDetail->slug);
        }
        return url('/search?q=' . urlencode($q) . '&open=' . $p->id);
    }

    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
