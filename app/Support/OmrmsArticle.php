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
        $user = $post->user;

        return [
            'title'        => (string) $post->title,
            'url'          => Omrms::articleUrl($post),
            'description'  => Omrms::describe($post->short_description ?: $post->body),
            'body'         => Omrms::bodyImages($post->body),
            'cover'        => Omrms::img($post->featured_image_url),
            'author'       => optional($user)->name ?: optional($user)->username,
            'authorUrl'    => Omrms::authorUrl(optional($user)->username),
            'authorAvatar' => Omrms::img(optional($user)->avatarUrl()),
            'category'     => optional($post->category)->name,
            'categorySlug' => optional($post->category)->slug,
            'publishedAt'  => $post->created_at,
            'updatedAt'    => $post->updated_at,
            'keywords'     => $post->relationLoaded('tags')
                ? $post->tags->pluck('name')->filter()->values()->all()
                : [],
            'stats'        => [
                'views'    => (int) ($post->views_count ?? 0),
                'likes'    => (int) ($post->likes_count ?? 0),
                'comments' => (int) ($post->comments_count ?? 0),
                'readTime' => Omrms::readingTime($post->body),
            ],
            'related'      => $related->map(fn (Post $p) => [
                'title' => (string) $p->title,
                'url'   => Omrms::articleUrl($p),
                'cover' => Omrms::img($p->featured_image_url),
                'date'  => $p->created_at,
            ])->all(),
        ];
    }

    /** Normalise an orphan LegacyArticle (+ its related legacy rows) for the view. */
    public static function fromLegacy(LegacyArticle $article, Collection $related): array
    {
        $user = $article->user;

        return [
            'title'        => (string) $article->title,
            'url'          => Omrms::legacyUrl($article),
            'description'  => Omrms::describe($article->body),
            'body'         => Omrms::bodyImages($article->body),
            'cover'        => Omrms::img($article->coverUrl()),
            'author'       => $article->author_name ?: optional($user)->name,
            'authorUrl'    => Omrms::authorUrl($article->author_username ?: optional($user)->username),
            'authorAvatar' => Omrms::img(optional($user)->avatarUrl()),
            'category'     => $article->category_name,
            'categorySlug' => null, // legacy category strings aren't real Category rows
            'publishedAt'  => $article->published_at,
            'updatedAt'    => $article->published_at,
            'keywords'     => $article->tagList(),
            'stats'        => [
                'views'    => (int) ($article->views ?? 0),
                'likes'    => 0,
                'comments' => 0,
                'readTime' => Omrms::readingTime($article->body),
            ],
            'related'      => $related->map(fn (LegacyArticle $a) => [
                'title' => (string) $a->title,
                'url'   => Omrms::legacyUrl($a),
                'cover' => Omrms::img($a->coverUrl()),
                'date'  => $a->published_at,
            ])->all(),
        ];
    }
}
