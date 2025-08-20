<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreservedSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_identifier',
        'topic_id',
        'lesson_id',
        'conversation_history',
        'session_metadata',
        'is_active',
        'last_activity',
        'session_type',
        'ai_models_used'
    ];

    protected $casts = [
        'conversation_history' => 'array',
        'session_metadata' => 'array',
        'ai_models_used' => 'array',
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
    ];

    /**
     * Get the topic that owns the session.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'topic_id');
    }

    /**
     * Get the lesson that owns the session.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LessonModule::class, 'lesson_id');
    }

    /**
     * Scope to get active sessions for a user.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get sessions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get sessions for a specific topic.
     */
    public function scopeForTopic($query, $topicId)
    {
        return $query->where('topic_id', $topicId);
    }

    /**
     * Mark session as inactive.
     */
    public function markAsInactive()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Mark session as active.
     */
    public function markAsActive()
    {
        $this->update([
            'is_active' => true,
            'last_activity' => now()
        ]);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateActivity()
    {
        $this->update(['last_activity' => now()]);
    }

    /**
     * Add message to conversation history.
     */
    public function addMessage($message)
    {
        $history = $this->conversation_history ?? [];
        $history[] = $message;
        $this->update([
            'conversation_history' => $history,
            'last_activity' => now()
        ]);
    }

    /**
     * Get the most recent session for a user and topic.
     */
    public static function getMostRecentSession($userId, $topicId = null)
    {
        $query = self::forUser($userId)->active();
        
        if ($topicId) {
            $query->forTopic($topicId);
        }
        
        return $query->orderBy('last_activity', 'desc')->first();
    }

    /**
     * Create a new session with proper identifier.
     */
    public static function createSession($data)
    {
        $sessionId = $data['user_id'] . '_' . time();
        
        return self::create([
            'user_id' => $data['user_id'],
            'session_identifier' => $sessionId,
            'topic_id' => $data['topic_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'conversation_history' => $data['conversation_history'] ?? [],
            'session_metadata' => $data['session_metadata'] ?? [],
            'is_active' => true,
            'last_activity' => now(),
            'session_type' => $data['session_type'] ?? 'comparison',
            'ai_models_used' => $data['ai_models_used'] ?? ['gemini', 'together']
        ]);
    }
}
