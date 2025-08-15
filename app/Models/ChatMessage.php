<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'message',
        'response',
        'topic',
        'topic_id',
        'context',
        'conversation_history',
        'preferences',
        'model',
        'response_time_ms',
        'is_fallback',
        'user_rating',
    ];

    protected $casts = [
        'conversation_history' => 'json',
        'preferences' => 'json',
        'is_fallback' => 'boolean',
    ];

    /**
     * Get the user that owns the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the split-screen session this message belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SplitScreenSession::class, 'session_id');
    }
}
