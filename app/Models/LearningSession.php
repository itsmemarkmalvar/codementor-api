<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningSession extends Model
{
    protected $fillable = [
        'user_id',
        'topic_id',
        'title',
        'started_at',
        'ended_at',
        'duration_minutes',
        'status',
        'progress_percentage',
        'tutor_preferences',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'progress_percentage' => 'integer',
        'tutor_preferences' => 'json',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the topic for this session.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class);
    }

    /**
     * Get the chat messages for this session.
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /**
     * Get the code snippets for this session.
     */
    public function codeSnippets(): HasMany
    {
        return $this->hasMany(CodeSnippet::class, 'session_id');
    }
}
