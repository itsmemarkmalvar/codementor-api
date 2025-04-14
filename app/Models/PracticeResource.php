<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PracticeResource extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'url',
        'type',      // 'article', 'video', 'documentation', 'course', etc.
        'source',    // platform or website the resource is from
        'is_premium',
        'difficulty_level',
        'estimated_time_minutes',
        'thumbnail_url',
        'is_official',
        'rating',
        'views',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_premium' => 'boolean',
        'is_official' => 'boolean',
        'rating' => 'float',
        'views' => 'integer',
        'estimated_time_minutes' => 'integer',
    ];

    /**
     * Get the practice problems associated with the resource.
     */
    public function problems(): BelongsToMany
    {
        return $this->belongsToMany(PracticeProblem::class, 'practice_problem_resources')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    /**
     * Get resources filtered by concept/learning topics
     * 
     * @param string|array $concepts
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findByLearningConcepts($concepts)
    {
        return static::whereHas('problems', function ($query) use ($concepts) {
            $query->where(function ($q) use ($concepts) {
                // If concepts is an array, search for any of the concepts
                if (is_array($concepts)) {
                    foreach ($concepts as $concept) {
                        $q->orWhereJsonContains('learning_concepts', $concept);
                    }
                } else {
                    // If concepts is a string, search for that specific concept
                    $q->whereJsonContains('learning_concepts', $concepts);
                }
            });
        })->get();
    }

    /**
     * Get the most relevant resources for a given problem
     * 
     * @param int $problemId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRelevantForProblem($problemId, $limit = 5)
    {
        return static::whereHas('problems', function ($query) use ($problemId) {
            $query->where('practice_problem_id', $problemId);
        })
        ->orderByDesc('rating')
        ->orderByDesc('views')
        ->limit($limit)
        ->get();
    }

    /**
     * Get popular resources
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPopular($limit = 10)
    {
        return static::orderByDesc('views')
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();
    }

    /**
     * Create resource recommendation for a specific error or struggle point
     * 
     * @param string $errorType Error type or struggle point
     * @param string $difficultyLevel User's skill level
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function recommendForError($errorType, $difficultyLevel = 'beginner')
    {
        // Get problems that address this type of error
        $relatedProblems = PracticeProblem::where(function ($query) use ($errorType) {
            $query->whereJsonContains('learning_concepts', $errorType)
                ->orWhereJsonContains('topic_tags', $errorType);
        })->pluck('id');
        
        // Get resources for these problems, prioritizing ones matching the user's level
        return static::whereHas('problems', function ($query) use ($relatedProblems) {
            $query->whereIn('practice_problem_id', $relatedProblems);
        })
        ->where('difficulty_level', $difficultyLevel)
        ->orWhere('difficulty_level', 'all')
        ->orderByDesc('rating')
        ->orderByDesc('views')
        ->limit(5)
        ->get();
    }
} 