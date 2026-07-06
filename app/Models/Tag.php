<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);
        $slug = Str::slug($name);
        return self::firstOrCreate(['slug' => $slug], ['name' => $name]);
    }
}
