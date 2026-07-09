<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    use HasFactory;

    /** Facebook-style reaction keys, in display order. Shared source of truth. */
    public const REACTIONS = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $fillable = [
        'user_id', 'type', 'category_id',
        'title', 'slug', 'featured_image', 'short_description', 'body',
        'status_text', 'bg_color', 'font_color',
        'description', 'is_adult', 'thumbnail', 'language',
        'embed_provider', 'embed_id',
        'is_legacy', 'legacy_post_id',
    ];

    protected $casts = [
        'is_adult'  => 'boolean',
        'is_legacy' => 'boolean',
    ];

    protected $appends = ['featured_image_url', 'thumbnail_url', 'embed_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function bookDetail(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')
            ->withPivot('reaction', 'media_id')
            ->withTimestamps();
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_saves')
            ->withPivot('save_category_id')
            ->withTimestamps();
    }

    public function hiders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_hides')
            ->withPivot('reason')
            ->withTimestamps();
    }

    public function isLikedBy(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->likers()->where('users.id', $userId)->exists();
    }

    /** The post-level reaction this user left, or null. */
    public function reactionBy(?int $userId): ?string
    {
        if (!$userId) return null;
        return \Illuminate\Support\Facades\DB::table('post_likes')
            ->where('post_id', $this->id)
            ->whereNull('media_id')
            ->where('user_id', $userId)
            ->value('reaction');
    }

    public function isSavedBy(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->savers()->where('users.id', $userId)->exists();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }
        // Legacy (migrated) articles store an absolute cover URL on the old
        // uploads host; leave those untouched. New posts store a storage path.
        return preg_match('~^https?://~i', $this->featured_image)
            ? $this->featured_image
            : asset('storage/' . $this->featured_image);
    }

    /** Canonical public URL for this post (legacy articles live at /blogs/{id}/{slug}). */
    public function permalink(): string
    {
        if ($this->is_legacy && $this->legacy_post_id) {
            return url('/blogs/' . $this->legacy_post_id . '/' . $this->slug);
        }
        return match ($this->type) {
            'article' => url('/articles/' . $this->slug),
            'book'    => url('/books/' . $this->slug),
            default   => url('/home#post-' . $this->id),
        };
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) return null;
        return preg_match('~^https?://~i', $this->thumbnail)
            ? $this->thumbnail
            : asset('storage/' . $this->thumbnail);
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if (!$this->embed_provider || !$this->embed_id) {
            return null;
        }
        return match ($this->embed_provider) {
            'youtube' => 'https://www.youtube.com/watch?v=' . $this->embed_id,
            'vimeo'   => 'https://vimeo.com/' . $this->embed_id,
            default   => null,
        };
    }
}
