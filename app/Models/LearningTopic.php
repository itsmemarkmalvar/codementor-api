<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningTopic extends Model
{
    protected $fillable = [
        'title',
        'description',
        'difficulty_level',
        'order',
        'parent_id',
        'learning_objectives',
        'prerequisites',
        'estimated_hours',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the parent topic for this topic.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'parent_id');
    }

    /**
     * Get the subtopics for this topic.
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(LearningTopic::class, 'parent_id');
    }

    /**
     * Get the learning sessions for this topic.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class, 'topic_id');
    }

    /**
     * Get all user progress records for this topic.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'topic_id');
    }
}
