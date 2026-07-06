<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_one_id', 'user_two_id', 'last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest('id')->limit(1);
    }

    /** Return the other participant id, given one. */
    public function otherUserId(int $userId): int
    {
        return $this->user_one_id === $userId ? $this->user_two_id : $this->user_one_id;
    }

    /** Find-or-create the conversation between two users. */
    public static function between(int $a, int $b): self
    {
        if ($a === $b) {
            throw new \InvalidArgumentException('A user cannot have a conversation with themselves.');
        }
        $one = min($a, $b);
        $two = max($a, $b);
        return self::firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
        );
    }
}
