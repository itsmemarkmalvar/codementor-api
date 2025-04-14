<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonExercise extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'module_id',
        'title',
        'type',
        'description',
        'instructions',
        'starter_code',
        'test_cases',
        'expected_output',
        'hints',
        'solution',
        'difficulty',
        'points',
        'order_index',
        'is_required',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'test_cases' => 'array',
        'expected_output' => 'array',
        'hints' => 'array',
        'solution' => 'array',
        'is_required' => 'boolean',
    ];

    /**
     * Get the module that this exercise belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LessonModule::class, 'module_id');
    }

    /**
     * Get the attempts for this exercise.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class, 'exercise_id');
    }

    /**
     * Get user attempts for this exercise.
     */
    public function userAttempts($userId)
    {
        return $this->attempts()->where('user_id', $userId)->orderBy('attempt_number')->get();
    }

    /**
     * Get the best attempt by a user for this exercise.
     */
    public function getBestAttempt($userId)
    {
        return $this->attempts()
                    ->where('user_id', $userId)
                    ->orderBy('score', 'desc')
                    ->first();
    }

    /**
     * Check if a user has completed this exercise.
     */
    public function isCompletedByUser($userId): bool
    {
        return $this->attempts()
                    ->where('user_id', $userId)
                    ->where('is_correct', true)
                    ->exists();
    }

    /**
     * Get the next available hint for a user based on their attempts.
     */
    public function getNextHint($userId)
    {
        if (!$this->hints || empty($this->hints)) {
            return null;
        }

        $hintsUsed = $this->attempts()
                         ->where('user_id', $userId)
                         ->whereNotNull('hints_used')
                         ->pluck('hints_used')
                         ->flatten()
                         ->unique()
                         ->toArray();

        foreach ($this->hints as $index => $hint) {
            if (!in_array($index, $hintsUsed)) {
                return [
                    'index' => $index,
                    'content' => $hint
                ];
            }
        }

        return null;
    }
}
