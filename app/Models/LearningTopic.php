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
    
    /**
     * Get the prerequisite topics for this topic.
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPrerequisiteTopics()
    {
        if (empty($this->prerequisites)) {
            return collect([]);
        }
        
        $prerequisiteIds = array_filter(
            array_map('trim', explode(',', $this->prerequisites))
        );
        
        if (empty($prerequisiteIds)) {
            return collect([]);
        }
        
        return self::whereIn('id', $prerequisiteIds)->get();
    }
    
    /**
     * Check if this topic is locked for a specific user.
     * 
     * @param int $userId
     * @return bool
     */
    public function isLockedForUser($userId)
    {
        // If there are no prerequisites, the topic is unlocked
        if (empty($this->prerequisites)) {
            return false;
        }
        
        $prerequisiteIds = array_filter(
            array_map('trim', explode(',', $this->prerequisites))
        );
        
        if (empty($prerequisiteIds)) {
            return false;
        }
        
        // Get progress for all prerequisites
        $prerequisiteProgress = UserProgress::where('user_id', $userId)
            ->whereIn('topic_id', $prerequisiteIds)
            ->get();
            
        // If any prerequisite is not completed (progress < 80%), the topic is locked
        foreach ($prerequisiteIds as $prereqId) {
            $progress = $prerequisiteProgress->firstWhere('topic_id', $prereqId);
            
            // If no progress found or progress is less than 80%, topic is locked
            if (!$progress || $progress->progress_percentage < 80) {
                return true;
            }
        }
        
        // All prerequisites are completed, so topic is unlocked
        return false;
    }
}
