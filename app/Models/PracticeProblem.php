<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PracticeProblem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'category_id',
        'description',
        'instructions',
        'requirements',
        'difficulty_level',  // beginner, easy, medium, hard, expert
        'points',
        'estimated_time_minutes',
        'complexity_tags',   // space, time, etc.
        'topic_tags',        // algorithms, data structures, etc.
        'starter_code',
        'test_cases',
        'solution_code',
        'expected_output',
        'hints',             // Array of progressive hints
        'learning_concepts', // Related Java concepts
        'prerequisites',     // Required knowledge
        'success_rate',      // Percentage of successful submissions
        'is_featured',       // Whether to feature this problem
        'attempts_count',    // Total number of attempts
        'completion_count',  // Successful completions
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requirements' => 'array',
        'complexity_tags' => 'array',
        'topic_tags' => 'array',
        'test_cases' => 'array',
        'expected_output' => 'array',
        'hints' => 'array',
        'learning_concepts' => 'array',
        'prerequisites' => 'array',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the category that this practice problem belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PracticeCategory::class, 'category_id');
    }

    /**
     * Get the attempts for this practice problem.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class, 'problem_id');
    }

    /**
     * Get the resources associated with this practice problem.
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(PracticeResource::class, 'practice_problem_resources')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    /**
     * Get recommended next problems based on this problem's difficulty and topics.
     */
    public function getRecommendedProblems($limit = 3)
    {
        return self::where('id', '!=', $this->id)
            ->where(function($query) {
                $query->whereJsonContains('topic_tags', $this->topic_tags)
                      ->orWhere('difficulty_level', $this->difficulty_level);
            })
            ->orderBy('success_rate', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get next problems in the difficulty progression.
     */
    public function getNextLevelProblems($limit = 3)
    {
        $nextLevel = $this->getNextDifficultyLevel();
        
        return self::where('difficulty_level', $nextLevel)
            ->whereJsonContains('topic_tags', $this->topic_tags)
            ->orderBy('success_rate', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the next difficulty level.
     */
    private function getNextDifficultyLevel()
    {
        $levels = ['beginner', 'easy', 'medium', 'hard', 'expert'];
        $currentIndex = array_search($this->difficulty_level, $levels);
        
        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            return $levels[$currentIndex + 1];
        }
        
        return $this->difficulty_level; // Return same level if already at max
    }

    /**
     * Get a progressive hint for a user based on their attempts.
     */
    public function getProgressiveHint($userId, $forceNext = false)
    {
        if (!$this->hints || empty($this->hints)) {
            return null;
        }

        $userAttempt = PracticeAttempt::where('user_id', $userId)
            ->where('problem_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Initialize hint index
        $hintIndex = 0;
        
        if ($userAttempt && $userAttempt->last_hint_index >= 0) {
            $hintIndex = $forceNext ? $userAttempt->last_hint_index + 1 : $userAttempt->last_hint_index;
        }
        
        // Ensure we don't exceed available hints
        if ($hintIndex >= count($this->hints)) {
            $hintIndex = count($this->hints) - 1;
        }
        
        // Update the attempt record with the new hint index
        if ($userAttempt) {
            $userAttempt->update(['last_hint_index' => $hintIndex]);
        }
        
        return [
            'index' => $hintIndex,
            'content' => $this->hints[$hintIndex],
            'total_hints' => count($this->hints),
            'has_more_hints' => $hintIndex < count($this->hints) - 1
        ];
    }

    /**
     * Calculate difficulty score (1-10) for displaying to users
     */
    public function getDifficultyScore()
    {
        $levels = [
            'beginner' => 2,
            'easy' => 4,
            'medium' => 6,
            'hard' => 8,
            'expert' => 10
        ];
        
        return $levels[$this->difficulty_level] ?? 5;
    }
} 