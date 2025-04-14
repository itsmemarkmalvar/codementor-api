<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_id',
        'user_id',
        'score',
        'max_possible_score',
        'percentage',
        'passed',
        'question_responses',
        'correct_questions',
        'time_spent_seconds',
        'attempt_number',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'integer',
        'max_possible_score' => 'integer',
        'percentage' => 'float',
        'question_responses' => 'array',
        'correct_questions' => 'array',
        'completed_at' => 'datetime',
        'time_spent_seconds' => 'integer',
        'attempt_number' => 'integer',
        'passed' => 'boolean',
    ];

    /**
     * Get the quiz associated with the attempt.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LessonQuiz::class, 'quiz_id');
    }

    /**
     * Get the user who took the quiz.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if the attempt is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Get the time taken in a human-readable format.
     *
     * @return string
     */
    public function getFormattedTimeTaken(): string
    {
        if ($this->time_spent_seconds === null || $this->time_spent_seconds === 0) {
            return 'Not completed';
        }

        $minutes = floor($this->time_spent_seconds / 60);
        $seconds = $this->time_spent_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get the status of the attempt.
     *
     * @return string
     */
    public function getStatus(): string
    {
        if (!$this->isCompleted()) {
            return 'In Progress';
        }

        return $this->passed ? 'Passed' : 'Failed';
    }

    /**
     * Get response for a specific question.
     *
     * @param int $questionId
     * @return mixed|null
     */
    public function getResponseForQuestion(int $questionId)
    {
        if (!isset($this->question_responses[$questionId])) {
            return null;
        }

        return $this->question_responses[$questionId];
    }

    /**
     * Get the number of correct answers.
     *
     * @return int
     */
    public function getCorrectAnswersCount(): int
    {
        return count($this->correct_questions ?? []);
    }

    /**
     * Get the percentage score.
     *
     * @return float
     */
    public function getPercentageScore(): float
    {
        return $this->percentage ?? 0;
    }
}
