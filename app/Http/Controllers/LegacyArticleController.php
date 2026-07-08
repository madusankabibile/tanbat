<?php

namespace App\Http\Controllers;

use App\Models\LegacyArticle;
use Illuminate\Http\Request;

/**
 * Serves the migrated old Sngine blog articles at their original
 * /blogs/{post_id}/{slug} URLs so existing inbound links / search rankings
 * keep working. Independent of the new /articles/{slug} blog.
 */
class LegacyArticleController extends Controller
{
    public function show(Request $request, int $postId, ?string $slug = null)
    {
        $article = LegacyArticle::with('user')
            ->where('old_post_id', $postId)
            ->firstOrFail();

        // Redirect to the canonical slug (301) when the incoming slug is
        // missing or wrong, mirroring the old site's behaviour and keeping a
        // single indexable URL per article.
        $canonical = $article->slug ?: LegacyArticle::slugify($article->title);
        if ($slug !== $canonical && $canonical !== '') {
            return redirect()->route('legacy.article', [
                'postId' => $article->old_post_id,
                'slug'   => $canonical,
            ], 301);
        }

        // Best-effort view counter (won't fail the request if the column locks).
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

        return view('legacy-article-show', [
            'article' => $article,
            'related' => $related,
        ]);
    }
}
