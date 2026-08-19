<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live TV channel — the detail row behind a post of type "tv".
 *
 * Mirrors BookDetail's shape (post_id + slug + its own fields) so the TV post
 * type slots into the same admin/permalink machinery books already use.
 *
 * `stream_url` is deliberately NOT in $appends and never reaches a view: the
 * public player is handed a short-lived signed proxy URL instead, so the real
 * manifest stays server-side. See App\Http\Controllers\TvStreamController.
 */
class TvChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'name', 'slug', 'logo', 'description',
        'stream_url', 'referer', 'user_agent', 'is_active', 'views',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'views'     => 'integer',
    ];

    /** Kept out of any accidental toJson() — the manifest URL is the secret. */
    protected $hidden = ['stream_url', 'referer', 'user_agent'];

    protected $appends = ['logo_url'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** Uploaded logos are storage paths; imported ones may be absolute URLs. */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }
        return preg_match('~^https?://~i', $this->logo)
            ? $this->logo
            : asset('storage/' . $this->logo);
    }

    public function permalink(): string
    {
        return url('/tv/' . $this->slug);
    }
}
