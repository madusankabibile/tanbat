<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    protected $fillable = [
        'title', 'body', 'link_url', 'image', 'placement',
        'is_active', 'weight', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weight'    => 'integer',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    protected $appends = ['image_url', 'ctr'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        return preg_match('~^https?://~i', $this->image)
            ? $this->image
            : asset('storage/' . $this->image);
    }

    public function getCtrAttribute(): float
    {
        if (!$this->impressions) return 0.0;
        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function scopeLive(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(fn ($q2) => $q2->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function scopePlacement(Builder $q, string $placement): Builder
    {
        return $q->where('placement', $placement);
    }

    public function recordImpression(): void
    {
        $this->newQuery()->whereKey($this->id)->increment('impressions');
    }

    public function recordClick(): void
    {
        $this->newQuery()->whereKey($this->id)->increment('clicks');
    }

    /** Pick one live ad for a placement, weighted random. */
    public static function pickFor(string $placement): ?self
    {
        $pool = static::live()->placement($placement)->get();
        if ($pool->isEmpty()) return null;

        $total = (int) $pool->sum(fn ($a) => max(1, (int) $a->weight));
        $rand  = random_int(1, max(1, $total));
        $acc   = 0;
        foreach ($pool as $ad) {
            $acc += max(1, (int) $ad->weight);
            if ($rand <= $acc) return $ad;
        }
        return $pool->first();
    }
}
