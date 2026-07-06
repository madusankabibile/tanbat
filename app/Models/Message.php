<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'sender_id', 'body',
        'attachment_path', 'attachment_type',
        'read_at', 'delivered_at',
    ];

    protected $casts = [
        // Cast FK ids to int so strict (===) comparisons against Auth::id()
        // hold. Without this the DB driver returns them as strings, which
        // makes `is_mine` always false → own bubbles render white/left on reload.
        'conversation_id' => 'integer',
        'sender_id'       => 'integer',
        'read_at'         => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? asset('storage/' . $this->attachment_path) : null;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
