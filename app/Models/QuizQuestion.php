<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_id',
        'question_text',
        'type',
        'options',
        'correct_answers',
        'explanation',
        'points',
        'code_snippet',
        'order_index',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'correct_answers' => 'array',
        'points' => 'integer',
        'order_index' => 'integer',
    ];

    /**
     * Get the quiz that owns the question.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LessonQuiz::class, 'quiz_id');
    }

    /**
     * Check if the given answer is correct.
     * 
     * @param mixed $userAnswer
     * @return bool
     */
    public function isCorrect($userAnswer): bool
    {
        switch ($this->type) {
            case 'multiple_choice':
                if (is_array($userAnswer)) {
                    // Sort both arrays to ensure consistent comparison
                    sort($userAnswer);
                    $correctAnswers = $this->correct_answers;
                    sort($correctAnswers);
                    return $userAnswer == $correctAnswers;
                }
                return in_array($userAnswer, $this->correct_answers);
                
            case 'true_false':
                return $userAnswer === $this->correct_answers[0];
                
            case 'fill_in_blank':
                if (is_string($userAnswer)) {
                    // Case insensitive comparison for text answers
                    return in_array(strtolower(trim($userAnswer)), array_map('strtolower', array_map('trim', $this->correct_answers)));
                }
                return false;
                
            case 'code_snippet':
                // For code snippets, more complex logic might be required
                // This is a simple implementation that checks for exact matches
                return in_array($userAnswer, $this->correct_answers);
                
            default:
                return false;
        }
    }

    /**
     * Get formatted options for display.
     * 
     * @return array
     */
    public function getFormattedOptions(): array
    {
        if (!$this->options || !is_array($this->options)) {
            return [];
        }

        return $this->options;
    }

    /**
     * Get the question difficulty based on type and complexity.
     * 
     * @return string
     */
    public function getDifficulty(): string
    {
        if ($this->type === 'code_snippet') {
            return 'advanced';
        }
        
        if ($this->type === 'multiple_choice' && count($this->options) > 4) {
            return 'intermediate';
        }
        
        return 'beginner';
    }
}
