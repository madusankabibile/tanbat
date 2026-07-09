<?php

use App\Http\Controllers\Admin\AdvertisementController as AdminAdvertisementController;
use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\BookSearchController as AdminBookSearchController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PinterestController as AdminPinterestController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\RedditController as AdminRedditController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ArticleFeedController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookFeedController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SaveCategoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskRunnerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFeedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pages
|--------------------------------------------------------------------------
*/
// Soft bot scheduler — pinged by the layout's JS heartbeat from logged-in
// browsers. See App\Http\Controllers\TaskRunnerController.
Route::get('/tasks/run', [TaskRunnerController::class, 'run']);

// Public home feed — guests can browse; liking/commenting prompts the auth
// modal (share stays open to everyone). There is no separate landing page any
// more: the root URL IS the feed for guests and members alike.
Route::get('/',                  [PageController::class, 'home'])->name('home');
// XML sitemap of the latest 50 articles. Registered here (before the root-level
// /{username} route, whose charset also matches "sitemap.xml") so it resolves.
Route::get('/sitemap.xml',       [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
// Legacy /home path — kept as a public alias for the many internal url('/home')
// links; serves the same feed as /.
Route::get('/home',              [PageController::class, 'home'])->name('home.feed');
Route::get('/privacy',           [PageController::class, 'privacy'])->name('privacy');
Route::get('/articles/feed.xml', [ArticleFeedController::class, 'rss'])->name('articles.feed');
Route::get('/articles/create',   [PageController::class, 'articleCreate'])->name('articles.create');
Route::get('/articles/{slug}',   [PageController::class, 'articleShow'])->name('articles.show');
// Legacy Sngine blog articles migrated from the old site. Kept at their
// original /blogs/{post_id}/{slug} URLs for SEO. Separate table, no overlap
// with the new /articles blog. See App\Http\Controllers\LegacyArticleController.
Route::get('/blogs/{postId}/{slug?}', [\App\Http\Controllers\LegacyArticleController::class, 'show'])
    ->whereNumber('postId')
    ->name('legacy.article');
// Old Sngine short permalink /posts/{post_id}. Articles were reachable there
// too, so 301-redirect these to their canonical /blogs/{id}/{slug} page. This
// is the browser-facing web route; the SPA's JSON post API lives under /api.
Route::get('/posts/{postId}', [\App\Http\Controllers\LegacyArticleController::class, 'showByPostId'])
    ->whereNumber('postId')
    ->name('legacy.post');
// Dedicated shareable pages for status / image / video posts:
// /posts/{type}/{id}. Two segments, so it never collides with the single-segment
// legacy short permalink above. See PageController::postShow.
Route::get('/posts/{type}/{post}', [PageController::class, 'postShow'])
    ->whereIn('type', ['status', 'image', 'video'])
    ->whereNumber('post')
    ->name('post.show');
// Old WoWonder blog permalink /read-blog/{id}_{slug}.html. Content is gone, so
// the first visit synthesises an article from the title via the Groq LLM,
// persists it, and serves the stored copy forever after (never regenerated).
// See App\Http\Controllers\ReadBlogController.
Route::get('/read-blog/{slug}', [\App\Http\Controllers\ReadBlogController::class, 'show'])
    ->where('slug', '[0-9].*')
    ->name('read-blog');
// Old WoWonder photo-album permalink /albums/{username}. Forwards to the
// member's photos tab, or shows the "account deleted" recovery page if they're
// gone. See App\Http\Controllers\UserController::albums.
Route::get('/albums/{username}', [UserController::class, 'albums'])
    ->where('username', '[A-Za-z0-9_.]+')
    ->name('legacy.albums');
// Legacy profile path — kept as a permanent alias to the same controller so
// old /u/{username} links (and usernames that collide with a reserved
// top-level route, e.g. "admin") still resolve. New links all use the
// root-level /{username} URL registered near the bottom of this file.
Route::get('/u/{username}', [UserController::class, 'show'])
    ->where('username', '[A-Za-z0-9_.]+');
Route::get('/messages',          [MessageController::class, 'index'])->name('messages.index');
// Legacy "Message BabyBoss" deep links — the persona is retired; send them
// to the Tanbat Assistant wizard instead.
Route::redirect('/messages/1',   '/assistant');
Route::get('/messages/{user}',   [MessageController::class, 'show'])->whereNumber('user')->name('messages.show');

/* Vanity-URL redirects. (/chinmoy9722 now resolves directly via the
   root-level /{username} profile route below, so no redirect is needed.) */
Route::redirect('/boss',         '/assistant');
// Legacy library search page → the new Books page (301 for SEO).
Route::permanentRedirect('/library_search.php', '/books');
Route::get('/search',            [SearchController::class, 'page'])->name('search');
// Public blog index — geo/hot/new ranked article cards. Guest-accessible.
// See App\Http\Controllers\BlogController.
Route::get('/blog',              [\App\Http\Controllers\BlogController::class, 'page'])->name('blog');
// Legacy WoWonder blog-category permalink /blog-category/{id}. Renders the blog
// index pre-filtered to that category (or the full index if the id is unknown).
Route::get('/blog-category/{category}', [\App\Http\Controllers\BlogController::class, 'category'])
    ->whereNumber('category')
    ->name('blog.category');
Route::get('/discover/people',   [PeopleController::class, 'page'])->name('people');
Route::get('/users/feed.xml',    [UserFeedController::class, 'rss'])->name('users.feed');
Route::get('/assistant',         [AssistantController::class, 'page'])->name('assistant');
Route::get('/books',             [AssistantController::class, 'booksPage'])->name('books');
// Public RSS feed of latest books — Pinterest auto-publish source. Declared
// BEFORE the {slug} route so "feed.xml" isn't swallowed as a book slug.
Route::get('/books/feed.xml',    [BookFeedController::class, 'rss'])->name('books.feed');
Route::get('/books/{slug}',      [AssistantController::class, 'show'])->name('books.show');

/*
|--------------------------------------------------------------------------
| Auth (session-based, CSRF-protected JSON endpoints)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login',    [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
    Route::get('/user',      [AuthController::class, 'user'])->name('user');
});

/*
|--------------------------------------------------------------------------
| API for the SPA (session-auth + CSRF)
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/sidebar',    [PageController::class, 'sidebar']);
    Route::get('/visitors',   [PageController::class, 'visitors']);
    Route::get('/search/suggest', [SearchController::class, 'suggest']);
    Route::get('/search/results', [SearchController::class, 'results']);

    // Public suggested-people list for the "account deleted" recovery page.
    Route::get('/discover/suggested', [PeopleController::class, 'suggested']);

    // Public blog feed — geo/hot/new ranked article cards for the /blog page.
    Route::get('/blog/feed', [\App\Http\Controllers\BlogController::class, 'feed']);

    // Tanbat Assistant is public end-to-end — guests can search the library and
    // queue a book request; guest requests are attributed to a shared anonymous
    // system account (see AssistantController::requestUserId).
    Route::get('/assistant/search', [AssistantController::class, 'search']);
    Route::post('/assistant/confirm', [AssistantController::class, 'confirm']);
    Route::get('/assistant/status',   [AssistantController::class, 'status']);
    Route::get('/posts',      [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show'])->whereNumber('post');

    // Personalized feed + interaction logging
    Route::get('/feed',                     [FeedController::class, 'index']);
    Route::post('/feed/impressions',        [FeedController::class, 'impressions']);
    Route::post('/posts/{post}/click',      [FeedController::class, 'click']);

    Route::get('/users/{user}',           [UserController::class, 'profile']);
    Route::get('/users/{user}/posts',     [UserController::class, 'posts']);
    Route::get('/users/{user}/followers', [UserController::class, 'followers']);
    Route::get('/users/{user}/following', [UserController::class, 'following']);

    Route::middleware('auth')->group(function () {
        Route::get('/people/summary',  [PeopleController::class, 'summary']);
        Route::get('/people/filters',  [PeopleController::class, 'filters']);
        Route::get('/people/{section}', [PeopleController::class, 'list'])
            ->whereIn('section', ['following', 'followers', 'visitors', 'discover']);

        Route::post('/posts/status',  [PostController::class, 'storeStatus']);
        Route::post('/posts/image',   [PostController::class, 'storeImage']);
        Route::post('/posts/video',   [PostController::class, 'storeVideo']);
        Route::post('/posts/article', [PostController::class, 'storeArticle']);
        Route::post('/posts/inline-image', [PostController::class, 'uploadInlineImage']);
        Route::get('/posts/saved',     [PostController::class, 'saved']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);

        // Personal "save folders" used to organize bookmarked posts.
        Route::get('/save-categories',                [SaveCategoryController::class, 'index']);
        Route::post('/save-categories',               [SaveCategoryController::class, 'store']);
        Route::patch('/save-categories/{category}',   [SaveCategoryController::class, 'update']);
        Route::delete('/save-categories/{category}',  [SaveCategoryController::class, 'destroy']);
        Route::patch('/posts/{post}',  [PostController::class, 'update']);

        Route::post('/posts/{post}/like',     [PostController::class, 'toggleLike']);
        Route::post('/posts/{post}/share',    [PostController::class, 'recordShare']);
        Route::post('/posts/{post}/save',     [PostController::class, 'toggleSave']);
        Route::post('/posts/{post}/hide',     [PostController::class, 'hide']);

        Route::post('/users/{user}/follow',   [UserController::class, 'toggleFollow']);
        Route::patch('/users/{user}',         [UserController::class, 'update']);
        Route::post('/users/{user}/avatar',   [UserController::class, 'updateAvatar']);
        Route::post('/users/{user}/banner',   [UserController::class, 'updateBanner']);

        Route::get('/notifications',          [NotificationController::class, 'index']);
        Route::post('/notifications/read',    [NotificationController::class, 'markRead']);

        Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
        Route::delete('/comments/{comment}',  [CommentController::class, 'destroy']);

        // Messaging
        Route::get('/messages/unread-count',            [MessageController::class, 'unreadCount']);
        Route::get('/messages/threads',                 [MessageController::class, 'threads']);
        Route::get('/messages/threads/{user}',          [MessageController::class, 'thread'])->whereNumber('user');
        Route::post('/messages/threads/{user}',         [MessageController::class, 'send'])->whereNumber('user');
        Route::get('/messages/threads/{user}/poll',     [MessageController::class, 'poll'])->whereNumber('user');
        Route::post('/messages/threads/{user}/typing',     [MessageController::class, 'typing'])->whereNumber('user');
        Route::post('/messages/threads/{user}/stop-typing',[MessageController::class, 'stopTyping'])->whereNumber('user');
        Route::post('/messages/threads/{user}/read',    [MessageController::class, 'read'])->whereNumber('user');

        // Tanbat Assistant — search/confirm/status are all public (see above);
        // this is the logged-in books grid only.
        Route::get('/books',              [AssistantController::class, 'booksList']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel (session auth + admin role)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('users',                [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit',    [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}',         [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}',      [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Posts
    Route::get('posts',                [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('posts/{post}',         [AdminPostController::class, 'show'])->name('posts.show');
    Route::get('posts/{post}/edit',    [AdminPostController::class, 'edit'])->name('posts.edit');
    Route::put('posts/{post}',         [AdminPostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}',      [AdminPostController::class, 'destroy'])->name('posts.destroy');

    // Books (book-type posts + Reddit cross-post controls)
    Route::get('books',                [AdminBookController::class, 'index'])->name('books.index');
    Route::get('books/create',         [AdminBookController::class, 'create'])->name('books.create');
    Route::post('books',               [AdminBookController::class, 'store'])->name('books.store');
    Route::post('books/{book}/repost', [AdminBookController::class, 'repost'])->name('books.repost');
    Route::post('books/{book}/repost-pinterest', [AdminBookController::class, 'repostPinterest'])->name('books.repost-pinterest');
    Route::delete('books/{book}',      [AdminBookController::class, 'destroy'])->name('books.destroy');

    // Categories
    Route::get('categories',           [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('categories',          [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}',[AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // Book search engine (Anna's Archive / Z-Library) + domain config
    Route::get('book-search',          [AdminBookSearchController::class, 'index'])->name('book-search.index');
    Route::put('book-search',          [AdminBookSearchController::class, 'update'])->name('book-search.update');

    // Reddit cross-poster OAuth
    Route::get('reddit',               [AdminRedditController::class, 'index'])->name('reddit.index');
    Route::post('reddit/connect',      [AdminRedditController::class, 'connect'])->name('reddit.connect');
    Route::get('reddit/callback',      [AdminRedditController::class, 'callback'])->name('reddit.callback');
    Route::post('reddit/disconnect',   [AdminRedditController::class, 'disconnect'])->name('reddit.disconnect');

    // Pinterest cross-poster OAuth + board selection
    Route::get('pinterest',             [AdminPinterestController::class, 'index'])->name('pinterest.index');
    Route::post('pinterest/connect',    [AdminPinterestController::class, 'connect'])->name('pinterest.connect');
    Route::get('pinterest/callback',    [AdminPinterestController::class, 'callback'])->name('pinterest.callback');
    Route::post('pinterest/board',      [AdminPinterestController::class, 'board'])->name('pinterest.board');
    Route::post('pinterest/token',      [AdminPinterestController::class, 'token'])->name('pinterest.token');
    Route::post('pinterest/disconnect', [AdminPinterestController::class, 'disconnect'])->name('pinterest.disconnect');

    // Advertisements
    Route::get('ads',                  [AdminAdvertisementController::class, 'index'])->name('ads.index');
    Route::get('ads/create',           [AdminAdvertisementController::class, 'create'])->name('ads.create');
    Route::post('ads',                 [AdminAdvertisementController::class, 'store'])->name('ads.store');
    Route::get('ads/{ad}/edit',        [AdminAdvertisementController::class, 'edit'])->name('ads.edit');
    Route::put('ads/{ad}',             [AdminAdvertisementController::class, 'update'])->name('ads.update');
    Route::post('ads/{ad}/toggle',     [AdminAdvertisementController::class, 'toggle'])->name('ads.toggle');
    Route::delete('ads/{ad}',          [AdminAdvertisementController::class, 'destroy'])->name('ads.destroy');
});

/*
|--------------------------------------------------------------------------
| Root-level username profiles — https://tanbat.com/{username}
|--------------------------------------------------------------------------
| MUST be registered last so every explicit route above (/home, /books,
| /search, /assistant, …) is matched before a path is treated as a username.
| Constrained to the username charset (letters, numbers, underscore, dot) so
| multi-segment URLs and unmatched paths fall through to the fallback.
*/
Route::get('/{username}', [UserController::class, 'show'])
    ->where('username', '[A-Za-z0-9_.]+')
    ->name('profile');

// Deep-linkable profile tabs — /{username}/followers, /{username}/photos, …
// Constrained to the known section names so unrelated two-segment paths still
// fall through to the fallback below.
Route::get('/{username}/{section}', [UserController::class, 'show'])
    ->where('username', '[A-Za-z0-9_.]+')
    ->whereIn('section', UserController::PROFILE_SECTIONS)
    ->name('profile.section');

// Legacy WoWonder profile sub-pages — /{username}/groups, /{username}/albums,
// /{username}/likes, … Those features don't exist here, so hand the request to
// the profile controller: it shows the member's profile if they still exist, or
// the themed "account not found" (410) page if they don't — instead of silently
// dropping to the home feed. Runs after the known-section route above.
Route::get('/{username}/{section}', [UserController::class, 'show'])
    ->where('username', '[A-Za-z0-9_.]+')
    ->where('section', '[A-Za-z0-9_.-]+')
    ->name('profile.legacy-section');

/*
|--------------------------------------------------------------------------
| Fallback — show the public home feed for any other route
|--------------------------------------------------------------------------
*/
Route::fallback([PageController::class, 'home']);
