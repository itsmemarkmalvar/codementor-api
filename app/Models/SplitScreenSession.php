<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitScreenSession extends Model
{
    protected $fillable = [
        'user_id',
        'topic_id',
        'lesson_id',
        'session_type',
        'ai_models_used',
        'started_at',
        'ended_at',
        'total_messages',
        'engagement_score',
        'quiz_triggered',
        'practice_triggered',
        'user_choice',
        'choice_reason',
        'clarification_needed',
        'clarification_request',
        'session_metadata',
    ];

    protected $casts = [
        'ai_models_used' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'quiz_triggered' => 'boolean',
        'practice_triggered' => 'boolean',
        'clarification_needed' => 'boolean',
        'session_metadata' => 'array',
    ];

    /**
     * Get the user that owns this session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the topic associated with this session.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'topic_id');
    }

    /**
     * Get the chat messages for this session.
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /**
     * Check if the session is active (not ended).
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * End the session.
     */
    public function endSession(): void
    {
        $this->ended_at = now();
        $this->save();
    }

    /**
     * Increment engagement score.
     */
    public function incrementEngagement(int $points = 1): void
    {
        $this->engagement_score += $points;
        $this->save();
    }

    /**
     * Record user choice after quiz/practice.
     */
    public function recordUserChoice(string $choice, ?string $reason = null): void
    {
        $this->user_choice = $choice;
        $this->choice_reason = $reason;
        $this->save();
    }

    /**
     * Mark quiz as triggered.
     */
    public function markQuizTriggered(): void
    {
        $this->quiz_triggered = true;
        $this->save();
    }

    /**
     * Mark practice as triggered.
     */
    public function markPracticeTriggered(): void
    {
        $this->practice_triggered = true;
        $this->save();
    }

    /**
     * Request clarification for lesson.
     */
    public function requestClarification(string $request): void
    {
        $this->clarification_needed = true;
        $this->clarification_request = $request;
        $this->save();
    }

    /**
     * Get session duration in minutes.
     */
    public function getDurationMinutes(): int
    {
        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInMinutes($endTime);
    }

    /**
     * Check if engagement threshold is met for triggering quiz/practice.
     */
    public function shouldTriggerEngagement(): bool
    {
        // Trigger if engagement score >= 10 or session duration >= 15 minutes
        return $this->engagement_score >= 10 || $this->getDurationMinutes() >= 15;
    }

    /**
     * Check if quiz threshold is met (30 points).
     */
    public function shouldTriggerQuiz(): bool
    {
        return $this->engagement_score >= 30 && !$this->quiz_triggered;
    }

    /**
     * Check if practice threshold is met (70 points).
     */
    public function shouldTriggerPractice(): bool
    {
        return $this->engagement_score >= 70 && !$this->practice_triggered && $this->quiz_triggered;
    }

    /**
     * Get current threshold status for frontend.
     */
    public function getThresholdStatus(): array
    {
        return [
            'quiz_threshold' => 30,
            'practice_threshold' => 70,
            'current_score' => $this->engagement_score,
            'quiz_unlocked' => $this->engagement_score >= 30,
            'practice_unlocked' => $this->engagement_score >= 70 && $this->quiz_triggered,
            'quiz_triggered' => $this->quiz_triggered,
            'practice_triggered' => $this->practice_triggered,
            'points_to_quiz' => max(0, 30 - $this->engagement_score),
            'points_to_practice' => max(0, 70 - $this->engagement_score),
        ];
    }

    /**
     * Get TICA metrics for this session.
     */
    public function getTicaMetrics(): array
    {
        return [
            'session_id' => $this->id,
            'duration_minutes' => $this->getDurationMinutes(),
            'total_messages' => $this->total_messages,
            'engagement_score' => $this->engagement_score,
            'user_choice' => $this->user_choice,
            'quiz_triggered' => $this->quiz_triggered,
            'practice_triggered' => $this->practice_triggered,
            'clarification_needed' => $this->clarification_needed,
        ];
    }
}
