<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthToken extends Model
{
    protected $table = 'oauth_tokens';

    protected $fillable = [
        'provider', 'access_token', 'refresh_token', 'scope',
        'account_name', 'meta', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'meta'       => 'array',
    ];

    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->subSeconds(60)->isPast();
    }
}
