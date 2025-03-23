<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgress extends Model
{
    protected $fillable = [
        'user_id',
        'topic_id',
        'progress_percentage',
        'status',
        'started_at',
        'completed_at',
        'time_spent_minutes',
        'exercises_completed',
        'exercises_total',
        'completed_subtopics',
        'current_streak_days',
        'last_interaction_at',
        'progress_data',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'time_spent_minutes' => 'integer',
        'exercises_completed' => 'integer',
        'exercises_total' => 'integer',
        'completed_subtopics' => 'json',
        'current_streak_days' => 'integer',
        'last_interaction_at' => 'datetime',
        'progress_data' => 'json',
    ];

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the topic for this progress.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class);
    }
}
