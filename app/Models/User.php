<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function saveCategories(): HasMany
    {
        return $this->hasMany(SaveCategory::class)->orderBy('name');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** People this user follows */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /** People who follow this user */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function isFollowing(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->following()->where('users.id', $userId)->exists();
    }

    protected $fillable = [
        'name',
        'age',
        'gender',
        'country',
        'email',
        'username',
        'password',
        'profile_picture',
        'banner_image',
        'bio',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'age'               => 'integer',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** URL-ready avatar path for API responses */
    public function avatarUrl(): ?string
    {
        return $this->profile_picture
            ? asset('storage/' . $this->profile_picture)
            : null;
    }

    /** URL-ready banner path; null when the user hasn't uploaded one (client falls back to seeded texture art). */
    public function bannerUrl(): ?string
    {
        return $this->banner_image
            ? asset('storage/' . $this->banner_image)
            : null;
    }
}
