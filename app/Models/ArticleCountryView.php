<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-country view counter for an article post. See the
 * create_article_country_views_table migration for the rationale.
 */
class ArticleCountryView extends Model
{
    protected $fillable = ['post_id', 'country_code', 'views', 'last_viewed_at'];

    protected $casts = [
        'views'          => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
