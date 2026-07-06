<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'md5', 'slug', 'title', 'author', 'publisher', 'year',
        'language', 'extension', 'size', 'cover_url', 'download_url',
        'description',
        'reddit_post_id', 'reddit_posted_at', 'reddit_attempts', 'reddit_last_error',
        'pinterest_pin_id', 'pinterest_posted_at', 'pinterest_attempts', 'pinterest_last_error',
    ];

    protected $casts = [
        'reddit_posted_at'    => 'datetime',
        'pinterest_posted_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
