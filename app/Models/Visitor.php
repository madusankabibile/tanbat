<?php

namespace App\Models;

use App\Services\UserAgentParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = [
        'visitor_token', 'ip_address', 'country_code', 'country_name',
        'page', 'referrer', 'user_agent', 'hits',
    ];

    /**
     * Rows whose user agent matches any known automation token.
     *
     * The classification itself lives in UserAgentParser, but the admin log
     * needs it *in SQL*: filtering in PHP after paginate() would report the
     * wrong total and leave short pages. The table is capped at 30 days of
     * visitors, so an OR-of-LIKEs is cheap.
     */
    public function scopeBots(Builder $q): Builder
    {
        return $q->where(function (Builder $w) {
            foreach (UserAgentParser::BOT_TOKENS as $token) {
                $w->orWhere('user_agent', 'like', '%' . self::escapeLike($token) . '%');
            }
        });
    }

    /**
     * The complement of scopeBots(). A NULL user agent matches no token, so it
     * is a human here — `NOT LIKE` against NULL yields NULL, which would
     * otherwise drop those rows from both scopes.
     */
    public function scopeHumans(Builder $q): Builder
    {
        return $q->where(function (Builder $w) {
            $w->whereNull('user_agent')->orWhere(function (Builder $x) {
                foreach (UserAgentParser::BOT_TOKENS as $token) {
                    $x->where('user_agent', 'not like', '%' . self::escapeLike($token) . '%');
                }
            });
        });
    }

    /** Neutralise LIKE wildcards so a token is matched literally. */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
