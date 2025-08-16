<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPreferenceLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_preference_logs';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'topic_id',
        'interaction_type',
        'chosen_ai',
        'choice_reason',
        'performance_score',
        'success_rate',
        'time_spent_seconds',
        'attempt_count',
        'difficulty_level',
        'context_data',
        'attribution_chat_message_id',
        'attribution_model',
        'attribution_confidence',
        'attribution_delay_sec',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'performance_score' => 'decimal:2',
        'success_rate' => 'decimal:2',
        'time_spent_seconds' => 'integer',
        'attempt_count' => 'integer',
        'context_data' => 'array',
        'attribution_delay_sec' => 'integer',
    ];

    /**
     * Get the user that owns this preference log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the split screen session associated with this preference log.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SplitScreenSession::class, 'session_id');
    }

    /**
     * Get the topic associated with this preference log.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'topic_id');
    }

    /**
     * Get the chat message that attributed this preference.
     */
    public function attributionChatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'attribution_chat_message_id');
    }

    /**
     * Scope to filter by interaction type.
     */
    public function scopeByInteractionType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope to filter by chosen AI model.
     */
    public function scopeByChosenAI($query, $ai)
    {
        return $query->where('chosen_ai', $ai);
    }

    /**
     * Scope to filter by topic.
     */
    public function scopeByTopic($query, $topicId)
    {
        return $query->where('topic_id', $topicId);
    }

    /**
     * Scope to filter by difficulty level.
     */
    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Get the interaction type label.
     */
    public function getInteractionTypeLabelAttribute(): string
    {
        return match($this->interaction_type) {
            'quiz' => 'Quiz',
            'practice' => 'Practice',
            'code_execution' => 'Code Execution',
            default => ucfirst($this->interaction_type)
        };
    }

    /**
     * Get the chosen AI label.
     */
    public function getChosenAILabelAttribute(): string
    {
        return match($this->chosen_ai) {
            'gemini' => 'Gemini',
            'together' => 'Together AI',
            'both' => 'Both Equally',
            'neither' => 'Neither',
            default => ucfirst($this->chosen_ai)
        };
    }

    /**
     * Check if this preference log is for a successful interaction.
     */
    public function isSuccessful(): bool
    {
        return $this->success_rate >= 70.0; // 70% threshold
    }

    /**
     * Get performance category based on score.
     */
    public function getPerformanceCategoryAttribute(): string
    {
        if ($this->performance_score >= 90) return 'excellent';
        if ($this->performance_score >= 80) return 'good';
        if ($this->performance_score >= 70) return 'fair';
        return 'poor';
    }

    /**
     * Get formatted time spent.
     */
    public function getFormattedTimeSpentAttribute(): string
    {
        if (!$this->time_spent_seconds) return 'N/A';
        
        $minutes = floor($this->time_spent_seconds / 60);
        $seconds = $this->time_spent_seconds % 60;
        
        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }
        
        return "{$seconds}s";
    }

    /**
     * Get TICA metrics for this preference log.
     */
    public function getTicaMetrics(): array
    {
        return [
            'preference_log_id' => $this->id,
            'interaction_type' => $this->interaction_type,
            'chosen_ai' => $this->chosen_ai,
            'performance_score' => $this->performance_score,
            'success_rate' => $this->success_rate,
            'time_spent_seconds' => $this->time_spent_seconds,
            'attempt_count' => $this->attempt_count,
            'difficulty_level' => $this->difficulty_level,
            'attribution_model' => $this->attribution_model,
            'attribution_confidence' => $this->attribution_confidence,
        ];
    }
}
