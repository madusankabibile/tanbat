<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedImpression extends Model
{
    protected $table = 'feed_impressions';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['user_id', 'post_id', 'seen_at', 'shown_count'];

    protected $casts = [
        'seen_at'     => 'datetime',
        'shown_count' => 'integer',
    ];
}
