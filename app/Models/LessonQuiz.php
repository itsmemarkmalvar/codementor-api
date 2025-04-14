<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonQuiz extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'module_id',
        'title',
        'description',
        'difficulty',
        'time_limit_minutes',
        'passing_score_percent',
        'points_per_question',
        'is_published',
        'order_index',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'time_limit_minutes' => 'integer',
        'passing_score_percent' => 'integer',
        'points_per_question' => 'integer',
        'is_published' => 'boolean',
        'order_index' => 'integer',
    ];

    /**
     * Get the module that owns the quiz.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LessonModule::class, 'module_id');
    }

    /**
     * Get the questions for this quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('order_index');
    }

    /**
     * Get the attempts for this quiz.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }

    /**
     * Get user attempts for this quiz.
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function userAttempts(int $userId)
    {
        return $this->attempts()->where('user_id', $userId)->orderBy('attempt_number')->get();
    }

    /**
     * Get the best attempt by a user for this quiz.
     * 
     * @param int $userId
     * @return QuizAttempt|null
     */
    public function getBestAttempt(int $userId)
    {
        return $this->attempts()
                    ->where('user_id', $userId)
                    ->orderBy('score', 'desc')
                    ->first();
    }

    /**
     * Check if a user has passed this quiz.
     * 
     * @param int $userId
     * @return bool
     */
    public function isPassedByUser(int $userId): bool
    {
        return $this->attempts()
                    ->where('user_id', $userId)
                    ->where('passed', true)
                    ->exists();
    }

    /**
     * Get the maximum possible score for this quiz.
     * 
     * @return int
     */
    public function getMaxScore(): int
    {
        return $this->questions()->count() * $this->points_per_question;
    }

    /**
     * Check if the quiz is ready for students.
     * 
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->is_published && $this->questions()->count() > 0;
    }
}
