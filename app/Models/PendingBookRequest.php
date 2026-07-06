<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingBookRequest extends Model
{
    protected $fillable = [
        'user_id', 'md5', 'query', 'payload', 'due_at', 'processed_at', 'post_id',
    ];

    protected $casts = [
        'payload'      => 'array',
        'due_at'       => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
