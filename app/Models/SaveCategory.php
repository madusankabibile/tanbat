<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SaveCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'slug'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_saves')
            ->withPivot('save_category_id')
            ->withTimestamps();
    }

    /**
     * Produce a slug unique within this user's categories.
     * Appends -2, -3, … if the base slug is already taken.
     */
    public static function uniqueSlugFor(int $userId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $n = 2;
        while (static::where('user_id', $userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}
