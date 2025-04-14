<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'exercise_id',
        'attempt_number',
        'submitted_code',
        'submitted_answer',
        'is_correct',
        'score',
        'test_results',
        'feedback',
        'hints_used',
        'time_spent_seconds',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_answer' => 'array',
        'test_results' => 'array',
        'hints_used' => 'array',
        'is_correct' => 'boolean',
    ];

    /**
     * Get the user that made this attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exercise that this attempt is for.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(LessonExercise::class, 'exercise_id');
    }

    /**
     * Record a hint as used.
     */
    public function useHint(int $hintIndex): void
    {
        $hintsUsed = $this->hints_used ?? [];
        
        if (!in_array($hintIndex, $hintsUsed)) {
            $hintsUsed[] = $hintIndex;
            $this->hints_used = $hintsUsed;
            $this->save();
        }
    }

    /**
     * Get the number of test cases passed.
     */
    public function getTestCasesPassed(): int
    {
        if (!$this->test_results) {
            return 0;
        }

        $passed = 0;
        foreach ($this->test_results as $result) {
            if (isset($result['passed']) && $result['passed']) {
                $passed++;
            }
        }

        return $passed;
    }

    /**
     * Get the next attempt number for this user and exercise.
     */
    public static function getNextAttemptNumber($userId, $exerciseId): int
    {
        $maxAttempt = self::where('user_id', $userId)
                        ->where('exercise_id', $exerciseId)
                        ->max('attempt_number');
        
        return $maxAttempt ? $maxAttempt + 1 : 1;
    }

    /**
     * Update the module progress when this attempt is saved.
     */
    protected static function booted()
    {
        static::saved(function ($attempt) {
            $exercise = $attempt->exercise;
            $module = $exercise->module;
            
            $moduleProgress = ModuleProgress::firstOrCreate(
                [
                    'user_id' => $attempt->user_id,
                    'module_id' => $module->id
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'last_activity_at' => now()
                ]
            );
            
            $moduleProgress->updateProgressPercentage();
        });
    }
}
