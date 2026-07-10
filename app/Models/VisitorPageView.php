<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single (visitor, path, day) bucket with a hit counter. Written only by
 * App\Http\Middleware\RecordVisitor via a raw upsert; read by the admin
 * statistics page. See the migration for why this exists alongside `visitors`.
 */
class VisitorPageView extends Model
{
    protected $fillable = ['visitor_token', 'path', 'day', 'hits'];

    protected $casts = [
        'day'  => 'date',
        'hits' => 'integer',
    ];
}
