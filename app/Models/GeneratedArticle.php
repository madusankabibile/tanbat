<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An AI-generated article backing an old /read-blog/{id}_{slug}.html URL.
 * See the create_generated_articles_table migration for intent.
 */
class GeneratedArticle extends Model
{
    protected $fillable = [
        'old_id', 'slug', 'title', 'excerpt', 'body',
        'category', 'tags', 'views', 'model', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views'        => 'integer',
    ];

    /** Tags as a clean array (stored comma-separated). */
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
     * Reconstruct a human title from a WoWonder URL slug, e.g.
     * "investment-and-growth-trends-in-the-glioblastoma-multiforme-treatment-market"
     * → "Investment and Growth Trends in the Glioblastoma Multiforme Treatment Market".
     * Small stop-words stay lowercase unless first/last.
     */
    public static function titleFromSlug(string $slug): string
    {
        $slug = str_replace(['_', '-', '+', '%20'], ' ', $slug);
        $slug = trim(preg_replace('/\s+/', ' ', $slug) ?? '');
        if ($slug === '') {
            return '';
        }

        $small = ['a','an','and','as','at','but','by','for','from','in','nor','of','on','or','the','to','via','with'];
        $words = explode(' ', mb_strtolower($slug));
        $last  = count($words) - 1;

        foreach ($words as $i => $w) {
            if ($w === '') {
                continue;
            }
            if ($i !== 0 && $i !== $last && in_array($w, $small, true)) {
                continue; // keep stop-words lowercase mid-title
            }
            $words[$i] = Str::ucfirst($w);
        }

        return implode(' ', $words);
    }
}
