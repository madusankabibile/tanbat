<?php

namespace App\Support;

use App\Models\LegacyArticle;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Normalises the two article sources into one array shape so the single
 * omrms.article view never has to know which model it was handed:
 *   - Post (type=article) — native articles AND migrated legacy ones that were
 *     materialised as posts.
 *   - LegacyArticle — the orphan migrated articles with no backing post.
 */
class OmrmsArticle
{
    /** Normalise an article-type Post (+ its related posts) for the omrms view. */
    public static function fromPost(Post $post, Collection $related): array
    {
        return [
            'title'       => (string) $post->title,
            'url'         => Omrms::articleUrl($post),
            'description' => Omrms::describe($post->short_description ?: $post->body),
            'body'        => (string) $post->body,
            'cover'       => $post->featured_image_url,
            'author'      => optional($post->user)->name ?: optional($post->user)->username,
            'category'    => optional($post->category)->name,
            'publishedAt' => $post->created_at,
            'updatedAt'   => $post->updated_at,
            'keywords'    => $post->relationLoaded('tags')
                ? $post->tags->pluck('name')->filter()->values()->all()
                : [],
            'related'     => $related->map(fn (Post $p) => [
                'title' => (string) $p->title,
                'url'   => Omrms::articleUrl($p),
                'cover' => $p->featured_image_url,
                'date'  => $p->created_at,
            ])->all(),
        ];
    }

    /** Normalise an orphan LegacyArticle (+ its related legacy rows) for the view. */
    public static function fromLegacy(LegacyArticle $article, Collection $related): array
    {
        return [
            'title'       => (string) $article->title,
            'url'         => Omrms::legacyUrl($article),
            'description' => Omrms::describe($article->body),
            'body'        => (string) $article->body,
            'cover'       => $article->coverUrl(),
            'author'      => $article->author_name ?: optional($article->user)->name,
            'category'    => $article->category_name,
            'publishedAt' => $article->published_at,
            'updatedAt'   => $article->published_at,
            'keywords'    => $article->tagList(),
            'related'     => $related->map(fn (LegacyArticle $a) => [
                'title' => (string) $a->title,
                'url'   => Omrms::legacyUrl($a),
                'cover' => $a->coverUrl(),
                'date'  => $a->published_at,
            ])->all(),
        ];
    }
}
