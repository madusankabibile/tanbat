<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostInteraction extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'post_id', 'event', 'weight', 'dwell_ms', 'created_at'];

    protected $casts = [
        'weight'     => 'float',
        'dwell_ms'   => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function post(): BelongsTo  { return $this->belongsTo(Post::class); }
}
