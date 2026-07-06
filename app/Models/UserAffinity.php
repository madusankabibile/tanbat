<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAffinity extends Model
{
    protected $table = 'user_affinity';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['user_id', 'dimension', 'dimension_value', 'score', 'events', 'updated_at'];

    protected $casts = [
        'score'      => 'float',
        'events'     => 'integer',
        'updated_at' => 'datetime',
    ];

    public const DIM_CATEGORY = 'category';
    public const DIM_TAG      = 'tag';
    public const DIM_AUTHOR   = 'author';
    public const DIM_TYPE     = 'type';
    public const DIM_LANGUAGE = 'language';
    public const DIM_COUNTRY  = 'country';
}
