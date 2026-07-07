<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SaveCategory;
use App\Models\Tag;
use App\Models\UserNotification;
use App\Http\Controllers\CommentController;
use App\Services\InteractionRecorder;
use App\Services\VideoEmbed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /** GET /api/posts  — paginated feed for the home page */
    public function index(Request $request): JsonResponse
    {
        $with = ['user:id,name,username,profile_picture', 'category', 'media', 'tags', 'bookDetail'];
        if (Auth::check()) {
            $uid = Auth::id();
            $with['likers'] = fn ($q) => $q->where('users.id', $uid);
        }
        $query = Post::with($with);
        // Book posts are only visible to authenticated users.
        if (!Auth::check()) {
            $query->where('type', '!=', 'book');
        }
        $posts = $query->latest()->paginate(24);

        return response()->json([
            'data' => collect($posts->items())->map(fn ($p) => $this->shape($p)),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    /** GET /api/posts/{post} — full post + comments (with nested replies) */
    public function show(Post $post): JsonResponse
    {
        if ($post->type === 'book' && !Auth::check()) {
            abort(403);
        }
        $post->load([
            'user:id,name,username,profile_picture',
            'category',
            'media',
            'tags',
            'bookDetail',
            'comments' => fn ($q) => $q->whereNull('parent_id'),
            'comments.user:id,name,username,profile_picture',
            'comments.replies' => fn ($q) => $q->oldest(),
            'comments.replies.user:id,name,username,profile_picture',
        ]);

        // Count this open. views_count also bumps on feed impressions
        // (InteractionRecorder); opening a post adds to it too.
        $post->increment('views_count');

        return response()->json([
            'post'     => $this->shape($post, true),
            'comments' => $post->comments->map(fn ($c) => CommentController::shape($c, true)),
        ]);
    }

    /** POST /api/posts/status */
    public function storeStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status_text' => 'required|string|max:2000',
            'bg_color'    => 'nullable|string|max:32',
            'font_color'  => 'nullable|string|max:32',
            'image'       => 'nullable|image|max:8192',
        ]);

        $post = Post::create([
            'user_id'     => Auth::id(),
            'type'        => 'status',
            'status_text' => $data['status_text'],
            'bg_color'    => $data['bg_color']   ?? null,
            'font_color'  => $data['font_color'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('posts/status', 'public');
            $post->media()->create(['kind' => 'image', 'path' => $path]);
        }

        return response()->json(['success' => true, 'post' => $this->shape($post->fresh(['user','media']))], 201);
    }

    /** POST /api/posts/image  — supports one or many images */
    public function storeImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'is_adult'    => 'required|in:0,1,true,false',
            'images'      => 'required|array|min:1|max:10',
            'images.*'    => 'image|max:8192',
        ]);

        $post = Post::create([
            'user_id'     => Auth::id(),
            'type'        => 'image',
            'category_id' => $data['category_id'],
            'description' => $data['description'] ?? null,
            'is_adult'    => filter_var($data['is_adult'], FILTER_VALIDATE_BOOLEAN),
        ]);

        foreach ($request->file('images') as $idx => $file) {
            $path = $file->store('posts/images', 'public');
            $post->media()->create(['kind' => 'image', 'path' => $path, 'position' => $idx]);
        }

        return response()->json(['success' => true, 'post' => $this->shape($post->fresh(['user','media','category']))], 201);
    }

    /** POST /api/posts/video — accepts either a video file upload or a YouTube/Vimeo embed URL. */
    public function storeVideo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'is_adult'    => 'required|in:0,1,true,false',
            'source'      => 'nullable|in:file,embed',
            'embed_url'   => 'nullable|string|max:500',
            'thumbnail'   => 'nullable|image|max:8192',
            'video'       => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:204800',
        ]);

        $source = $data['source'] ?? ($request->filled('embed_url') ? 'embed' : 'file');

        $thumbValue = null;
        $embedProvider = null;
        $embedId = null;
        $videoPath = null;

        if ($source === 'embed') {
            $parsed = VideoEmbed::parse((string) ($data['embed_url'] ?? ''));
            if (!$parsed) {
                return response()->json([
                    'message' => 'Unsupported embed URL. Paste a YouTube or Vimeo link.',
                    'errors'  => ['embed_url' => ['Unsupported embed URL. Paste a YouTube or Vimeo link.']],
                ], 422);
            }
            [$embedProvider, $embedId] = $parsed;

            if ($request->hasFile('thumbnail')) {
                $thumbValue = $request->file('thumbnail')->store('posts/thumbs', 'public');
            } else {
                $thumbValue = VideoEmbed::fetchPoster($embedProvider, $embedId);
            }
        } else {
            if (!$request->hasFile('video') || !$request->hasFile('thumbnail')) {
                return response()->json([
                    'message' => 'Both a video file and thumbnail are required for file uploads.',
                    'errors'  => [
                        'video'     => $request->hasFile('video') ? [] : ['A video file is required.'],
                        'thumbnail' => $request->hasFile('thumbnail') ? [] : ['A thumbnail is required.'],
                    ],
                ], 422);
            }
            $thumbValue = $request->file('thumbnail')->store('posts/thumbs', 'public');
            $videoPath  = $request->file('video')->store('posts/videos', 'public');
        }

        $post = Post::create([
            'user_id'        => Auth::id(),
            'type'           => 'video',
            'category_id'    => $data['category_id'],
            'description'    => $data['description'] ?? null,
            'is_adult'       => filter_var($data['is_adult'], FILTER_VALIDATE_BOOLEAN),
            'thumbnail'      => $thumbValue,
            'embed_provider' => $embedProvider,
            'embed_id'       => $embedId,
        ]);

        if ($videoPath) {
            $post->media()->create(['kind' => 'video', 'path' => $videoPath]);
        }

        return response()->json(['success' => true, 'post' => $this->shape($post->fresh(['user','media','category']))], 201);
    }

    /** POST /api/posts/article */
    public function storeArticle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'             => 'required|string|max:240',
            'short_description' => ['required', 'string', function ($attr, $value, $fail) {
                if (str_word_count(strip_tags($value)) > 100) {
                    $fail('Short description must be 100 words or less.');
                }
            }],
            'category_id'       => 'required|exists:categories,id',
            'body'              => 'required|string',
            'featured_image'    => 'required|image|max:8192',
            'tags'              => 'nullable|string|max:600', // comma-separated
        ]);

        $featuredPath = $request->file('featured_image')->store('posts/featured', 'public');

        $slug = Str::slug($data['title']) . '-' . Str::lower(Str::random(6));

        $post = Post::create([
            'user_id'           => Auth::id(),
            'type'              => 'article',
            'category_id'       => $data['category_id'],
            'title'             => $data['title'],
            'slug'              => $slug,
            'short_description' => $data['short_description'],
            'body'              => $data['body'],
            'featured_image'    => $featuredPath,
        ]);

        if (!empty($data['tags'])) {
            $tagIds = collect(explode(',', $data['tags']))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->unique()
                ->take(10)
                ->map(fn ($name) => Tag::findOrCreateByName($name)->id)
                ->values();
            $post->tags()->sync($tagIds);
        }

        return response()->json([
            'success' => true,
            'post'    => $this->shape($post->fresh(['user','media','category','tags'])),
            'url'     => url('/articles/' . $post->slug),
        ], 201);
    }

    /** Upload an inline image inside the Quill editor (article body) */
    public function uploadInlineImage(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|image|max:8192']);
        $path = $request->file('file')->store('articles/inline', 'public');
        return response()->json(['url' => asset('storage/' . $path)]);
    }

    /**
     * POST /api/posts/{post}/like — set / change / clear the authenticated user's
     * reaction (Facebook-style), optionally scoped to a media item.
     *
     * One row per (post, media, user). Behavior given an incoming `reaction`:
     *   • no existing row            → add it          (likes_count +1)
     *   • existing, SAME reaction    → remove it       (likes_count -1)  ← toggle off
     *   • existing, DIFFERENT        → switch reaction (count unchanged)
     *
     * `likes_count` stays a single total across all reaction types.
     */
    public function toggleLike(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'media_id' => [
                'nullable', 'integer',
                Rule::exists('post_media', 'id')->where(fn ($q) => $q->where('post_id', $post->id)),
            ],
            'reaction' => ['nullable', Rule::in(Post::REACTIONS)],
        ]);

        $userId   = Auth::id();
        $mediaId  = $data['media_id'] ?? null;
        $reaction = $data['reaction'] ?? 'like';

        $row = DB::table('post_likes')
            ->where('post_id', $post->id)
            ->where('user_id', $userId)
            ->where(fn ($q) => $mediaId === null ? $q->whereNull('media_id') : $q->where('media_id', $mediaId))
            ->first();

        if ($row && $row->reaction === $reaction) {
            // Same reaction tapped again → remove it.
            DB::table('post_likes')->where('id', $row->id)->delete();
            $post->decrement('likes_count');
            $current = null;
            app(InteractionRecorder::class)->record($userId, $post->id, 'unlike');
        } elseif ($row) {
            // Switching from one reaction to another — no count change.
            DB::table('post_likes')->where('id', $row->id)->update([
                'reaction'   => $reaction,
                'updated_at' => now(),
            ]);
            $current = $reaction;
        } else {
            // First reaction for this user/scope.
            DB::table('post_likes')->insert([
                'post_id'    => $post->id,
                'media_id'   => $mediaId,
                'user_id'    => $userId,
                'reaction'   => $reaction,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $post->increment('likes_count');
            $current = $reaction;
            app(InteractionRecorder::class)->record($userId, $post->id, 'like');

            // Notify the post owner (skip self-reactions; throttle to one notice per
            // actor+post per day so a quick react/unreact loop doesn't spam the bell).
            if ($post->user_id !== $userId) {
                $alreadyRecent = UserNotification::where('user_id', $post->user_id)
                    ->where('actor_id', $userId)
                    ->where('type', 'like_post')
                    ->whereRaw("JSON_EXTRACT(data, '$.post_id') = ?", [$post->id])
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();
                if (!$alreadyRecent) {
                    $actor = Auth::user();
                    UserNotification::create([
                        'user_id'  => $post->user_id,
                        'actor_id' => $userId,
                        'type'     => 'like_post',
                        'data'     => [
                            'actor_name'     => $actor?->name,
                            'actor_username' => $actor?->username,
                            'post_id'        => $post->id,
                            'media_id'       => $mediaId,
                            'reaction'       => $reaction,
                        ],
                    ]);
                }
            }
        }

        $mediaLikes = $mediaId === null
            ? null
            : DB::table('post_likes')->where('post_id', $post->id)->where('media_id', $mediaId)->count();

        return response()->json([
            'success'           => true,
            'reacted'           => $current !== null,
            'reaction'          => $current,
            'liked'             => $current !== null,   // back-compat alias
            'likes_count'       => $post->fresh()->likes_count,
            'top_reactions'     => $this->topReactions($post->id, $mediaId),
            'media_id'          => $mediaId,
            'media_likes_count' => $mediaLikes,
        ]);
    }

    /**
     * Distinct reaction keys present for a post, ordered most-used first.
     * Drives the little emoji stack on the card. With $mediaId the count is
     * scoped to that one image (used by the gallery modal); otherwise it
     * aggregates every reaction on the post so it matches `likes_count`.
     */
    private function topReactions(int $postId, ?int $mediaId = null): array
    {
        return DB::table('post_likes')
            ->where('post_id', $postId)
            ->when($mediaId !== null, fn ($q) => $q->where('media_id', $mediaId))
            ->select('reaction', DB::raw('count(*) as c'))
            ->groupBy('reaction')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('reaction')
            ->all();
    }

    /** POST /api/posts/{post}/share — record a share + notify the owner (throttled). */
    public function recordShare(Post $post): JsonResponse
    {
        $userId = Auth::id();

        // Persist the share row + feed the affinity signal.
        DB::table('post_shares')->insert([
            'post_id'    => $post->id,
            'user_id'    => $userId,
            'created_at' => now(),
        ]);
        app(InteractionRecorder::class)->record($userId, $post->id, 'share');

        if ($post->user_id !== $userId) {
            $alreadyRecent = UserNotification::where('user_id', $post->user_id)
                ->where('actor_id', $userId)
                ->where('type', 'share_post')
                ->whereRaw("JSON_EXTRACT(data, '$.post_id') = ?", [$post->id])
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if (!$alreadyRecent) {
                $actor = Auth::user();
                UserNotification::create([
                    'user_id'  => $post->user_id,
                    'actor_id' => $userId,
                    'type'     => 'share_post',
                    'data'     => [
                        'actor_name'     => $actor?->name,
                        'actor_username' => $actor?->username,
                        'post_id'        => $post->id,
                    ],
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /** DELETE /api/posts/{post} */
    public function destroy(Post $post): JsonResponse
    {
        abort_unless($post->user_id === Auth::id() || Auth::user()?->isAdmin(), 403);
        $post->delete();
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/posts/saved — paginated list of posts the user has bookmarked.
     * Accepts ?category= : numeric folder id, 'uncategorized', or 'all' (default).
     */
    public function saved(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $filter = (string) $request->query('category', 'all');

        $with = ['user:id,name,username,profile_picture', 'category', 'media', 'tags'];
        $with['likers'] = fn ($q) => $q->where('users.id', $userId);

        $posts = Post::with($with)
            ->whereExists(function ($sub) use ($userId, $filter) {
                $sub->select(DB::raw(1))
                    ->from('post_saves')
                    ->whereColumn('post_saves.post_id', 'posts.id')
                    ->where('post_saves.user_id', $userId);

                if ($filter === 'uncategorized') {
                    $sub->whereNull('post_saves.save_category_id');
                } elseif (ctype_digit($filter)) {
                    $sub->where('post_saves.save_category_id', (int) $filter);
                }
            })
            ->orderByDesc(
                DB::table('post_saves')
                    ->select('created_at')
                    ->whereColumn('post_saves.post_id', 'posts.id')
                    ->where('post_saves.user_id', $userId)
                    ->limit(1)
            )
            ->paginate(20);

        // Per-post: which folder is it currently in? Used by the modal so cards
        // can show their badge + drive the "move to folder" picker.
        $postIds = collect($posts->items())->pluck('id');
        $folderByPost = $postIds->isEmpty()
            ? collect()
            : DB::table('post_saves')
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->pluck('save_category_id', 'post_id');

        return response()->json([
            'data'  => collect($posts->items())->map(function ($p) use ($folderByPost) {
                $shaped = $this->shape($p);
                $shaped['save_category_id'] = $folderByPost[$p->id] ?? null;
                return $shaped;
            }),
            'total' => $posts->total(),
        ]);
    }

    /**
     * POST /api/posts/{post}/save — toggle saved-post for the authenticated user.
     *
     * Body (optional):
     *   save_category_id : int|null   target folder; null = Uncategorized
     *   new_category     : string     create a new folder with this name and save there
     *
     * Behavior:
     *  • Post not yet saved → save it (in chosen folder or Uncategorized).
     *  • Post already saved AND a category is specified → MOVE it to that folder (don't unsave).
     *  • Post already saved AND no category is specified → unsave (toggle off).
     */
    public function toggleSave(Request $request, Post $post): JsonResponse
    {
        $userId = Auth::id();

        $data = $request->validate([
            'save_category_id' => [
                'nullable', 'integer',
                Rule::exists('save_categories', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'new_category' => 'nullable|string|max:60',
        ]);

        // "Create folder on the fly" path: turn the name into a real category.
        $categoryId = $data['save_category_id'] ?? null;
        $newCategoryPayload = null;
        if (!empty($data['new_category'])) {
            $name = trim($data['new_category']);
            if ($name !== '') {
                $cat = SaveCategory::create([
                    'user_id' => $userId,
                    'name'    => $name,
                    'slug'    => SaveCategory::uniqueSlugFor($userId, $name),
                ]);
                $categoryId = $cat->id;
                $newCategoryPayload = ['id' => $cat->id, 'name' => $cat->name, 'slug' => $cat->slug, 'count' => 1];
            }
        }

        $explicitCategory = $request->has('save_category_id') || $newCategoryPayload !== null;

        $existing = DB::table('post_saves')
            ->where('post_id', $post->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            // No category specified → plain toggle-off (unsave).
            if (!$explicitCategory) {
                DB::table('post_saves')->where('id', $existing->id)->delete();
                return response()->json(['success' => true, 'saved' => false]);
            }
            // Category specified → move (re-file) without unsaving.
            DB::table('post_saves')->where('id', $existing->id)->update([
                'save_category_id' => $categoryId,
                'updated_at'       => now(),
            ]);
            return response()->json([
                'success'          => true,
                'saved'            => true,
                'moved'            => true,
                'save_category_id' => $categoryId,
                'new_category'     => $newCategoryPayload,
            ]);
        }

        DB::table('post_saves')->insert([
            'post_id'          => $post->id,
            'user_id'          => $userId,
            'save_category_id' => $categoryId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success'          => true,
            'saved'            => true,
            'save_category_id' => $categoryId,
            'new_category'     => $newCategoryPayload,
        ]);
    }

    /**
     * POST /api/posts/{post}/hide — hide the post from this user's feed.
     * `reason` distinguishes a hard "Hide" from a softer "Not interested" signal,
     * but both have the same effect: the post no longer appears in the ranker.
     */
    public function hide(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|in:hide,not_interested',
        ]);
        $userId = Auth::id();
        $reason = $data['reason'] ?? 'hide';

        DB::table('post_hides')->updateOrInsert(
            ['post_id' => $post->id, 'user_id' => $userId],
            ['reason' => $reason, 'updated_at' => now(), 'created_at' => now()],
        );

        // Feed the affinity signal so the ranker also down-weights similar posts.
        app(InteractionRecorder::class)->record($userId, $post->id, 'hide');

        return response()->json(['success' => true, 'hidden' => true, 'reason' => $reason]);
    }

    /** PATCH /api/posts/{post} — edit own post (status / description / title). */
    public function update(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === Auth::id() || Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'status_text'       => 'nullable|string|max:2000',
            'description'       => 'nullable|string|max:2000',
            'title'             => 'nullable|string|max:240',
            'short_description' => 'nullable|string|max:2000',
        ]);

        // Only update fields meaningful for this post type — saves us from
        // accidentally clearing a sibling field on a partial PATCH.
        $patch = [];
        if ($post->type === 'status' && array_key_exists('status_text', $data)) {
            $patch['status_text'] = $data['status_text'];
        }
        if (in_array($post->type, ['image', 'video'], true) && array_key_exists('description', $data)) {
            $patch['description'] = $data['description'];
        }
        if ($post->type === 'article') {
            if (array_key_exists('title', $data))             $patch['title']             = $data['title'];
            if (array_key_exists('short_description', $data)) $patch['short_description'] = $data['short_description'];
        }
        if (!empty($patch)) {
            $post->update($patch);
        }

        return response()->json([
            'success' => true,
            'post'    => $this->shape($post->fresh(['user', 'media', 'category', 'tags'])),
        ]);
    }

    /** Public so other controllers (e.g. UserController) can reuse it. */
    public function shapePublic(Post $p, bool $includeBody = false): array
    {
        return $this->shape($p, $includeBody);
    }

    /** Consistent post payload for the SPA */
    public function shape(Post $p, bool $includeBody = false): array
    {
        $user = $p->user;

        // Per-media aggregates — only computed when there is media to enrich.
        $mediaIds = $p->media->pluck('id');
        if ($mediaIds->isNotEmpty()) {
            $likeCounts = DB::table('post_likes')
                ->whereIn('media_id', $mediaIds)
                ->select('media_id', DB::raw('count(*) as c'))
                ->groupBy('media_id')->pluck('c', 'media_id');
            $commentCounts = DB::table('comments')
                ->whereIn('media_id', $mediaIds)
                ->select('media_id', DB::raw('count(*) as c'))
                ->groupBy('media_id')->pluck('c', 'media_id');
            $myMediaReaction = Auth::check()
                ? DB::table('post_likes')
                    ->whereIn('media_id', $mediaIds)
                    ->where('user_id', Auth::id())
                    ->pluck('reaction', 'media_id')
                : collect();
            // Top reaction keys per media item (most-used first, up to 3).
            $mediaTopReactions = DB::table('post_likes')
                ->whereIn('media_id', $mediaIds)
                ->select('media_id', 'reaction', DB::raw('count(*) as c'))
                ->groupBy('media_id', 'reaction')
                ->get()
                ->groupBy('media_id')
                ->map(fn ($rows) => $rows->sortByDesc('c')->pluck('reaction')->take(3)->values()->all());
        } else {
            $likeCounts = collect();
            $commentCounts = collect();
            $myMediaReaction = collect();
            $mediaTopReactions = collect();
        }

        // Post-level reaction of the current viewer (prefer the eager-loaded
        // relationship to avoid an extra query in the feed list).
        $myReaction = null;
        if (Auth::check()) {
            if ($p->relationLoaded('likers')) {
                $mine = $p->likers->first(fn ($u) => ($u->pivot->media_id ?? null) === null);
                $myReaction = $mine ? ($mine->pivot->reaction ?? 'like') : null;
            } else {
                $myReaction = $p->reactionBy(Auth::id());
            }
        }

        return [
            'id'                => $p->id,
            'type'              => $p->type,
            'title'             => $p->title,
            'slug'              => $p->slug,
            'short_description' => $p->short_description,
            'body'              => $includeBody ? $p->body : null,
            'featured_image'    => $p->featured_image_url,
            'thumbnail'         => $p->thumbnail_url,
            'embed_provider'    => $p->embed_provider,
            'embed_id'          => $p->embed_id,
            'embed_url'         => $p->embed_url,
            'status_text'       => $p->status_text,
            'bg_color'          => $p->bg_color,
            'font_color'        => $p->font_color,
            'description'       => $p->description,
            'is_adult'          => (bool) $p->is_adult,
            'likes_count'       => $p->likes_count,
            'comments_count'    => $p->comments_count,
            'views_count'       => $p->views_count,
            'liked'             => $myReaction !== null,
            'my_reaction'       => $myReaction,
            'top_reactions'     => $p->likes_count > 0 ? $this->topReactions($p->id) : [],
            'saved'             => Auth::check() ? $p->isSavedBy(Auth::id()) : false,
            'is_owner'          => Auth::check() && $p->user_id === Auth::id(),
            'created_at'        => $p->created_at?->diffForHumans(),
            'created_at_full'   => $p->created_at?->format('M j, Y · g:i A'),
            'created_at_iso'    => $p->created_at?->toIso8601String(),
            'media'             => $p->media->map(fn ($m) => [
                'id'             => $m->id,
                'kind'           => $m->kind,
                'url'            => $m->url,
                'width'          => $m->width,
                'height'         => $m->height,
                'likes_count'    => (int) ($likeCounts[$m->id] ?? 0),
                'comments_count' => (int) ($commentCounts[$m->id] ?? 0),
                'liked'          => $myMediaReaction->has($m->id),
                'my_reaction'    => $myMediaReaction[$m->id] ?? null,
                'top_reactions'  => $mediaTopReactions[$m->id] ?? [],
            ]),
            'category' => $p->category ? [
                'id' => $p->category->id, 'name' => $p->category->name, 'slug' => $p->category->slug,
            ] : null,
            'tags' => $p->relationLoaded('tags')
                ? $p->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
                : [],
            'user' => $user ? [
                'id'              => $user->id,
                'name'            => $user->name,
                'username'        => $user->username,
                'profile_picture' => $user->profile_picture
                    ? asset('storage/' . $user->profile_picture) : null,
            ] : null,
            'view_url' => match (true) {
                $p->type === 'article' => url('/articles/' . $p->slug),
                $p->type === 'book' && $p->relationLoaded('bookDetail') && $p->bookDetail?->slug
                    => url('/books/' . $p->bookDetail->slug),
                default => null,
            },
            'book'     => $p->type === 'book' && $p->relationLoaded('bookDetail') && $p->bookDetail ? [
                'md5'          => $p->bookDetail->md5,
                'slug'         => $p->bookDetail->slug,
                'title'        => $p->bookDetail->title,
                'author'       => $p->bookDetail->author,
                'publisher'    => $p->bookDetail->publisher,
                'year'         => $p->bookDetail->year,
                'language'     => $p->bookDetail->language,
                'extension'    => $p->bookDetail->extension,
                'size'         => $p->bookDetail->size,
                'cover_url'    => $p->bookDetail->cover_url,
                'download_url' => Auth::check() ? $p->bookDetail->download_url : null,
                'description'  => $p->bookDetail->description,
            ] : null,
        ];
    }
}
