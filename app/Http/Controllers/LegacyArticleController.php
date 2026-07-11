<?php

namespace App\Http\Controllers;

use App\Models\LegacyArticle;
use App\Models\Post;
use App\Support\Omrms;
use App\Support\OmrmsArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Serves migrated old-Sngine blog articles at their original
 * /blogs/{post_id}/{slug} URLs so inbound links / search rankings keep working.
 *
 * Authored articles are materialised as native posts (is_legacy=1) by
 * `legacy:sync-posts`, so those render through the full interactive article
 * page (3-column, author-linked, like/comment/share). The ~187 articles with
 * no matched author fall back to the read-only legacy_articles view.
 */
class LegacyArticleController extends Controller
{
    public function show(Request $request, int $postId, ?string $slug = null)
    {
        // Preferred path: a native post backs this legacy article → full,
        // interactive article page shared with the /articles/{slug} blog.
        $post = Post::with([
                'user', 'category', 'tags',
                'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user']),
            ])
            ->where('is_legacy', true)
            ->where('legacy_post_id', $postId)
            ->first();

        if ($post) {
            return $this->showPost($post, $slug, $request);
        }

        return $this->showFromLegacyTable($postId, $slug);
    }

    /** Render a legacy article that exists as a native post. */
    private function showPost(Post $post, ?string $slug, Request $request)
    {
        // Canonical slug redirect (301) — one indexable URL per article.
        if ($slug !== $post->slug && $post->slug !== '') {
            return redirect()->route('legacy.article', [
                'postId' => $post->legacy_post_id,
                'slug'   => $post->slug,
            ], 301);
        }

        $post->increment('views_count');
        // Attribute the view to the visitor's country for /blog geo-ranking.
        app(\App\Services\ArticleGeoViews::class)->record($post->id, $request);

        $myReaction = Auth::check() ? $post->reactionBy(Auth::id()) : null;

        // Related = other legacy articles, most recent first.
        $related = Post::with(['user:id,name,username,profile_picture', 'category:id,name'])
            ->where('is_legacy', true)
            ->where('id', '!=', $post->id)
            ->latest()
            ->limit(6)
            ->get();

        if (Omrms::isActive()) {
            return view('omrms.article', ['a' => OmrmsArticle::fromPost($post, $related)]);
        }

        return view('article-show', [
            'post'       => $post,
            'liked'      => $myReaction !== null,
            'myReaction' => $myReaction,
            'related'    => $related,
        ]);
    }

    /** Fallback: read-only render straight from legacy_articles (no linked user). */
    private function showFromLegacyTable(int $postId, ?string $slug)
    {
        $article = LegacyArticle::with('user')
            ->where('old_post_id', $postId)
            ->firstOrFail();

        $canonical = $article->slug ?: LegacyArticle::slugify($article->title);
        if ($slug !== $canonical && $canonical !== '') {
            return redirect()->route('legacy.article', [
                'postId' => $article->old_post_id,
                'slug'   => $canonical,
            ], 301);
        }

        try {
            $article->increment('views');
        } catch (\Throwable $e) {
            // ignore
        }

        $related = LegacyArticle::query()
            ->where('id', '!=', $article->id)
            ->when($article->old_category_id, fn ($q) => $q->where('old_category_id', $article->old_category_id))
            ->latest('published_at')
            ->limit(6)
            ->get(['id', 'old_post_id', 'title', 'slug', 'cover', 'published_at']);

        if (Omrms::isActive()) {
            return view('omrms.article', ['a' => OmrmsArticle::fromLegacy($article, $related)]);
        }

        return view('legacy-article-show', [
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Serves the old Sngine short permalink /posts/{post_id}. On the old site
     * every post — articles included — was reachable at that URL, so inbound
     * links and search results still point here. 301-redirect to the canonical
     * /blogs/{id}/{slug} page. Anything else is a genuine 404.
     */
    public function showByPostId(int $postId)
    {
        // Prefer the materialised post's (deduplicated) slug when present.
        $post = Post::where('is_legacy', true)->where('legacy_post_id', $postId)->first(['legacy_post_id', 'slug']);
        if ($post) {
            return redirect()->route('legacy.article', [
                'postId' => $post->legacy_post_id,
                'slug'   => $post->slug,
            ], 301);
        }

        $article = LegacyArticle::where('old_post_id', $postId)->firstOrFail();
        $slug = $article->slug ?: LegacyArticle::slugify($article->title);

        return redirect()->route('legacy.article', [
            'postId' => $article->old_post_id,
            'slug'   => $slug,
        ], 301);
    }
}
