<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'response',
        'topic',
        'topic_id',
        'context',
        'conversation_history',
        'preferences',
    ];

    protected $casts = [
        'conversation_history' => 'json',
        'preferences' => 'json',
    ];

    /**
     * Get the user that owns the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
