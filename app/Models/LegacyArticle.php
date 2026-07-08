<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blog article migrated from the old Sngine tanbat.com site.
 * See the create_legacy_articles_table migration for the schema/intent.
 */
class LegacyArticle extends Model
{
    protected $fillable = [
        'old_post_id', 'old_article_id',
        'user_id', 'author_name', 'author_username',
        'title', 'slug', 'cover', 'body',
        'old_category_id', 'category_name', 'category_slug',
        'tags', 'views', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views'        => 'integer',
    ];

    /** Migrated author account, when one was matched at import time. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Full URL for the cover image (old covers live under the uploads base). */
    public function coverUrl(): ?string
    {
        if (empty($this->cover)) {
            return null;
        }
        // Already an absolute URL? Leave it be.
        if (preg_match('#^https?://#i', $this->cover)) {
            return $this->cover;
        }

        return config('legacy.uploads_base') . '/' . ltrim($this->cover, '/');
    }

    /** Tags as a clean array (old value is a comma-separated string). */
    public function tagList(): array
    {
        if (empty($this->tags)) {
            return [];
        }

        return collect(explode(',', $this->tags))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Canonical URL slug for a title — a faithful port of the old site's
     * get_url_text() so the migrated URLs match exactly what Google indexed:
     * keep original case, collapse non-alphanumerics to "-", cap at 10 words.
     */
    public static function slugify(string $string, int $length = 10): string
    {
        $string = html_entity_decode($string, ENT_QUOTES);
        $string = htmlspecialchars_decode($string, ENT_QUOTES);
        $string = preg_replace('/[^\pL\d]+/u', '-', $string);
        $string = trim((string) $string, '-');

        $words = explode('-', $string);
        if (count($words) > $length) {
            $string = trim(implode('-', array_slice($words, 0, $length)), '-');
        }

        return $string;
    }
}
