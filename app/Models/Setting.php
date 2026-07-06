<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value settings row. Use the static get()/set() helpers rather than
 * querying directly — they keep a per-key cache warm so reads in hot paths (the
 * book-search wizard) don't hit the DB every request.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'setting:';

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX . $key, function () use ($key) {
            // Wrap in an array so a genuine null/missing value is still cached
            // (Cache::rememberForever treats a bare null as "not cached").
            return ['v' => optional(static::find($key))->value];
        });

        return $value['v'] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
