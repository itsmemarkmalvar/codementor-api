<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeCategory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'display_order',
        'parent_id', // For nested categories
        'is_active',
        'required_level', // Minimum user level required to access
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the practice problems that belong to this category.
     */
    public function problems(): HasMany
    {
        return $this->hasMany(PracticeProblem::class, 'category_id');
    }

    /**
     * Get the subcategories of this category.
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(PracticeCategory::class, 'parent_id');
    }

    /**
     * Get problems with specific difficulty levels.
     */
    public function problemsByDifficulty($difficulty)
    {
        return $this->problems()->where('difficulty_level', $difficulty)->get();
    }

    /**
     * Get problems count by difficulty level.
     */
    public function getProblemCountsByDifficulty()
    {
        return $this->problems()
            ->selectRaw('difficulty_level, count(*) as count')
            ->groupBy('difficulty_level')
            ->pluck('count', 'difficulty_level')
            ->toArray();
    }

    /**
     * Check if this category has any accessible problems for a user.
     */
    public function hasAccessibleProblems($userLevel = 0)
    {
        return $this->is_active && $this->required_level <= $userLevel;
    }

    /**
     * Get a recommended learning path for this category.
     */
    public function getLearningPath()
    {
        $difficulties = ['beginner', 'easy', 'medium', 'hard', 'expert'];
        $learningPath = [];
        
        foreach ($difficulties as $difficulty) {
            $problems = $this->problemsByDifficulty($difficulty)
                ->sortBy('success_rate')
                ->take(3)
                ->toArray();
                
            if (!empty($problems)) {
                $learningPath[$difficulty] = $problems;
            }
        }
        
        return $learningPath;
    }
} 